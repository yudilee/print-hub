@extends('admin.layout')
@section('title', 'Cost Tracking')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>💰 Cost Tracking</h1>
            <p>Monitor print costs across branches, agents, and time periods.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.costs.export', request()->only(['start_date', 'end_date'])) }}" class="btn btn-secondary">
                ⬇ Export CSV
            </a>
        </div>
    </div>
</div>

{{-- Date Range Filter --}}
<div class="card" style="padding: 1rem;">
    <form method="GET" action="{{ route('admin.costs') }}" style="display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 160px;">
            <label for="start_date">Start Date</label>
            <input type="date" name="start_date" id="start_date" value="{{ $startDate }}">
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 160px;">
            <label for="end_date">End Date</label>
            <input type="date" name="end_date" id="end_date" value="{{ $endDate }}">
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.costs') }}" class="btn btn-secondary">Reset</a>
        </div>
    </form>
</div>

{{-- Summary Stats Grid --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);">Rp {{ number_format($totalCost, 0, ',', '.') }}</div>
        <div class="stat-label">Total Cost (Period)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info);">{{ number_format($totalJobs) }}</div>
        <div class="stat-label">Total Jobs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);">Rp {{ number_format($avgCostPerJob, 0, ',', '.') }}</div>
        <div class="stat-label">Avg Cost / Job</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);">{{ number_format($totalPages) }}</div>
        <div class="stat-label">Total Pages Printed</div>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--primary);">{{ number_format($colorJobs) }}</div>
        <div class="stat-label">Color Jobs</div>
    </div>
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">{{ number_format($bwJobs) }}</div>
        <div class="stat-label">B&W Jobs</div>
    </div>
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">
            Rp {{ number_format($totalJobs > 0 ? $totalCost / $totalPages : 0, 2) }}
        </div>
        <div class="stat-label">Cost Per Page (Avg)</div>
    </div>
</div>

{{-- Monthly Trend Bar Chart (CSS bars) --}}
@if($monthlyTrend->count() > 0)
<div class="card">
    <div class="card-header">
        <h2>📈 Monthly Cost Trend (Last 12 Months)</h2>
    </div>
    @php
        $maxMonthly = max($monthlyTrend->pluck('total')->toArray()) ?: 1;
    @endphp
    <div style="display: flex; align-items: flex-end; gap: 4px; min-height: 220px; padding: 1rem 0; overflow-x: auto;">
        @foreach($monthlyTrend as $trend)
            @php
                $barHeight = max(($trend->total / $maxMonthly) * 200, 4);
                $formatted = 'Rp ' . number_format($trend->total, 0, ',', '.');
            @endphp
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; min-width: 60px;">
                <div style="font-size: 0.65rem; color: var(--text-muted); margin-bottom: 2px; white-space: nowrap;">
                    {{ $formatted }}
                </div>
                <div style="width: 100%; max-width: 50px; height: {{ $barHeight }}px; background: linear-gradient(180deg, var(--primary), #a855f7); border-radius: 4px 4px 0 0; transition: height 0.3s; position: relative;"
                     title="{{ $trend->month }}: {{ $formatted }} ({{ $trend->job_count }} jobs, {{ $trend->pages }} pages)">
                </div>
                <div style="font-size: 0.6rem; color: var(--text-muted); margin-top: 4px; white-space: nowrap;">
                    {{ \Carbon\Carbon::createFromFormat('Y-m', $trend->month)->format('M Y') }}
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Top Spending Branches & Agents --}}
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    {{-- Top Branches --}}
    <div class="card">
        <div class="card-header">
            <h2>🏢 Top Spending Branches</h2>
        </div>
        @if($topBranches->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Branch</th>
                    <th>Jobs</th>
                    <th>Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topBranches as $idx => $b)
                <tr>
                    <td style="color: var(--text-muted);">{{ $idx + 1 }}</td>
                    <td><strong>{{ $b->branch?->name ?? 'N/A' }}</strong></td>
                    <td>{{ number_format($b->job_count) }}</td>
                    <td><strong>Rp {{ number_format($b->total, 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p style="color: var(--text-muted); font-size: 0.85rem;">No cost data available.</p>
        @endif
    </div>

    {{-- Top Agents --}}
    <div class="card">
        <div class="card-header">
            <h2>🖥️ Top Spending Agents</h2>
        </div>
        @if($topAgents->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Jobs</th>
                    <th>Total Cost</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topAgents as $idx => $a)
                <tr>
                    <td style="color: var(--text-muted);">{{ $idx + 1 }}</td>
                    <td><strong>{{ $a->printAgent?->name ?? 'N/A' }}</strong></td>
                    <td>{{ number_format($a->job_count) }}</td>
                    <td><strong>Rp {{ number_format($a->total, 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <p style="color: var(--text-muted); font-size: 0.85rem;">No cost data available.</p>
        @endif
    </div>
</div>

{{-- Cost by Branch Breakdown --}}
@if($costByBranch->count() > 0)
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2>📊 Cost by Branch</h2>
    </div>
    @php $maxBranchCost = max($costByBranch->pluck('total')->toArray()) ?: 1; @endphp
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Jobs</th>
                <th>Total Cost</th>
                <th>Share</th>
            </tr>
        </thead>
        <tbody>
            @foreach($costByBranch as $cb)
            <tr>
                <td><strong>{{ $cb->branch?->name ?? 'N/A' }}</strong></td>
                <td>{{ number_format($cb->job_count) }}</td>
                <td><strong>Rp {{ number_format($cb->total, 0, ',', '.') }}</strong></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                            <div style="height: 100%; width: {{ ($cb->total / $maxBranchCost) * 100 }}%; background: var(--primary); border-radius: 3px;"></div>
                        </div>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $totalCost > 0 ? round(($cb->total / $totalCost) * 100) : 0 }}%
                        </span>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Recent Costs Data Table --}}
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2>📋 Recent Cost Records</h2>
        <div>
            <a href="{{ route('admin.costs.export', request()->only(['start_date', 'end_date'])) }}" class="btn btn-secondary btn-sm">⬇ Export CSV</a>
        </div>
    </div>
    @if($recentCosts->count() > 0)
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Job ID</th>
                    <th>Branch</th>
                    <th>Agent</th>
                    <th>Pages</th>
                    <th>Type</th>
                    <th>Cost/Page</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentCosts as $cost)
                <tr>
                    <td style="font-size: 0.8rem;">{{ $cost->created_at?->format('d M Y H:i') }}</td>
                    <td><code class="mono">{{ $cost->printJob?->job_id ?? 'N/A' }}</code></td>
                    <td>{{ $cost->branch?->name ?? 'N/A' }}</td>
                    <td>{{ $cost->printAgent?->name ?? 'N/A' }}</td>
                    <td>{{ number_format($cost->pages_printed) }}</td>
                    <td>
                        @if($cost->is_color)
                            <span class="badge badge-warning">Color</span>
                        @else
                            <span class="badge badge-info">B&W</span>
                        @endif
                    </td>
                    <td>Rp {{ number_format($cost->cost_per_page, 4) }}</td>
                    <td><strong>Rp {{ number_format($cost->total_cost, 0, ',', '.') }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="pagination">
        {{ $recentCosts->appends(request()->only(['start_date', 'end_date']))->links() }}
    </div>
    @else
        <p style="color: var(--text-muted); font-size: 0.85rem; text-align: center; padding: 2rem 0;">
            No cost records found for the selected period.
        </p>
    @endif
</div>
@endsection
