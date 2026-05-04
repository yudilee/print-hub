<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintAgent;
use App\Models\PrintJob;
use App\Models\PrintProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    /**
     * Display the monitoring dashboard view.
     */
    public function index()
    {
        $now = now();

        // Total print jobs
        $jobsToday  = PrintJob::whereDate('created_at', $now->toDateString())->count();
        $jobsWeek   = PrintJob::whereBetween('created_at', [$now->copy()->startOfWeek(), $now])->count();
        $jobsMonth  = PrintJob::whereBetween('created_at', [$now->copy()->startOfMonth(), $now])->count();

        // Success/failure rates (today)
        $todayJobs = PrintJob::whereDate('created_at', $now->toDateString());
        $totalToday = (clone $todayJobs)->count();
        $successToday = (clone $todayJobs)->where('status', 'success')->count();
        $failedToday  = (clone $todayJobs)->where('status', 'failed')->count();
        $successRate  = $totalToday > 0 ? round(($successToday / $totalToday) * 100, 1) : 0;
        $failureRate  = $totalToday > 0 ? round(($failedToday / $totalToday) * 100, 1) : 0;

        // Active agents count
        $activeAgents = PrintAgent::where('is_active', true)->get()->filter->isOnline()->count();

        // Queue depth
        $queueDepth = PrintJob::whereIn('status', ['pending', 'processing'])->count();

        // Average processing time (last 100 successful jobs)
        $avgProcessingTime = PrintJob::where('status', 'success')
            ->whereNotNull('agent_created_at')
            ->whereNotNull('agent_completed_at')
            ->latest()
            ->limit(100)
            ->get()
            ->avg(function ($job) {
                return $job->agent_created_at && $job->agent_completed_at
                    ? $job->agent_created_at->diffInSeconds($job->agent_completed_at)
                    : 0;
            });

        // Top printers by usage (last 30 days)
        $topPrinters = PrintJob::select('printer_name', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('printer_name')
            ->groupBy('printer_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Top users by print volume (last 30 days)
        $topUsers = PrintJob::select('reference_id', DB::raw('count(*) as total'))
            ->where('created_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('reference_id')
            ->groupBy('reference_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Agent health data
        $agents = PrintAgent::where('is_active', true)->get();
        $onlineCount  = $agents->filter->isOnline()->count();
        $offlineCount = $agents->count() - $onlineCount;

        // Version distribution
        $versionDist = $agents->groupBy(function ($agent) {
            return $agent->capabilities['version'] ?? 'unknown';
        })->map->count();

        // Eco / sustainability metrics
        $totalCarbonSaved = PrintProfile::sum('carbon_saved');
        $ecoProfiles = PrintProfile::where('eco_mode', true)->count();

        return view('admin.monitoring.index', compact(
            'jobsToday',
            'jobsWeek',
            'jobsMonth',
            'successRate',
            'failureRate',
            'successToday',
            'failedToday',
            'activeAgents',
            'queueDepth',
            'avgProcessingTime',
            'topPrinters',
            'topUsers',
            'agents',
            'onlineCount',
            'offlineCount',
            'versionDist',
            'totalCarbonSaved',
            'ecoProfiles',
        ));
    }

    /**
     * Return JSON stats for API/charts.
     * Filters: period (today, 7d, 30d, 90d)
     */
    public function stats(Request $request)
    {
        $period = $request->get('period', 'today');
        $now    = now();

        $dateMap = [
            'today' => [$now->copy()->startOfDay(), $now],
            '7d'    => [$now->copy()->subDays(7), $now],
            '30d'   => [$now->copy()->subDays(30), $now],
            '90d'   => [$now->copy()->subDays(90), $now],
        ];

        [$start, $end] = $dateMap[$period] ?? $dateMap['today'];

        $jobsInPeriod = PrintJob::whereBetween('created_at', [$start, $end]);

        $total    = (clone $jobsInPeriod)->count();
        $success  = (clone $jobsInPeriod)->where('status', 'success')->count();
        $failed   = (clone $jobsInPeriod)->where('status', 'failed')->count();
        $pending  = (clone $jobsInPeriod)->whereIn('status', ['pending', 'processing'])->count();

        $agents = PrintAgent::where('is_active', true)->get();
        $online  = $agents->filter->isOnline()->count();
        $offline = $agents->count() - $online;

        return response()->json([
            'period'        => $period,
            'total_jobs'    => $total,
            'success'       => $success,
            'failed'        => $failed,
            'pending'       => $pending,
            'success_rate'  => $total > 0 ? round(($success / $total) * 100, 1) : 0,
            'failure_rate'  => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
            'active_agents' => $online,
            'offline_agents'=> $offline,
            'queue_depth'   => PrintJob::whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }

    /**
     * Return agent health metrics.
     */
    public function agentHealth()
    {
        $agents = PrintAgent::where('is_active', true)->get();

        $online  = $agents->filter->isOnline()->values();
        $offline = $agents->reject(function ($a) { return $a->isOnline(); })->values();

        $versionDist = $agents->groupBy(function ($agent) {
            return $agent->capabilities['version'] ?? 'unknown';
        })->map(function ($group) {
            return $group->count();
        });

        $healthData = $agents->map(function ($agent) {
            return [
                'id'          => $agent->id,
                'name'        => $agent->name,
                'online'      => $agent->isOnline(),
                'last_seen'   => $agent->last_seen_at?->diffForHumans(),
                'last_seen_at'=> $agent->last_seen_at?->toIso8601String(),
                'version'     => $agent->capabilities['version'] ?? 'unknown',
                'printers'    => $agent->printers ?? [],
                'branch'      => $agent->branch?->name,
            ];
        });

        return response()->json([
            'online_count'      => $online->count(),
            'offline_count'     => $offline->count(),
            'version_distribution' => $versionDist,
            'agents'            => $healthData,
        ]);
    }

    /**
     * Return job creation timeline (grouped by hour) for chart rendering.
     */
    public function jobTimeline(Request $request)
    {
        $period = $request->get('period', '24h');

        $now = now();

        if ($period === '7d') {
            $start = $now->copy()->subDays(7);
            $groupFormat = "Y-m-d";
            $labelFormat = "D";
        } elseif ($period === '30d') {
            $start = $now->copy()->subDays(30);
            $groupFormat = "Y-m-d";
            $labelFormat = "M j";
        } else {
            // Default 24h
            $start = $now->copy()->subHours(24);
            $groupFormat = "Y-m-d H:00";
            $labelFormat = "H:00";
        }

        $jobs = PrintJob::where('created_at', '>=', $start)
            ->selectRaw("DATE_FORMAT(created_at, '{$groupFormat}') as time_group, COUNT(*) as count")
            ->groupBy('time_group')
            ->orderBy('time_group')
            ->get();

        $timeline = $jobs->map(function ($row) use ($start, $now, $groupFormat, $labelFormat) {
            $dt = \DateTime::createFromFormat($groupFormat, $row->time_group);
            return [
                'label' => $dt ? $dt->format($labelFormat) : $row->time_group,
                'count' => (int) $row->count,
            ];
        });

        return response()->json([
            'period'   => $period,
            'timeline' => $timeline,
        ]);
    }
}
