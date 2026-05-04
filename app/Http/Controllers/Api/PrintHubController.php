<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\PrintJob;
use App\Services\PaperSizeService;
use Illuminate\Http\Request;

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
            $agent->update([
                'last_seen_at' => now(),
                'ip_address'   => $request->ip(),
            ]);
        }
        return $agent;
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

        $profiles = PrintProfile::where('print_agent_id', $agent->id)->get()->map(function ($p) {
            $dimensions = PaperSizeService::resolveFromProfile($p);

            return [
                'id'                 => $p->id,
                'name'               => $p->name,
                'description'        => $p->description,
                'printer'            => $p->default_printer ?? '',
                'print_agent_id'     => $p->print_agent_id,
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

        // Reset stale processing jobs (agent may have crashed)
        PrintJob::where('status', 'processing')
            ->where('created_at', '<', now()->subMinutes(10))
            ->update(['status' => 'pending']);

        // Exclude jobs pending approval — only return approved or auto_approved
        $jobs = PrintJob::where('print_agent_id', $agent->id)
            ->where('status', 'pending')
            ->whereIn('approval_status', ['approved', 'auto_approved'])
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $queue = $jobs->map(function ($job) {
            $base64 = null;
            if ($job->file_path && \Illuminate\Support\Facades\Storage::exists($job->file_path)) {
                $base64 = base64_encode(\Illuminate\Support\Facades\Storage::get($job->file_path));
            }

            return [
                'job_id'           => $job->job_id,
                'printer'          => $job->printer_name,
                'type'             => $job->type,
                'priority'         => $job->priority,
                'options'          => $job->options,
                'document_base64'  => $base64,
                'scheduled_at'     => $job->scheduled_at?->toISOString(),
                'recurrence'       => $job->recurrence,
                'recurrence_end_at'=> $job->recurrence_end_at?->toISOString(),
                'recurrence_count' => $job->recurrence_count,
                'approval_status'  => $job->approval_status,
            ];
        });

        // Mark as Processing — agent has acknowledged the jobs
        PrintJob::whereIn('id', $jobs->pluck('id'))->update(['status' => 'processing']);

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
            'job_id'       => 'required|string',
            'printer'      => 'required|string',
            'type'         => 'required|string',
            'status'       => 'required|string',
            'error'        => 'nullable|string',
            'options'      => 'nullable|array',
            'created_at'   => 'nullable|string',
            'completed_at' => 'nullable|string',
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
                \Illuminate\Support\Facades\Log::warning('WebhookService dispatch failed: ' . $e->getMessage(), [
                    'job_id' => $job->job_id,
                ]);
            }
        } else {
            // Unregistered job (local/direct print), create for historical records
            PrintJob::create([
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

        $jobToBroadcast = $job ?? PrintJob::where('job_id', $data['job_id'])->first();
        if ($jobToBroadcast) {
            event(new \App\Events\JobStatusUpdated($jobToBroadcast));
        }

        // Dispatch QueueUpdated for admin dashboard
        try {
            $queueData = [
                'total_pending'    => \App\Models\PrintJob::where('status', 'pending')->count(),
                'total_processing' => \App\Models\PrintJob::where('status', 'processing')->count(),
                'total_queued'     => \App\Models\PrintJob::whereIn('status', ['pending', 'processing'])->count(),
            ];
            event(new \App\Events\QueueUpdated($queueData));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to dispatch QueueUpdated: ' . $e->getMessage());
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
}
