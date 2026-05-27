<?php

namespace App\Http\Controllers;

use App\Models\PrintAgent;
use App\Models\PrintJob;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Prometheus Metrics Endpoint (Task 3.5)
 *
 * Provides a /metrics endpoint returning Prometheus-format text for monitoring
 * Print Hub operational metrics such as job counts, agent status, and queue depth.
 */
class MetricsController extends Controller
{
    public function index(): Response
    {
        $metrics = [];

        // ── print_hub_jobs_total{status,agent,branch} ──────────────────
        $jobsByStatus = PrintJob::selectRaw("status, COUNT(*) as count")
            ->groupBy('status')
            ->pluck('count', 'status');

        foreach ($jobsByStatus as $status => $count) {
            $metrics[] = 'print_hub_jobs_total{status="' . $status . '"} ' . $count;
        }

        // Jobs by agent
        $jobsByAgent = PrintJob::selectRaw("print_agent_id, COUNT(*) as count")
            ->whereNotNull('print_agent_id')
            ->groupBy('print_agent_id')
            ->with('agent:id,name')
            ->get()
            ->groupBy(fn($j) => $j->agent?->name ?? 'unknown');

        foreach ($jobsByAgent as $agentName => $jobs) {
            $total = $jobs->sum('count');
            $metrics[] = 'print_hub_jobs_total{agent="' . str_replace('"', '\\"', $agentName) . '"} ' . $total;
        }

        // Jobs by branch
        $jobsByBranch = PrintJob::selectRaw("branch_id, COUNT(*) as count")
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->get();
        // If branch_id is numeric, we use it directly
        foreach ($jobsByBranch as $row) {
            $metrics[] = 'print_hub_jobs_total{branch="' . $row->branch_id . '"} ' . $row->count;
        }

        // ── print_hub_agents_online ────────────────────────────────────
        $totalAgents = PrintAgent::count();
        $onlineAgents = PrintAgent::all()->filter(fn($a) => $a->isOnline())->count();
        $metrics[] = 'print_hub_agents_total ' . $totalAgents;
        $metrics[] = 'print_hub_agents_online ' . $onlineAgents;

        // ── print_hub_queue_depth ──────────────────────────────────────
        $queueDepth = PrintJob::whereIn('status', ['pending', 'queued'])->count();
        $metrics[] = 'print_hub_queue_depth ' . $queueDepth;

        // ── print_hub_jobs_processed_total ────────────────────────────
        $processedTotal = PrintJob::count();
        $metrics[] = 'print_hub_jobs_processed_total ' . $processedTotal;

        // ── print_hub_jobs_failed_total ───────────────────────────────
        $failedTotal = PrintJob::where('status', 'failed')->count();
        $metrics[] = 'print_hub_jobs_failed_total ' . $failedTotal;

        // ── print_hub_info (static metadata) ──────────────────────────
        $metrics[] = 'print_hub_info{version="1.0"} 1';

        $output = implode("\n", $metrics) . "\n";

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
