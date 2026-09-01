@extends('admin.layout')
@section('title', 'Monitoring Dashboard')

@section('content')
<x-breadcrumb :items="[['label' => 'System Health & Monitoring']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Live Monitoring & Telemetry</h2>
        <p class="text-xs text-slate-400">Real-time throughput metrics, cluster health, latency and environmental impact</p>
    </div>
    <div class="flex items-center gap-2">
        <span id="last-updated" class="text-xs text-slate-500 font-mono"></span>
        <span class="badge badge-success" id="refresh-indicator">
            <span class="dot dot-green"></span> Live Stream
        </span>
    </div>
</div>

{{-- 4 Core Stat Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Jobs Today</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-blue-400">{{ number_format($jobsToday) }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Processed in last 24h</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Success Reliability</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-emerald-400">{{ $successRate }}%</div>
        <span class="text-[10px] text-slate-500 mt-1 block">{{ number_format($successToday) }} ok / {{ number_format($failedToday) }} fail</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Online Agents</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-indigo-400">{{ $activeAgents }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">{{ $offlineCount }} nodes offline</span>
    </div>
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4.5 shadow-xs">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Active Queue Depth</span>
        <div class="text-xl sm:text-2xl font-mono font-bold text-amber-400">{{ number_format($queueDepth) }}</div>
        <span class="text-[10px] text-slate-500 mt-1 block">Avg latency: {{ $avgProcessingTime ? round($avgProcessingTime) . 's' : 'N/A' }}</span>
    </div>
</div>

{{-- Carbon & Sustainability Row --}}
<div class="bg-gradient-to-r from-emerald-950/40 via-slate-900 to-slate-900 border border-emerald-500/20 rounded-2xl p-5 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-lg">
            🌱
        </div>
        <div>
            <h3 class="text-sm font-bold text-white">Sustainability & Eco Reduction</h3>
            <p class="text-xs text-slate-400">Total estimated carbon footprint eliminated via electronic routing</p>
        </div>
    </div>
    <div class="flex items-center gap-4">
        <div class="text-right">
            <span class="text-xs text-slate-400 block">Carbon Saved</span>
            <span class="text-base sm:text-lg font-mono font-bold text-emerald-400">{{ number_format($totalCarbonSaved, 2) }} g CO₂</span>
        </div>
        <div class="text-right border-l border-slate-800 pl-4">
            <span class="text-xs text-slate-400 block">Eco Profiles</span>
            <span class="text-base sm:text-lg font-mono font-bold text-white">{{ $ecoProfiles }}</span>
        </div>
    </div>
</div>

{{-- Job Creation Timeline Chart Container --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Hourly Print Dispatch Volume</h3>
        <select id="timeline-period" onchange="refreshTimeline()" class="bg-slate-950 border border-slate-800 rounded-lg px-2.5 py-1 text-xs text-slate-200">
            <option value="24h">Last 24 Hours</option>
            <option value="7d">Last 7 Days</option>
            <option value="30d">Last 30 Days</option>
        </select>
    </div>
    <div id="timeline-chart" class="min-h-[180px] flex items-end gap-1.5 pt-4">
        <div class="w-full text-center text-xs text-slate-500">Loading metrics...</div>
    </div>
</div>

{{-- Agent Health & Top Hardware Grid --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Agent Health --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Agent Heartbeat Roster</h3>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-5 py-3">Agent</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Version</th>
                    <th class="px-5 py-3 text-right">Last Seen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($agents as $agent)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-5 py-3 font-semibold text-white">{{ $agent->name }}</td>
                    <td class="px-5 py-3">
                        <span class="badge {{ $agent->isOnline() ? 'badge-success' : 'badge-danger' }} text-[10px]">
                            <span class="dot {{ $agent->isOnline() ? 'dot-green' : 'dot-red' }}"></span>
                            {{ $agent->isOnline() ? 'Online' : 'Offline' }}
                        </span>
                    </td>
                    <td class="px-5 py-3 font-mono text-xs text-slate-400">
                        v{{ $agent->capabilities['version'] ?? '1.0' }}
                    </td>
                    <td class="px-5 py-3 text-xs text-slate-400 font-mono text-right">
                        {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-5 py-4 text-center text-slate-500 text-xs">No agents registered.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Top Printers --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 border-b border-slate-800">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Top Utilizing Printers (30 Days)</h3>
        </div>
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-5 py-3">Device Name</th>
                    <th class="px-5 py-3">Jobs</th>
                    <th class="px-5 py-3 text-right">Load Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @php $grandTotal = $topPrinters->sum('total'); @endphp
                @forelse($topPrinters as $printer)
                <tr class="hover:bg-slate-800/40">
                    <td class="px-5 py-3 font-mono font-semibold text-blue-400 text-xs">{{ $printer->printer_name }}</td>
                    <td class="px-5 py-3 text-xs font-mono text-slate-300">{{ number_format($printer->total) }}</td>
                    <td class="px-5 py-3 text-xs font-mono text-slate-400 text-right">
                        {{ $grandTotal > 0 ? round(($printer->total / $grandTotal) * 100) : 0 }}%
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-5 py-4 text-center text-slate-500 text-xs">No print metrics available.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function refreshTimeline() {
    const period = document.getElementById('timeline-period').value;
    const chartEl = document.getElementById('timeline-chart');
    chartEl.innerHTML = '<div class="w-full text-center text-xs text-slate-500">Loading timeline data...</div>';

    fetch(`/admin/monitoring/job-timeline?period=${period}`)
        .then(r => r.json())
        .then(data => {
            const timeline = data.timeline || [];
            if (timeline.length === 0) {
                chartEl.innerHTML = '<div class="w-full text-center text-xs text-slate-500 py-6">No data for selected period.</div>';
                return;
            }

            const maxCount = Math.max(...timeline.map(t => t.count), 1);
            let html = '<div class="flex items-end gap-1.5 h-40 w-full overflow-x-auto px-2">';
            timeline.forEach(point => {
                const h = Math.max((point.count / maxCount) * 140, 4);
                html += `<div class="flex-1 flex flex-col items-center min-w-[28px] group relative">
                    <span class="text-[9px] font-mono text-slate-400 mb-1 opacity-0 group-hover:opacity-100 transition">${point.count}</span>
                    <div class="w-full rounded-t-md bg-blue-500/80 group-hover:bg-blue-400 transition" style="height: ${h}px" title="${point.label}: ${point.count} jobs"></div>
                    <span class="text-[9px] font-mono text-slate-500 mt-1.5 truncate max-w-[28px]">${point.label}</span>
                </div>`;
            });
            html += '</div>';
            chartEl.innerHTML = html;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
    refreshTimeline();
});
</script>
@endsection
