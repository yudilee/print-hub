<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PrintAgent;
use App\Models\PrintCost;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CostController extends Controller
{
    /**
     * Display the cost tracking dashboard.
     */
    public function index(Request $request)
    {
        $now = now();

        // Date range filter
        $startDate = $request->get('start_date', $now->copy()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', $now->copy()->toDateString());

        $costsQuery = PrintCost::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59']);

        // ── Summary Cards ────────────────────────────────────
        $totalCost      = (clone $costsQuery)->sum('total_cost');
        $totalJobs      = (clone $costsQuery)->count();
        $totalPages     = (clone $costsQuery)->sum('pages_printed');
        $avgCostPerJob  = $totalJobs > 0 ? round($totalCost / $totalJobs, 2) : 0;
        $colorJobs      = (clone $costsQuery)->where('is_color', true)->count();
        $bwJobs         = $totalJobs - $colorJobs;

        // Cost by branch — load branches manually for aggregated results
        $costByBranchRaw = (clone $costsQuery)
            ->select('branch_id', DB::raw('SUM(total_cost) as total'), DB::raw('COUNT(*) as job_count'))
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->get();

        $branchesMap = Branch::whereIn('id', $costByBranchRaw->pluck('branch_id'))->get()->keyBy('id');
        $costByBranch = $costByBranchRaw->map(function ($item) use ($branchesMap) {
            $item->branch = $branchesMap->get($item->branch_id);
            return $item;
        });

        // ── Monthly Trend (last 12 months) ───────────────────
        $monthlyTrend = PrintCost::select(
                DB::raw("TO_CHAR(created_at, 'YYYY-MM') as month"),
                DB::raw('SUM(total_cost) as total'),
                DB::raw('COUNT(*) as job_count'),
                DB::raw('SUM(pages_printed) as pages')
            )
            ->where('created_at', '>=', $now->copy()->subMonths(12)->startOfMonth())
            ->groupBy(DB::raw("TO_CHAR(created_at, 'YYYY-MM')"))
            ->orderBy('month')
            ->get();

        // ── Top Spending Branches ────────────────────────────
        $topBranchesRaw = (clone $costsQuery)
            ->select('branch_id', DB::raw('SUM(total_cost) as total'), DB::raw('COUNT(*) as job_count'))
            ->whereNotNull('branch_id')
            ->groupBy('branch_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $topBranches = $topBranchesRaw->map(function ($item) use ($branchesMap) {
            $item->branch = $branchesMap->get($item->branch_id);
            return $item;
        });

        // ── Top Spending Agents ──────────────────────────────
        $topAgentsRaw = (clone $costsQuery)
            ->select('print_agent_id', DB::raw('SUM(total_cost) as total'), DB::raw('COUNT(*) as job_count'))
            ->whereNotNull('print_agent_id')
            ->groupBy('print_agent_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $agentsMap = PrintAgent::whereIn('id', $topAgentsRaw->pluck('print_agent_id'))->get()->keyBy('id');
        $topAgents = $topAgentsRaw->map(function ($item) use ($agentsMap) {
            $item->printAgent = $agentsMap->get($item->print_agent_id);
            return $item;
        });

        // ── Recent Costs (data table) ────────────────────────
        $recentCosts = (clone $costsQuery)
            ->with(['printJob', 'branch', 'printAgent'])
            ->latest()
            ->paginate(25);

        // Branches list for filter
        $branches = Branch::orderBy('name')->get();

        return view('admin.costs.index', compact(
            'totalCost',
            'totalJobs',
            'totalPages',
            'avgCostPerJob',
            'colorJobs',
            'bwJobs',
            'costByBranch',
            'monthlyTrend',
            'topBranches',
            'topAgents',
            'recentCosts',
            'branches',
            'startDate',
            'endDate',
        ));
    }

    /**
     * Export filtered cost data as CSV.
     */
    public function exportCsv(Request $request)
    {
        $now = now();

        $startDate = $request->get('start_date', $now->copy()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', $now->copy()->toDateString());

        $costs = PrintCost::whereBetween('created_at', [$startDate, $endDate . ' 23:59:59'])
            ->with(['printJob', 'branch', 'printAgent'])
            ->latest()
            ->get();

        $filename = "print-costs-{$startDate}-{$endDate}.csv";

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($costs) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header row
            fputcsv($handle, [
                'Date',
                'Job ID',
                'Branch',
                'Agent',
                'Printer',
                'Pages',
                'Color',
                'Cost/Page',
                'Total Cost',
                'Currency',
            ]);

            foreach ($costs as $cost) {
                fputcsv($handle, [
                    $cost->created_at?->toDateString(),
                    $cost->printJob?->job_id,
                    $cost->branch?->name ?? 'N/A',
                    $cost->printAgent?->name ?? 'N/A',
                    $cost->printJob?->printer_name ?? 'N/A',
                    $cost->pages_printed,
                    $cost->is_color ? 'Yes' : 'No',
                    $cost->cost_per_page,
                    $cost->total_cost,
                    $cost->currency,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
