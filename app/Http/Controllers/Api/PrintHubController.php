<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Branch;
use App\Models\PrintAgent;
use App\Models\PrintCost;
use App\Models\PrinterPoolPrinter;
use App\Models\PrintProfile;
use App\Models\PrintJob;
use App\Services\PaperSizeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintHubController extends Controller
{
    /**
     * Authenticate agent by Bearer token (agent_key).
     */
    private function authenticateAgent(Request $request): ?PrintAgent
    {
        $token = $request->bearerToken()
                 ?? $request->header('X-Agent-Key')
                 ?? $request->query('key');

        if (! $token) return null;

        $agent = PrintAgent::findByKey($token);
        if ($agent && $agent->is_active) {
            // ── IP Whitelist Enforcement (Task 4.1) ────────────
            if (!empty($agent->allowed_ips)) {
                $requestIp = $request->ip();
                $allowed = false;
                $ipList = is_array($agent->allowed_ips) ? $agent->allowed_ips : explode(',', $agent->allowed_ips);

                foreach ($ipList as $allowedIp) {
                    $allowedIp = trim($allowedIp);
                    if (str_contains($allowedIp, '/')) {
                        // CIDR notation support
                        if ($this->ipInCidr($requestIp, $allowedIp)) {
                            $allowed = true;
                            break;
                        }
                    } else {
                        // Exact IP match
                        if ($requestIp === $allowedIp) {
                            $allowed = true;
                            break;
                        }
                    }
                }

                if (!$allowed) {
                    return null; // Will result in 403 from caller
                }
            }

            $agent->update([
                'last_seen_at' => now(),
                'ip_address'   => $request->ip(),
            ]);
        }
        return $agent;
    }

    /**
     * Check if an IP address is within a CIDR range.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return $ip === $parts[0];
        }

        $subnetIp = $parts[0];
        $prefix = (int) $parts[1];

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnetIp);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $prefix);
        $mask = $mask & 0xFFFFFFFF;

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function unauthorized(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::unauthorized('INVALID_AGENT_KEY', 'Provide a valid agent Bearer token.');
    }

    // -------------------------------------------------------------------------
    // POST /api/print-hub/heartbeat
    // Lightweight heartbeat — just updates last_seen_at.
    // -------------------------------------------------------------------------

    public function heartbeat(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        $agent->update(['last_seen_at' => now()]);

        return ApiResponse::success(['status' => 'ok', 'server_time' => now()->toIso8601String()]);
    }

    // -------------------------------------------------------------------------
    // GET /api/print-hub/profiles
    // Agent pulls its printer profiles.
    // -------------------------------------------------------------------------

    public function getProfiles(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        // Scope profiles to the agent's branch so all agents in the same branch
        // see the same set of profiles. The agent can then override via options.
        $profiles = PrintProfile::where('branch_id', $agent->branch_id)->get()->map(function ($p) {
            $dimensions = PaperSizeService::resolveFromProfile($p);

            return [
                'id'                 => $p->id,
                'name'               => $p->name,
                'description'        => $p->description,
                'printer'            => $p->default_printer ?? '',
                'pool_id'            => $p->pool_id,
                'print_agent_id'     => $p->print_agent_id,
                'branch_id'          => $p->branch_id,
                'paper_size'         => $p->paper_size,
                'paper_width_mm'     => $dimensions['width_mm'],
                'paper_height_mm'    => $dimensions['height_mm'],
                'margin_top'         => $p->margin_top,
                'margin_bottom'      => $p->margin_bottom,
                'margin_left'        => $p->margin_left,
                'margin_right'       => $p->margin_right,
                'fit_to_page'        => is_array($p->extra_options) ? ($p->extra_options['fit_to_page'] ?? false) : false,
                'orientation'        => $p->orientation,
                'copies'             => $p->copies,
                'duplex'             => $p->duplex,
                'tray_source'        => $p->tray_source,
                'color_mode'         => $p->color_mode,
                'print_quality'      => $p->print_quality,
                'scaling_percentage' => $p->scaling_percentage,
                'media_type'         => $p->media_type,
                'collate'            => $p->collate,
                'reverse_order'      => $p->reverse_order,

                // Watermark fields
                'watermark_text'     => $p->watermark_text ?? '',
                'watermark_opacity'  => (float)($p->watermark_opacity ?? 0.3),
                'watermark_rotation' => (int)($p->watermark_rotation ?? -45),
                'watermark_position' => $p->watermark_position ?? 'center',

                // Per-copy watermark configs (array of {text, opacity, rotation, position})
                'watermark_copies' => $p->watermark_copies ?? [],

                // Finishing fields
                'finishing_staple'   => $p->finishing_staple ?? '',
                'finishing_punch'    => $p->finishing_punch ?? '',
                'finishing_booklet'  => (bool)($p->finishing_booklet ?? false),
                'finishing_fold'     => $p->finishing_fold ?? '',
                'finishing_bind'     => $p->finishing_bind ?? '',

                // Eco / sustainability fields
                'eco_mode'           => (bool)($p->eco_mode ?? false),
                'grayscale_force'    => (bool)($p->grayscale_force ?? false),
                'pages_per_sheet'    => (int)($p->pages_per_sheet ?? 1),
                'remove_images'      => (bool)($p->remove_images ?? false),
                'duplex_saved'       => (int)($p->duplex_saved ?? 0),
                'carbon_saved'       => (float)($p->carbon_saved ?? 0),
            ];
        });

        return ApiResponse::success(['profiles' => $profiles]);
    }

    // -------------------------------------------------------------------------
    // GET /api/print-hub/queue
    // Agent pulls its pending jobs.
    // -------------------------------------------------------------------------

    public function getQueue(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        // Reset stale processing jobs for this specific agent (agent may have crashed)
        PrintJob::where('print_agent_id', $agent->id)
            ->where('status', 'processing')
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '<', now()->subMinutes(5))
            ->update(['status' => 'pending', 'dispatched_at' => null]);

        // Exclude jobs pending approval and already leased/dispatched jobs
        $jobs = PrintJob::where('print_agent_id', $agent->id)
            ->whereIn('status', ['pending', 'queued'])
            ->whereNull('dispatched_at')
            ->whereIn('approval_status', ['approved', 'auto_approved'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $threshold = config('app.large_job_threshold_bytes', 5 * 1024 * 1024); // 5MB default

        $queue = $jobs->map(function ($job) use ($threshold) {
            $base64 = null;
            $download_url = null;

            if ($job->file_path && Storage::exists($job->file_path)) {
                $fileSize = Storage::size($job->file_path);

                // For large files, generate a signed temporary download URL instead of base64
                if ($fileSize > $threshold) {
                    $download_url = URL::temporarySignedRoute(
                        'agent.job.download',
                        now()->addMinutes(30),
                        ['job_id' => $job->job_id]
                    );
                } else {
                    $base64 = base64_encode(Storage::get($job->file_path));
                }
            }

            $printerName = $job->printer_name;
            if (!$printerName && $job->pool_id) {
                try {
                    $orchestrator = new \App\Services\PrintJobOrchestrator();
                    $printerName = $orchestrator->selectPrinterFromPool($job->pool_id, $job->print_agent_id);
                    $job->update(['printer_name' => $printerName]);
                } catch (\RuntimeException $e) {
                    Log::error("Failed to select printer from pool {$job->pool_id} for job {$job->id}: " . $e->getMessage());
                    $printerName = 'Default';
                }
            }

            return [
                'job_id'           => $job->job_id,
                'printer'          => $printerName,
                'type'             => $job->type,
                'priority'         => $job->priority,
                'options'          => $job->options,
                'document_base64'  => $base64,
                'download_url'     => $download_url,
                'scheduled_at'     => $job->scheduled_at?->toISOString(),
                'recurrence'       => $job->recurrence,
                'recurrence_end_at'=> $job->recurrence_end_at?->toISOString(),
                'recurrence_count' => $job->recurrence_count,
                'approval_status'  => $job->approval_status,
            ];
        });

        // Mark as Processing and set dispatched_at — agent has acknowledged the jobs
        PrintJob::whereIn('id', $jobs->pluck('id'))->update([
            'status'        => 'processing',
            'dispatched_at' => now(),
        ]);

        return ApiResponse::success(['jobs' => $queue]);
    }

    // -------------------------------------------------------------------------
    // POST /api/print-hub/jobs
    // Agent reports a completed print job.
    // -------------------------------------------------------------------------

    public function reportJob(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        $data = $request->validate([
            'job_id'        => 'required|string',
            'printer'       => 'required|string',
            'type'          => 'required|string',
            'status'        => 'required|string',
            'error'         => 'nullable|string',
            'options'       => 'nullable|array',
            'created_at'    => 'nullable|string',
            'completed_at'  => 'nullable|string',
            'pages_printed' => 'nullable|integer|min:0',
            'is_color'      => 'nullable|boolean',
        ]);

        $job = PrintJob::where('job_id', $data['job_id'])->first();

        if ($job) {
            $job->update([
                'status'              => $data['status'],
                'error'               => $data['error'] ?? null,
                'agent_created_at'    => $data['created_at'] ?? null,
                'agent_completed_at'  => $data['completed_at'] ?? null,
            ]);

            // Fire webhook via WebhookService
            try {
                $eventType = $job->status === 'success' ? 'job.completed' : 'job.failed';
                app(\App\Services\WebhookService::class)->dispatch($eventType, [
                    'reference_id' => $job->reference_id,
                    'job_id'       => $job->job_id,
                    'status'       => $job->status,
                    'error'        => $job->error,
                    'printer'      => $job->printer_name,
                ]);
            } catch (\Exception $e) {
                Log::warning('WebhookService dispatch failed: ' . $e->getMessage(), [
                    'job_id' => $job->job_id,
                ]);
            }
        } else {
            // Unregistered job (local/direct print), create for historical records
            $job = PrintJob::create([
                'job_id'              => $data['job_id'],
                'print_agent_id'      => $agent->id,
                'printer_name'        => $data['printer'],
                'type'                => $data['type'],
                'status'              => $data['status'],
                'error'               => $data['error'] ?? null,
                'options'             => $data['options'] ?? null,
                'agent_created_at'    => $data['created_at'] ?? null,
                'agent_completed_at'  => $data['completed_at'] ?? null,
            ]);
        }

        // ── Feature 2.3: Track printer failures ──────────────
        if ($data['status'] === 'failed') {
            try {
                $printerName = $data['printer'] ?? $job->printer_name;
                $poolPrinters = PrinterPoolPrinter::where('printer_name', $printerName)->get();

                foreach ($poolPrinters as $pp) {
                    $pp->increment('failure_count');
                    $pp->update([
                        'last_error_at'      => now(),
                        'last_error_message' => $data['error'] ?? 'Unknown failure',
                    ]);

                    // Mark unhealthy if failure_count >= 3
                    if ($pp->failure_count >= 3) {
                        $pp->update(['is_healthy' => false]);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Failed to track printer failure: ' . $e->getMessage());
            }
        }

        // ── Feature 1.3: Record costs on successful completion ─
        if ($data['status'] === 'success') {
            try {
                $pagesPrinted = (int) ($data['pages_printed'] ?? $job->options['pages_printed'] ?? 0);
                $isColor      = (bool) ($data['is_color'] ?? $job->options['is_color'] ?? false);

                if ($pagesPrinted > 0) {
                    $branchId = $job->branch_id ?? $agent->branch_id;
                    $branch   = $branchId ? Branch::find($branchId) : null;

                    if ($branch) {
                        $costPerPage = $isColor ? $branch->color_cost_per_page : $branch->bw_cost_per_page;
                    } else {
                        // Fallback defaults
                        $costPerPage = $isColor ? 0.25 : 0.05;
                    }

                    $totalCost = round($pagesPrinted * $costPerPage, 2);

                    PrintCost::create([
                        'print_job_id'   => $job->id,
                        'branch_id'      => $branchId,
                        'print_agent_id' => $agent->id,
                        'pages_printed'  => $pagesPrinted,
                        'is_color'       => $isColor,
                        'cost_per_page'  => $costPerPage,
                        'total_cost'     => $totalCost,
                        'currency'       => $branch?->currency ?? 'IDR',
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to record print cost: ' . $e->getMessage());
            }
        }

        $jobToBroadcast = $job ?? PrintJob::where('job_id', $data['job_id'])->first();
        if ($jobToBroadcast) {
            event(new \App\Events\JobStatusUpdated($jobToBroadcast));
        }

        // Dispatch QueueUpdated for admin dashboard
        try {
            $queueData = [
                'total_pending'    => PrintJob::where('status', 'pending')->count(),
                'total_processing' => PrintJob::where('status', 'processing')->count(),
                'total_queued'     => PrintJob::whereIn('status', ['pending', 'processing'])->count(),
            ];
            event(new \App\Events\QueueUpdated($queueData));
        } catch (\Exception $e) {
            Log::warning('Failed to dispatch QueueUpdated: ' . $e->getMessage());
        }

        return ApiResponse::success(['status' => 'received']);
    }

    // -------------------------------------------------------------------------
    // POST /api/print-hub/status
    // Agent reports its status (printers list, etc.)
    // -------------------------------------------------------------------------

    public function updateStatus(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        $data = $request->validate([
            'printers'      => 'required|array',
            'printers.*'    => 'required|string',
            'capabilities'  => 'nullable|array',
        ]);

        $wasOnline = $agent->isOnline();

        $updateData = [
            'printers'     => $data['printers'],
            'last_seen_at' => now(),
        ];

        // Store capabilities as JSON if provided
        if ($request->has('capabilities')) {
            $updateData['capabilities'] = $data['capabilities'];
        }

        $agent->update($updateData);

        // Dispatch AgentStatusUpdated if status changed (online → offline or offline → online)
        $agent->refresh();
        if ($wasOnline !== $agent->isOnline()) {
            event(new \App\Events\AgentStatusUpdated($agent));
        }

        return ApiResponse::success(['status' => 'ok']);
    }

    // -------------------------------------------------------------------------
    // GET /api/print-hub/jobs/{job_id}/download
    // Signed temporary download URL for large job files (streaming).
    // -------------------------------------------------------------------------

    public function downloadJob(Request $request, string $jobId)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        if (! $request->hasValidSignature()) {
            return ApiResponse::error('INVALID_SIGNATURE', 'Download link expired or invalid.', 410);
        }

        $job = PrintJob::where('job_id', $jobId)
            ->where('print_agent_id', $agent->id)
            ->first();

        if (! $job || ! $job->file_path || ! Storage::exists($job->file_path)) {
            return ApiResponse::error('FILE_NOT_FOUND', 'Job file not found.', 404);
        }

        $filePath = Storage::path($job->file_path);
        $fileName = basename($job->file_path);

        return new StreamedResponse(function () use ($filePath) {
            $handle = fopen($filePath, 'rb');
            if ($handle) {
                fpassthru($handle);
                fclose($handle);
            }
        }, 200, [
            'Content-Type'        => Storage::mimeType($job->file_path) ?? 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Content-Length'      => (string) Storage::size($job->file_path),
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/print-hub/cors-origins
    // Agent pulls allowed CORS origins for its local configuration.
    // -------------------------------------------------------------------------

    public function getCorsOrigins(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        $apps    = \App\Models\ClientApp::where('is_active', true)->get();
        $origins = [];

        foreach ($apps as $app) {
            if (is_array($app->allowed_origins)) {
                foreach ($app->allowed_origins as $origin) {
                    $origin = trim($origin);
                    if ($origin) {
                        $origins[] = $origin;
                    }
                }
            }
        }

        // Always permit typical local dev origins
        $origins[] = 'http://127.0.0.1:*';
        $origins[] = 'http://localhost:*';

        return ApiResponse::success([
            'allowed_origins' => array_values(array_unique($origins)),
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/agents/version
    // GET /api/print-hub/agent/version
    // Returns the latest available agent version for auto-update.
    // -------------------------------------------------------------------------

    public function getAgentVersion(Request $request)
    {
        $latestVersion = config('app.agent_latest_version', '1.0.0');
        $downloadUrl   = config('app.agent_download_url', '');
        $releaseNotes  = config('app.agent_release_notes', '');
        $sha256        = config('app.agent_sha256', '');
        $mandatory     = config('app.agent_mandatory', false);

        return ApiResponse::success([
            'latest_version' => $latestVersion,
            'download_url'   => $downloadUrl,
            'release_notes'  => $releaseNotes,
            'sha256'         => $sha256,
            'mandatory'      => (bool) $mandatory,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/print-hub/diagnostics/crash
    // Receives crash diagnostics reports from TrayPrint agents.
    // -------------------------------------------------------------------------

    public function reportCrash(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (! $agent) return $this->unauthorized();

        $data = $request->validate([
            'event'          => 'required|string',
            'agent_version'  => 'required|string',
            'platform'       => 'required|string',
            'exception'      => 'required|string',
            'message'        => 'nullable|string',
            'traceback'      => 'nullable|string',
            'timestamp'      => 'required|string',
        ]);

        // Log the crash report
        \Illuminate\Support\Facades\Log::error('Agent crash report received', [
            'agent_id'    => $agent->id,
            'agent_name'  => $agent->name,
            'version'     => $data['agent_version'],
            'platform'    => $data['platform'],
            'exception'   => $data['exception'],
            'message'     => $data['message'] ?? '',
            'timestamp'   => $data['timestamp'],
        ]);

        // Store crash report in a log file for admin review
        try {
            $logLine = sprintf(
                "[%s] AGENT #%d (%s) v%s on %s — %s: %s\n%s\n---\n",
                $data['timestamp'],
                $agent->id,
                $agent->name,
                $data['agent_version'],
                $data['platform'],
                $data['exception'],
                $data['message'] ?? '',
                $data['traceback'] ?? ''
            );
            \Illuminate\Support\Facades\Storage::append('crash-reports.log', $logLine);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to persist crash report: ' . $e->getMessage());
        }

        return ApiResponse::success(['status' => 'crash_received']);
    }
}
