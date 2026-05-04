@extends('admin.layout')
@section('title', 'Monitoring Dashboard')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>📊 Monitoring Dashboard</h1>
            <p>System health, print volumes, and error rates — auto-refreshing every 30s.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span id="last-updated" style="font-size: 0.75rem; color: var(--text-muted);"></span>
            <span class="badge badge-info" id="refresh-indicator">Live</span>
        </div>
    </div>
</div>

{{-- Row 1: Stat Cards --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info);">{{ number_format($jobsToday) }}</div>
        <div class="stat-label">Jobs Today</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);">{{ $successRate }}%</div>
        <div class="stat-label">Success Rate (Today)</div>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            {{ number_format($successToday) }} success / {{ number_format($failedToday) }} failed
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);">{{ $activeAgents }}</div>
        <div class="stat-label">Active Agents</div>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            {{ $offlineCount }} offline
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);">{{ number_format($queueDepth) }}</div>
        <div class="stat-label">Queue Depth</div>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            Avg processing: {{ $avgProcessingTime ? round($avgProcessingTime) . 's' : 'N/A' }}
        </div>
    </div>
</div>

{{-- More stats row --}}
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">{{ number_format($jobsWeek) }}</div>
        <div class="stat-label">This Week</div>
    </div>
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">{{ number_format($jobsMonth) }}</div>
        <div class="stat-label">This Month</div>
    </div>
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">{{ $failureRate }}%</div>
        <div class="stat-label">Failure Rate</div>
    </div>
    <div class="stat-card" style="padding: 1rem;">
        <div class="stat-value" style="font-size: 1.4rem; color: var(--text);">{{ number_format($onlineCount + $offlineCount) }}</div>
        <div class="stat-label">Total Agents</div>
    </div>
</div>

{{-- Row: Sustainability Widget --}}
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="stat-card" style="padding: 1rem; border-color: #22c55e;">
        <div class="stat-value" style="font-size: 1.4rem; color: #22c55e;">
            🌿 {{ $ecoProfiles }}
        </div>
        <div class="stat-label">Eco Profiles</div>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            Profiles with eco mode enabled
        </div>
    </div>
    <div class="stat-card" style="padding: 1rem; border-color: #22c55e; grid-column: span 3;">
        <div class="stat-value" style="font-size: 1.4rem; color: #22c55e;">
            💚 {{ number_format($totalCarbonSaved, 2) }} g CO₂
        </div>
        <div class="stat-label">Total Carbon Saved</div>
        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.25rem;">
            {{ $totalCarbonSaved >= 1000 ? 'That\'s ~' . number_format($totalCarbonSaved / 1000, 2) . ' kg of CO₂! 🌍' : 'Every gram counts! Keep printing green.' }}
        </div>
    </div>
</div>

{{-- Row 2: Job Timeline Chart (CSS bar chart) --}}
<div class="card">
    <div class="card-header">
        <h2>📈 Job Creation Timeline (Last 24 Hours)</h2>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <select id="timeline-period" onchange="refreshTimeline()" style="width: auto; padding: 0.3rem 0.5rem; font-size: 0.8rem;">
                <option value="24h">Last 24 Hours</option>
                <option value="7d">Last 7 Days</option>
                <option value="30d">Last 30 Days</option>
            </select>
        </div>
    </div>
    <div id="timeline-chart" style="min-height: 200px; display: flex; align-items: flex-end; gap: 2px; padding: 1rem 0;">
        <div style="width: 100%; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
            <span class="spinner"></span> Loading chart data...
        </div>
    </div>
</div>

