<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintJob::with('agent')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('agent_id')) {
            $query->where('print_agent_id', $request->agent_id);
        }

        $jobs = $query->paginate(50);
        $agents = PrintAgent::all();
        return view('admin.jobs', compact('jobs', 'agents'));
    }

    public function download(PrintJob $job)
    {
        if (!$job->file_path || !\Illuminate\Support\Facades\Storage::exists($job->file_path)) {
            abort(404, 'Document not found or deleted.');
        }

        // Sanitize path: ensure file_path is within the storage directory
        $filePath = $job->file_path;
        $fullPath = storage_path('app/private/' . $filePath);
        $realBase = realpath(storage_path('app/private'));
        $realFile = realpath($fullPath);

        if (!$realFile || !str_starts_with($realFile, $realBase)) {
            abort(404, 'Document not found or deleted.');
        }

        return response()->file($realFile);
    }

    public function updateStatus(Request $request, PrintJob $job)
    {
        $data = $request->validate([
            'status' => 'required|in:success,failed,processing,pending',
        ]);

        $job->update(['status' => $data['status']]);

        return redirect()->back()->with('success', "Job status updated to {$data['status']}");
    }

    public function retry(PrintJob $job)
    {
        $newJob = $job->replicate();
        $newJob->job_id = (string) Str::uuid();
        $newJob->status = 'pending';
        $newJob->error = null;
        $newJob->agent_created_at = null;
        $newJob->agent_completed_at = null;
        $newJob->created_at = now();
        $newJob->updated_at = now();

        if ($job->file_path && \Illuminate\Support\Facades\Storage::exists($job->file_path)) {
            $ext = pathinfo($job->file_path, PATHINFO_EXTENSION);
            $newJob->file_path = "print_jobs/{$newJob->job_id}.{$ext}";
            \Illuminate\Support\Facades\Storage::copy($job->file_path, $newJob->file_path);
        }

        $newJob->save();

        return redirect()->back()->with('success', 'Job retried! New Job ID: ' . $newJob->job_id);
    }
}
