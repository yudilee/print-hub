<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PrintAgent;
use App\Models\PrintProfile;
use App\Models\PrintJob;
use Illuminate\Http\Request;

class PrintHubController extends Controller
{
    /**
     * Authenticate agent by Bearer token (agent_key).
     */
    private function authenticateAgent(Request $request): ?PrintAgent
    {
        $token = $request->bearerToken();
        if (!$token) return null;

        $agent = PrintAgent::where('agent_key', $token)->where('is_active', true)->first();
        if ($agent) {
            $agent->update([
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
            ]);
        }
        return $agent;
    }

    /**
     * GET /api/print-hub/profiles
     * Agent pulls its printer profiles.
     */
    public function getProfiles(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (!$agent) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $profiles = PrintProfile::all()->keyBy('name')->map(function ($p) {
            return [
                'printer' => $p->default_printer ?? '',
                'options' => [
                    'paper_size' => $p->paper_size,
                    'orientation' => $p->orientation,
                    'copies' => $p->copies,
                    'duplex' => $p->duplex,
                ],
                'description' => $p->description,
            ];
        });

        return response()->json(['profiles' => $profiles]);
    }

    /**
     * GET /api/print-hub/queue
     * Agent pulls its pending jobs.
     */
    public function getQueue(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (!$agent) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $jobs = PrintJob::where('print_agent_id', $agent->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->get();

        $queue = $jobs->map(function ($job) {
            $base64 = null;
            if ($job->file_path && \Illuminate\Support\Facades\Storage::exists($job->file_path)) {
                $base64 = base64_encode(\Illuminate\Support\Facades\Storage::get($job->file_path));
            }
            
            return [
                'job_id' => $job->job_id,
                'printer' => $job->printer_name,
                'type' => $job->type,
                'options' => $job->options,
                'document_base64' => $base64,
            ];
        });

        // Mark as Processing
        PrintJob::whereIn('id', $jobs->pluck('id'))->update(['status' => 'processing']);

        return response()->json(['jobs' => $queue]);
    }

    /**
     * POST /api/print-hub/jobs
     * Agent reports a completed print job.
     */
    public function reportJob(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (!$agent) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'job_id' => 'required|string',
            'printer' => 'required|string',
            'type' => 'required|string',
            'status' => 'required|string',
            'error' => 'nullable|string',
            'options' => 'nullable|array',
            'created_at' => 'nullable|string',
            'completed_at' => 'nullable|string',
        ]);

        $job = PrintJob::where('job_id', $data['job_id'])->first();

        if ($job) {
            // Update existing job queued by client app
            $job->update([
                'status' => $data['status'],
                'error' => $data['error'] ?? null,
                'agent_created_at' => $data['created_at'] ?? null,
                'agent_completed_at' => $data['completed_at'] ?? null,
            ]);

            // Fire Webhook if present
            if ($job->webhook_url) {
                try {
                    \Illuminate\Support\Facades\Http::timeout(5)->post($job->webhook_url, [
                        'reference_id' => $job->reference_id,
                        'job_id' => $job->job_id,
                        'status' => $job->status,
                        'error' => $job->error,
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Webhook failed: ' . $e->getMessage());
                }
            }
        } else {
            // Unregistered job (from direct localhost access), create it for historical records
            PrintJob::create([
                'job_id' => $data['job_id'],
                'print_agent_id' => $agent->id,
                'printer_name' => $data['printer'],
                'type' => $data['type'],
                'status' => $data['status'],
                'error' => $data['error'] ?? null,
                'options' => $data['options'] ?? null,
                'agent_created_at' => $data['created_at'] ?? null,
                'agent_completed_at' => $data['completed_at'] ?? null,
            ]);
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * POST /api/print-hub/status
     * Agent reports its status (printers, etc)
     */
    public function updateStatus(Request $request)
    {
        $agent = $this->authenticateAgent($request);
        if (!$agent) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'printers' => 'required|array',
        ]);

        $agent->update([
            'printers' => $data['printers'],
            'last_seen_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }
}