{{-- Row 3: Agent Health + Top Printers --}}
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    {{-- Agent Health Table --}}
    <div class="card">
        <div class="card-header">
            <h2>🖥️ Agent Health</h2>
        </div>
        @if($agents->count() > 0)
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Last Seen</th>
                        <th>Printers</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agents as $agent)
                    <tr>
                        <td><strong>{{ $agent->name }}</strong></td>
                        <td>
                            @if($agent->isOnline())
                                <span class="badge badge-success"><span class="dot dot-green"></span>Online</span>
                            @else
                                <span class="badge badge-danger"><span class="dot dot-red"></span>Offline</span>
                            @endif
                        </td>
                        <td style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $agent->capabilities['version'] ?? 'unknown' }}
                        </td>
                        <td style="font-size: 0.8rem;">
                            {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                        </td>
                        <td style="font-size: 0.75rem;">
                            {{ is_array($agent->printers) ? count($agent->printers) : 0 }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
            <x-empty-state icon="agent" title="No agents registered" message="Register a print agent to see health metrics." />
        @endif
    </div>

    {{-- Top Printers by Usage --}}
    <div class="card">
        <div class="card-header">
            <h2>🖨️ Top Printers (30 Days)</h2>
        </div>
        @if($topPrinters->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Printer</th>
                    <th>Jobs</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = $topPrinters->sum('total'); @endphp
                @foreach($topPrinters as $idx => $printer)
                <tr>
                    <td style="color: var(--text-muted);">{{ $idx + 1 }}</td>
                    <td><strong>{{ $printer->printer_name }}</strong></td>
                    <td>{{ number_format($printer->total) }}</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                <div style="height: 100%; width: {{ $grandTotal > 0 ? ($printer->total / $grandTotal) * 100 : 0 }}%; background: var(--primary); border-radius: 3px;"></div>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">
                                {{ $grandTotal > 0 ? round(($printer->total / $grandTotal) * 100) : 0 }}%
                            </span>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
            <x-empty-state icon="printer" title="No print data" message="Print jobs will appear here as they are processed." />
        @endif
    </div>
</div>

{{-- Top Users Section --}}
@if($topUsers->count() > 0)
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2>👤 Top Users by Print Volume (30 Days)</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Reference ID</th>
                <th>Jobs Printed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topUsers as $idx => $user)
            <tr>
                <td style="color: var(--text-muted);">{{ $idx + 1 }}</td>
                <td><strong>{{ $user->reference_id }}</strong></td>
                <td>{{ number_format($user->total) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Version Distribution --}}
@if($versionDist->count() > 0)
<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <h2>📦 Agent Version Distribution</h2>
    </div>
    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
        @foreach($versionDist as $version => $count)
        <div style="background: var(--bg); padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border); min-width: 120px; text-align: center;">
            <div style="font-size: 1.2rem; font-weight: 700;">{{ $count }}</div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">{{ $version }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
// Auto-refresh every 30 seconds
let refreshInterval;

function startAutoRefresh() {
    refreshInterval = setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                // Update last-updated timestamp
                document.getElementById('last-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString();
                
                // Flash the indicator
                const indicator = document.getElementById('refresh-indicator');
                indicator.textContent = 'Refreshing...';
                indicator.className = 'badge badge-warning';
                setTimeout(() => {
                    indicator.textContent = 'Live';
                    indicator.className = 'badge badge-info';
                }, 1000);
            })
            .catch(() => {
                const indicator = document.getElementById('refresh-indicator');
                indicator.textContent = 'Offline';
                indicator.className = 'badge badge-danger';
            });
    }, 30000);
}

// Timeline chart
function refreshTimeline() {
    const period = document.getElementById('timeline-period').value;
    const chartEl = document.getElementById('timeline-chart');
    chartEl.innerHTML = '<div style="width:100%;text-align:center;color:var(--text-muted);font-size:0.85rem;"><span class="spinner"></span> Loading...</div>';

    fetch(`/admin/monitoring/job-timeline?period=${period}`)
        .then(r => r.json())
        .then(data => {
            const timeline = data.timeline || [];
            if (timeline.length === 0) {
                chartEl.innerHTML = '<div style="width:100%;text-align:center;color:var(--text-muted);font-size:0.85rem;padding:2rem 0;">No data for this period.</div>';
                return;
            }

            const maxCount = Math.max(...timeline.map(t => t.count), 1);
            const barHeight = 180;

            let html = '<div style="display:flex;align-items:flex-end;gap:2px;height:' + barHeight + 'px;padding:0 0.5rem;width:100%;overflow-x:auto;">';
            timeline.forEach(point => {
                const h = Math.max((point.count / maxCount) * barHeight, 2);
                html += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;min-width:30px;">';
                html += '<div style="font-size:0.65rem;color:var(--text-muted);margin-bottom:2px;">' + point.count + '</div>';
                html += '<div style="width:100%;height:' + h + 'px;background:var(--primary);border-radius:3px 3px 0 0;opacity:0.8;transition:height 0.3s;" title="' + point.label + ': ' + point.count + ' jobs"></div>';
                html += '<div style="font-size:0.6rem;color:var(--text-muted);margin-top:2px;transform:rotate(-45deg);white-space:nowrap;">' + point.label + '</div>';
                html += '</div>';
            });
            html += '</div>';
            chartEl.innerHTML = html;
        })
        .catch(() => {
            chartEl.innerHTML = '<div style="width:100%;text-align:center;color:var(--danger);font-size:0.85rem;">Failed to load timeline.</div>';
        });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('last-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString();
    refreshTimeline();
    startAutoRefresh();
});
</script>
@endsection
