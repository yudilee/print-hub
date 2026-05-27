<?php

namespace App\Http\Controllers;

use App\Models\Branch;
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

        // ── Agent Uptime Data (Item 3.1) ──────────────────────────────────
        $agentsWithUptime = $agents->map(function ($agent) {
            $agent->uptime_duration = null;
            if ($agent->isOnline() && $agent->last_seen_at) {
                // Uptime is the duration since the agent was first seen
                // We approximate by using the last_seen_at as a proxy for
                // continuous uptime — agents ping regularly when online
                $agent->uptime_duration = $agent->last_seen_at->diffForHumans(now(), true);
            }
            return $agent;
        });

        $totalAgents  = $agents->count();
        $onlineAgents = $agents->filter(fn($a) => $a->isOnline())->count();
        $offlineAgents = $totalAgents - $onlineAgents;

        // ── SLA Breach Data (Item 3.2) ────────────────────────────────────
        // Jobs created > 1 hour ago that are still pending or queued
        $slaBreachJobs = PrintJob::with('agent')
            ->whereIn('status', ['pending', 'queued'])
            ->where('created_at', '<', now()->subHour())
            ->when(! $isSuperAdmin && ! empty($visibleBranches), fn($q) => $q->whereIn('branch_id', $visibleBranches))
            ->orderBy('created_at', 'asc')
            ->take(20)
            ->get();

        // ── Recent Failures (Task 2.7) ────────────────────────────────────
        // Last 5 failed jobs for the Recent Failures widget
        $recentFailures = PrintJob::with('agent')
            ->where('status', 'failed')
            ->when(! $isSuperAdmin && ! empty($visibleBranches), fn($q) => $q->whereIn('branch_id', $visibleBranches))
            ->latest()
            ->take(5)
            ->get();

        // ── Print Reduction Goals (Task 8) ──────────────────────────────────
        $branchGoals = Branch::with('company')
            ->whereNotNull('monthly_page_goal')
            ->where('monthly_page_goal', '>', 0)
            ->get()
            ->map(function ($branch) {
                // Get current month's print job count for this branch
                $currentUsage = PrintJob::where('branch_id', $branch->id)
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->where('status', 'success')
                    ->count();

                $goal = $branch->monthly_page_goal;
                $percentage = $goal > 0 ? round(($currentUsage / $goal) * 100) : 0;
                $daysInMonth = now()->daysInMonth;
                $dayOfMonth = now()->day;
                $expectedProgress = $daysInMonth > 0 ? round(($dayOfMonth / $daysInMonth) * 100) : 0;

                return [
                    'branch'            => $branch,
                    'goal'              => $goal,
                    'current_usage'     => $currentUsage,
                    'percentage'        => $percentage,
                    'expected_progress' => $expectedProgress,
                    'on_track'          => $percentage <= $expectedProgress,
                    'company_name'      => $branch->company?->name ?? '—',
                ];
            });

        $stats = [
            'total_agents'    => $totalAgents,
            'online_agents'   => $onlineAgents,
            'offline_agents'  => $offlineAgents,
            'total_profiles'  => $profiles->count(),
            'total_jobs'      => $totalJobs,
            'failed_jobs'     => $failedJobs,
            'today_jobs'      => $todayJobs,
            'pending_jobs'    => $pendingJobs,
            'processing_jobs' => $processingJobs,
            'success_rate'    => $successRate,
            'jobs_by_status'  => $jobsByStatus,
        ];

        return view('admin.dashboard', compact(
            'agents', 'agentsWithUptime', 'profiles', 'recentJobs', 'stats',
            'totalAgents', 'onlineAgents', 'offlineAgents', 'slaBreachJobs',
            'recentFailures', 'branchGoals'
        ));
    }
}
