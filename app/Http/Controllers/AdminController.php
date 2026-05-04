<?php

namespace App\Http\Controllers;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\PrintTemplate;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user           = Auth::user();
        $visibleBranches = $user->getVisibleBranchIds();
        $isSuperAdmin   = $user->isSuperAdmin();

        // Scope agents & jobs to the user's visible branches
        $agentsQuery = PrintAgent::withCount('jobs');
        $jobsQuery   = PrintJob::with('agent');

        if (! $isSuperAdmin && ! empty($visibleBranches)) {
            $agentsQuery->whereIn('branch_id', $visibleBranches);
            $jobsQuery->whereIn('branch_id', $visibleBranches);
        }

        $agents     = $agentsQuery->get();
        $profiles   = PrintProfile::all();
        $recentJobs = $jobsQuery->latest()->take(30)->get();

        // Job status breakdown for the mini chart
        $jobsByStatus = PrintJob::query()
            ->when(! $isSuperAdmin && ! empty($visibleBranches), fn($q) => $q->whereIn('branch_id', $visibleBranches))
            ->selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $totalJobs   = array_sum($jobsByStatus);
        $successJobs = $jobsByStatus['success']    ?? 0;
        $failedJobs  = $jobsByStatus['failed']     ?? 0;
        $pendingJobs = $jobsByStatus['pending']    ?? 0;
        $processingJobs = $jobsByStatus['processing'] ?? 0;

        // Jobs created today (scoped)
        $todayJobs = PrintJob::query()
            ->when(! $isSuperAdmin && ! empty($visibleBranches), fn($q) => $q->whereIn('branch_id', $visibleBranches))
            ->whereDate('created_at', today())
            ->count();

        // Success rate (last 100 completed jobs)
        $completed  = $successJobs + $failedJobs;
        $successRate = $completed > 0 ? round(($successJobs / $completed) * 100) : null;

        $stats = [
            'total_agents'    => $agents->count(),
            'online_agents'   => $agents->filter(fn($a) => $a->isOnline())->count(),
            'total_profiles'  => $profiles->count(),
            'total_jobs'      => $totalJobs,
            'failed_jobs'     => $failedJobs,
            'today_jobs'      => $todayJobs,
            'pending_jobs'    => $pendingJobs,
            'processing_jobs' => $processingJobs,
            'success_rate'    => $successRate,
            'jobs_by_status'  => $jobsByStatus,
        ];

        return view('admin.dashboard', compact('agents', 'profiles', 'recentJobs', 'stats'));
    }
}
