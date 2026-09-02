<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = PrintJob::with('agent.branch')
            ->with('dependsOn')
            ->withCount('dependents');

        // ── Status filter ────────────────────────────────────
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ── Agent filter ─────────────────────────────────────
        if ($request->filled('agent_id')) {
            $query->where('print_agent_id', $request->agent_id);
        }

        // ── Priority filter ──────────────────────────────────
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // ── Branch filter ────────────────────────────────────
        if ($request->filled('branch_id')) {
            $query->whereHas('agent', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }

        // ── Date range filter ────────────────────────────────
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // ── Scheduled filter ─────────────────────────────────
        if ($request->filled('scheduled_filter')) {
            if ($request->scheduled_filter === 'scheduled') {
                $query->whereNotNull('scheduled_at');
            } elseif ($request->scheduled_filter === 'recurring') {
                $query->whereNotNull('recurrence')->where('recurrence', '!=', 'none');
            }
        }

        // ── Job ID search ────────────────────────────────────
        if ($request->filled('job_id')) {
            $query->where('job_id', $request->job_id);
        }

        // ── Sort order (Task 4.3) ────────────────────────────
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');

        $allowedSorts = ['job_id', 'created_at', 'status', 'printer_name', 'type'];
        if (in_array($sortField, $allowedSorts)) {
            if ($sortField === 'agent') {
                $query->orderBy(
                    PrintAgent::select('name')
                        ->whereColumn('print_agents.id', 'print_jobs.print_agent_id')
                        ->limit(1),
                    $sortDirection
                );
            } else {
                $query->orderBy($sortField, $sortDirection);
            }
        } else {
            $query->latest();
        }

        // ── Page size (Task 4.3) ─────────────────────────────
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $jobs = $query->paginate($perPage)->withQueryString();
        $agents = PrintAgent::all();
        $branches = Branch::active()->get();

        return view('admin.jobs', compact('jobs', 'agents', 'branches', 'sortField', 'sortDirection', 'perPage'));
    }

    /**
     * Export filtered jobs as CSV.
     */
    public function exportCsv(Request $request)
    {
        $query = PrintJob::with('agent.branch')->latest();

        // Apply same filters as index()
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('agent_id')) {
            $query->where('print_agent_id', $request->agent_id);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('branch_id')) {
            $query->whereHas('agent', function ($q) use ($request) {
                $q->where('branch_id', $request->branch_id);
            });
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('scheduled_filter')) {
            if ($request->scheduled_filter === 'scheduled') {
                $query->whereNotNull('scheduled_at');
            } elseif ($request->scheduled_filter === 'recurring') {
                $query->whereNotNull('recurrence')->where('recurrence', '!=', 'none');
            }
        }

        $jobs = $query->get();

        $filename = 'jobs-export-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($jobs) {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'ID', 'Template', 'Agent', 'Branch', 'Status',
                'Priority', 'Copies', 'Created At', 'Completed At', 'Error Message',
            ]);
            foreach ($jobs as $job) {
                fputcsv($handle, [
                    $job->job_id,
                    $job->template_name ?? 'N/A',
                    $job->agent->name ?? 'N/A',
                    $job->agent->branch->name ?? 'N/A',
                    $job->status,
                    $job->priority ?? 'N/A',
                    $job->options['copies'] ?? 1,
                    $job->created_at->format('Y-m-d H:i:s'),
                    $job->agent_completed_at?->format('Y-m-d H:i:s') ?? 'N/A',
                    $job->error ?? '',
                ]);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
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
        $orchestrator = new \App\Services\PrintJobOrchestrator();
        $newJob = $orchestrator->retryJob($job, 'Admin manual retry');

        return redirect()->back()->with('success', 'Job retried! New Job ID: ' . $newJob->job_id);
    }

    public function reprint(PrintJob $job)
    {
        $orchestrator = new \App\Services\PrintJobOrchestrator();
        $newJob = $orchestrator->retryJob($job, 'Admin manual reprint');

        return redirect()->back()->with('success', 'Job reprint queued! New Job ID: ' . $newJob->job_id);
    }

    /**
     * Retry all failed jobs by resetting their status to 'pending' and clearing errors.
     */
    public function retryAllFailed()
    {
        $failedJobs = PrintJob::where('status', 'failed')->get();
        $count = 0;

        foreach ($failedJobs as $job) {
            $newJob = $job->replicate();
            $newJob->job_id = (string) Str::uuid();
            $newJob->status = 'pending';
            $newJob->dispatched_at = null;
            $newJob->retried_from_job_id = $job->id;
            $newJob->retry_count = $job->retry_count + 1;
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
            $count++;
        }

        return redirect()->back()->with('success', "{$count} failed job(s) have been re-queued for retry.");
    }

    /**
     * Bulk retry selected jobs by job_id (Task 2.3).
     */
    public function bulkRetry(Request $request)
    {
        $data = $request->validate([
            'job_ids'   => 'required|array',
            'job_ids.*' => 'required|string|exists:print_jobs,job_id',
        ]);

        $count = 0;
        foreach ($data['job_ids'] as $jobId) {
            $job = PrintJob::where('job_id', $jobId)->first();
            if (!$job) continue;

            $newJob = $job->replicate();
            $newJob->job_id = (string) Str::uuid();
            $newJob->status = 'pending';
            $newJob->dispatched_at = null;
            $newJob->retried_from_job_id = $job->id;
            $newJob->retry_count = $job->retry_count + 1;
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
            $count++;
        }

        return redirect()->back()->with('success', "{$count} selected job(s) have been re-queued for retry.");
    }
    // ──────────────────────────────────────────────────────────
    //  Dependency UI Support (Item 13.2)
    // ──────────────────────────────────────────────────────────

    /**
     * Show detailed dependency information for a specific job.
     *
     * Returns a JSON response with the dependency chain (parents and children)
     * for use in the modal detail view.
     */
    public function dependencies(PrintJob $job)
    {
        $job->load('dependsOn', 'dependents');

        // Build parent chain (walk up the dependency tree)
        $parentChain = [];
        $current = $job->dependsOn;
        $visited = [$job->job_id];
        while ($current) {
            if (in_array($current->job_id, $visited, true)) {
                $parentChain[] = [
                    'job_id' => $current->job_id,
                    'status' => 'circular',
                    'label'  => '[Circular] ' . $current->job_id,
                ];
                break;
            }
            $visited[] = $current->job_id;
            $parentChain[] = [
                'job_id' => $current->job_id,
                'status' => $current->status,
                'label'  => $current->job_id,
            ];
            $current = $current->dependsOn;
        }

        // Build child chain (walk down the dependency tree)
        $childChain = [];
        $queue = $job->dependents->toArray();
        $visitedChildren = [$job->job_id];
        while (!empty($queue)) {
            $child = array_shift($queue);
            if (in_array($child['job_id'], $visitedChildren, true)) {
                $childChain[] = [
                    'job_id' => $child['job_id'],
                    'status' => 'circular',
                    'label'  => '[Circular] ' . $child['job_id'],
                ];
                break;
            }
            $visitedChildren[] = $child['job_id'];
            $childChain[] = [
                'job_id' => $child['job_id'],
                'status' => $child['status'],
                'label'  => $child['job_id'],
            ];
            // Load grandchildren
            $grandchildren = PrintJob::where('depends_on_job_id', $child['job_id'])->get(['job_id', 'status', 'depends_on_job_id']);
            foreach ($grandchildren as $gc) {
                $queue[] = $gc->toArray();
            }
        }

        return response()->json([
            'success'      => true,
            'job'          => [
                'job_id'          => $job->job_id,
                'status'          => $job->status,
                'depends_on_job_id' => $job->depends_on_job_id,
                'dependency_type' => $job->dependency_type,
            ],
            'parent_chain' => $parentChain,
            'child_chain'  => $childChain,
            'dependents_count' => $job->dependents->count(),
        ]);
    }

    /**
     * Search for jobs to use as parent dependencies (for the dropdown).
     *
     * Searches by job_id or template_name. Excludes the given job and its
     * descendants to prevent circular dependencies.
     */
    public function searchParentJobs(Request $request)
    {
        $query = $request->get('q', '');
        $excludeJobId = $request->get('exclude_job_id');

        $jobs = PrintJob::where(function ($q) use ($query) {
            $q->where('job_id', 'LIKE', "%{$query}%")
              ->orWhere('template_name', 'LIKE', "%{$query}%");
        });

        // Exclude the current job to prevent self-reference
        if ($excludeJobId) {
            $jobs->where('job_id', '!=', $excludeJobId);
        }

        // Exclude descendants to prevent circular dependencies
        if ($excludeJobId) {
            $descendantIds = $this->getDescendantIds($excludeJobId);
            if (!empty($descendantIds)) {
                $jobs->whereNotIn('job_id', $descendantIds);
            }
        }

        $results = $jobs->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['job_id', 'template_name', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'results' => $results->map(function ($job) {
                return [
                    'id'            => $job->job_id,
                    'text'          => "{$job->job_id} — {$job->template_name} ({$job->status})",
                    'template_name' => $job->template_name,
                    'status'        => $job->status,
                ];
            }),
        ]);
    }

    /**
     * Validate that setting a dependency would not create a circular reference.
     */
    public function validateDependency(Request $request)
    {
        $data = $request->validate([
            'job_id'          => 'required|string',
            'depends_on_job_id' => 'required|string|different:job_id',
        ]);

        $jobId = $data['job_id'];
        $dependsOnId = $data['depends_on_job_id'];

        // Check if the proposed parent already depends (directly or indirectly) on the child
        $descendantIds = $this->getDescendantIds($dependsOnId);

        if (in_array($jobId, $descendantIds, true)) {
            return response()->json([
                'valid'  => false,
                'error'  => 'Circular dependency detected: the proposed parent job already depends on this job (directly or indirectly).',
            ]);
        }

        return response()->json([
            'valid' => true,
        ]);
    }

    /**
     * Update a job's dependency settings.
     */
    public function updateDependency(Request $request, PrintJob $job)
    {
        $data = $request->validate([
            'depends_on_job_id' => 'nullable|string|exists:print_jobs,job_id|different:job_id',
            'dependency_type'   => 'required_with:depends_on_job_id|in:after,after_success,after_failure',
        ]);

        // Validate no circular dependency
        if (!empty($data['depends_on_job_id'])) {
            $descendantIds = $this->getDescendantIds($data['depends_on_job_id']);
            if (in_array($job->job_id, $descendantIds, true)) {
                return redirect()->back()->withErrors([
                    'depends_on_job_id' => 'Circular dependency detected. Cannot set this dependency.',
                ]);
            }
        }

        $job->update([
            'depends_on_job_id' => $data['depends_on_job_id'] ?? null,
            'dependency_type'   => $data['depends_on_job_id'] ? ($data['dependency_type'] ?? 'after') : null,
        ]);

        return redirect()->back()->with('success', 'Job dependency updated successfully.');
    }

    /**
     * Get all descendant job IDs for a given job (recursive).
     *
     * @return string[]
     */
    private function getDescendantIds(string $jobId): array
    {
        $ids = [];
        $children = PrintJob::where('depends_on_job_id', $jobId)->pluck('job_id')->toArray();

        foreach ($children as $childId) {
            $ids[] = $childId;
            $grandchildren = $this->getDescendantIds($childId);
            $ids = array_merge($ids, $grandchildren);
        }

        return array_unique($ids);
    }

    // ──────────────────────────────────────────────────────────
    //  Print Preview (Task 4)
    // ──────────────────────────────────────────────────────────

    /**
     * Preview a print job's PDF content.
     *
     * If the job has a stored file_path, return the PDF directly.
     * Otherwise, attempt to generate a preview using the template
     * engine and the stored template_data.
     */
    public function preview(PrintJob $job)
    {
        // If file exists on disk, serve it directly
        if ($job->file_path && \Illuminate\Support\Facades\Storage::exists($job->file_path)) {
            $fullPath = storage_path('app/private/' . $job->file_path);
            $realBase = realpath(storage_path('app/private'));
            $realFile = realpath($fullPath);

            if ($realFile && str_starts_with($realFile, $realBase)) {
                return response()->file($realFile, [
                    'Content-Type' => 'application/pdf',
                ]);
            }
        }

        // Try to generate a preview using the template + data
        if ($job->template_name && !empty($job->template_data)) {
            $template = \App\Models\PrintTemplate::where('name', $job->template_name)->first();
            if ($template) {
                try {
                    $engine = app(\App\Services\ContinuousFormEngine::class);
                    $pdf = $engine->generate($template, $job->template_data ?? [], $job->options ?? []);
                    return response($pdf, 200, [
                        'Content-Type' => 'application/pdf',
                    ]);
                } catch (\Exception $e) {
                    abort(500, 'Preview generation failed: ' . $e->getMessage());
                }
            }
        }

        abort(404, 'No preview available for this job.');
    }
}
