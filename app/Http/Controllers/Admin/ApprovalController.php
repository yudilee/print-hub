<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function index()
    {
        $pendingJobs = PrintJob::with('agent:id,name')
            ->where('approval_status', 'pending')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('admin.approvals.index', compact('pendingJobs'));
    }

    public function approve($id)
    {
        $job = PrintJob::where('approval_status', 'pending')->findOrFail($id);

        $job->update([
            'approval_status' => 'approved',
            'approved_by'     => Auth::id(),
            'approved_at'     => now(),
            'status'          => 'pending',
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

        return redirect()->route('admin.approvals')->with('success', "Job {$job->job_id} approved.");
    }

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

        return redirect()->route('admin.approvals')->with('success', "Job {$job->job_id} rejected.");
    }
}
