<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ApprovalController manages the approval workflow for print jobs.
 *
 * Jobs that match approval rules (by user, role, page count, or cost)
 * require manual approval from an admin/approver before being processed
 * by a print agent.
 */
class ApprovalController extends Controller
{
    // -------------------------------------------------------------------------
    // GET /api/v1/approvals/pending
    // -------------------------------------------------------------------------

    public function pendingJobs(Request $request)
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $jobs = PrintJob::with('agent:id,name')
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $jobs->through(fn($job) => [
            'id'              => $job->id,
            'job_id'          => $job->job_id,
            'status'          => $job->status,
            'printer'         => $job->printer_name,
            'type'            => $job->type,
            'priority'        => $job->priority,
            'reference_id'    => $job->reference_id,
            'template_name'   => $job->template_name,
            'agent'           => $job->agent?->name,
            'approval_status' => $job->approval_status,
            'created_at'      => $job->created_at?->toISOString(),
        ]);

        return ApiResponse::success([
            'jobs' => $jobs->items(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'per_page'     => $jobs->perPage(),
                'total'        => $jobs->total(),
                'last_page'    => $jobs->lastPage(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/approvals/{id}/approve
    // -------------------------------------------------------------------------

    public function approve($id)
    {
        $job = PrintJob::where('approval_status', 'pending')->findOrFail($id);

        $job->update([
            'approval_status' => 'approved',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'status'          => 'pending', // Make available for agent pickup
        ]);

        // Fire webhook event
        try {
            app(\App\Services\WebhookService::class)->dispatch('job.approved', [
                'job_id'       => $job->job_id,
                'reference_id' => $job->reference_id,
            ]);
        } catch (\Exception $e) {
            // Non-critical
        }

        return ApiResponse::success([
            'message' => 'Job approved successfully.',
            'job_id'  => $job->job_id,
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/approvals/{id}/reject
    // -------------------------------------------------------------------------

    public function reject(Request $request, $id)
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $job = PrintJob::where('approval_status', 'pending')->findOrFail($id);

        $job->update([
            'approval_status' => 'rejected',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'rejected_reason' => $data['reason'] ?? null,
            'status'          => 'rejected',
        ]);

        // Fire webhook event
        try {
            app(\App\Services\WebhookService::class)->dispatch('job.rejected', [
                'job_id'       => $job->job_id,
                'reference_id' => $job->reference_id,
                'reason'       => $data['reason'] ?? null,
            ]);
        } catch (\Exception $e) {
            // Non-critical
        }

        return ApiResponse::success([
            'message' => 'Job rejected.',
            'job_id'  => $job->job_id,
        ]);
    }
}
