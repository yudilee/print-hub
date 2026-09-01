@extends('admin.layout')
@section('title', 'Cost Tracking')

@section('content')
<x-breadcrumb :items="[['label' => 'Cost Analytics']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Cost & Consumption Telemetry</h2>
        <p class="text-xs text-slate-400">Financial breakdown of paper, ink, and printer utilization across facilities</p>
    </div>
    <a href="{{ route('admin.costs.export', request()->only(['start_date', 'end_date'])) }}" class="btn-primary btn-sm">
        <x-icon name="download" size="13" />
        <span>Export Cost CSV</span>
    </a>
</div>

{{-- Date Filter Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xs">
    <form method="GET" action="{{ route('admin.costs') }}" class="flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400">Date Range:</span>
            <input type="date" name="start_date" value="{{ $startDate }}" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200">
            <span class="text-xs text-slate-500">to</span>
            <input type="date" name="end_date" value="{{ $endDate }}" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200">
        </div>
        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.costs') }}" class="btn-secondary btn-sm">Reset</a>
    </form>
</div>

{{-- Summary Metric Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Expense</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-blue-400">Rp {{ number_format($totalCost, 0, ',', '.') }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Filtered Period</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Jobs Run</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-slate-200">{{ number_format($totalJobs) }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Completed Dispatch</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Avg Cost / Job</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-emerald-400">Rp {{ number_format($avgCostPerJob, 0, ',', '.') }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Efficiency Metric</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Pages Output</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-amber-400">{{ number_format($totalPages) }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Physical sheets consumed</span>
    </div>
</div>

{{-- Top Spending Entities --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Top Spending Branches</h3>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-5 py-3">Branch</th>
                    <th class="px-5 py-3">Jobs</th>
                    <th class="px-5 py-3 text-right">Total Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($topBranches as $b)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-5 py-3 font-semibold text-white">{{ $b->branch?->name ?? 'Unassigned' }}</td>
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono">{{ number_format($b->job_count) }}</td>
                    <td class="px-5 py-3 text-xs font-mono font-bold text-blue-400 text-right">Rp {{ number_format($b->total, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-4 text-center text-slate-500 text-xs">No records available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Top Spending Workstations</h3>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-5 py-3">Agent</th>
                    <th class="px-5 py-3">Jobs</th>
                    <th class="px-5 py-3 text-right">Total Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($topAgents as $a)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-5 py-3 font-semibold text-white">{{ $a->printAgent?->name ?? 'Unassigned' }}</td>
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono">{{ number_format($a->job_count) }}</td>
                    <td class="px-5 py-3 text-xs font-mono font-bold text-emerald-400 text-right">Rp {{ number_format($a->total, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-4 text-center text-slate-500 text-xs">No records available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Recent Cost Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Recent Cost Line Items</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Timestamp</th>
                    <th class="px-5 py-3.5">Job Ref</th>
                    <th class="px-5 py-3.5">Branch / Agent</th>
                    <th class="px-5 py-3.5">Pages</th>
                    <th class="px-5 py-3.5">Mode</th>
                    <th class="px-5 py-3.5 text-right">Line Cost</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($recentCosts as $cost)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">{{ $cost->created_at?->format('d M Y H:i') }}</td>
                    <td class="px-5 py-3.5 font-mono text-xs text-blue-400">{{ $cost->printJob?->job_id ?? 'N/A' }}</td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="text-white">{{ $cost->branch?->name ?? '—' }}</span>
                        <span class="block text-[10px] text-slate-500">{{ $cost->printAgent?->name ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs font-mono">{{ number_format($cost->pages_printed) }}</td>
                    <td class="px-5 py-3.5">
                        <span class="badge {{ $cost->is_color ? 'badge-warning' : 'badge-info' }} text-[10px]">
                            {{ $cost->is_color ? 'Color' : 'Monochrome' }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs font-mono font-bold text-white text-right">Rp {{ number_format($cost->total_cost, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="💰" title="No cost line items recorded" description="Print jobs will calculate paper and ink costs automatically." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($recentCosts->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $recentCosts->appends(request()->only(['start_date', 'end_date']))->links() }}
    </div>
    @endif
</div>
@endsection
