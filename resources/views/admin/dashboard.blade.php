@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard']]" />

<!-- Live Clock & Welcome Banner -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 bg-gradient-to-r from-slate-900 via-slate-900/90 to-blue-950/40 border border-slate-800 rounded-2xl p-5 shadow-xs">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>Good Day, {{ auth()->user()->name ?? 'Administrator' }}</span>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20 font-mono">
                Hub Active
            </span>
        </h2>
        <p class="text-xs text-slate-400 mt-1">
            Overview of your print infrastructure, agent health, and real-time print telemetry.
            @if(!auth()->user()->isSuperAdmin() && auth()->user()->branch)
                — <strong class="text-slate-200">{{ auth()->user()->branch->name }}</strong>
            @endif
        </p>
    </div>

    <!-- Live Digital Clock Widget -->
    <div class="flex items-center gap-3 bg-slate-950/80 border border-slate-800/80 px-4 py-2 rounded-xl self-start sm:self-auto shadow-xs">
        <x-icon name="clock" size="18" class="text-blue-400" />
        <div>
            <div class="text-sm font-mono font-bold text-white" id="live-clock-time">--:--:-- <span class="text-[10px] text-blue-400 font-sans">WIB</span></div>
            <div class="text-[10px] text-slate-400" id="live-clock-date">Loading...</div>
        </div>
    </div>
</div>

{{-- Getting Started Checklist --}}
@if($stats['total_agents'] == 0 || $stats['total_profiles'] == 0 || \App\Models\PrintTemplate::count() == 0)
<div class="mb-6 p-5 rounded-2xl bg-gradient-to-r from-indigo-950/30 via-slate-900 to-slate-900 border border-indigo-500/30 shadow-xs">
    <div class="flex items-center justify-between mb-2">
        <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>🚀 Getting Started Checklist</span>
        </h3>
        <span class="text-xs text-indigo-400 font-semibold">Initial Setup</span>
    </div>
    <p class="text-xs text-slate-400 mb-4">Complete these key steps to get your print infrastructure operational:</p>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
        @php
            $agentCount    = $stats['total_agents'];
            $profileCount  = $stats['total_profiles'];
            $templateCount = \App\Models\PrintTemplate::count();
        @endphp

        <!-- Step 1 -->
        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-white">1. Register Agent</span>
                    @if($agentCount > 0)
                        <span class="badge badge-success">Done ({{ $agentCount }})</span>
                    @else
                        <span class="badge badge-warning">Pending</span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">Install TrayPrint on client PCs and connect to Hub.</p>
            </div>
            <div class="mt-3">
                @if($agentCount == 0)
                    <a href="{{ route('admin.agents') }}" class="btn-primary btn-sm w-full text-center">Register Agent →</a>
                @endif
            </div>
        </div>

        <!-- Step 2 -->
        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-white">2. Create Queue</span>
                    @if($profileCount > 0)
                        <span class="badge badge-success">Done ({{ $profileCount }})</span>
                    @else
                        <span class="badge badge-warning">Pending</span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">Define paper sizes, targets, and print routing.</p>
            </div>
            <div class="mt-3">
                @if($profileCount == 0)
                    <a href="{{ route('admin.profiles') }}" class="btn-primary btn-sm w-full text-center">Create Queue →</a>
                @endif
            </div>
        </div>

        <!-- Step 3 -->
        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-white">3. Design Template</span>
                    @if($templateCount > 0)
                        <span class="badge badge-success">Done ({{ $templateCount }})</span>
                    @else
                        <span class="badge badge-warning">Pending</span>
                    @endif
                </div>
                <p class="text-[11px] text-slate-400">Build label and document form layouts.</p>
            </div>
            <div class="mt-3">
                @if($templateCount == 0)
                    <a href="{{ route('admin.templates.create') }}" class="btn-primary btn-sm w-full text-center">Design Template →</a>
                @endif
            </div>
        </div>

        <!-- Step 4 -->
        <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-bold text-white">4. Client Integration</span>
                    <span class="badge badge-info">API Ready</span>
                </div>
                <p class="text-[11px] text-slate-400">Obtain API credentials to submit print jobs.</p>
            </div>
            <div class="mt-3">
                @if(auth()->user()?->isSuperAdmin())
                    <a href="{{ route('admin.clients') }}" class="btn-secondary btn-sm w-full text-center">Client Apps →</a>
                @else
                    <a href="{{ route('admin.sdk-docs') }}" class="btn-secondary btn-sm w-full text-center">View Docs →</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endif

<!-- Top Metrics Cards (Grid) -->
<div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3 mb-6" id="stats-grid">
    <!-- Total Agents -->
    <a href="{{ route('admin.agents') }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Total Agents</p>
        <h3 class="text-xl sm:text-2xl font-bold text-white font-mono mt-1 group-hover:text-blue-400 transition" id="stat-total-agents">{{ $stats['total_agents'] }}</h3>
    </a>

    <!-- Online Agents -->
    <a href="{{ route('admin.agents') }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-emerald-500/40 transition shadow-xs group block">
        <div class="flex items-center justify-between">
            <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Online</p>
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        </div>
        <h3 class="text-xl sm:text-2xl font-bold text-emerald-400 font-mono mt-1" id="stat-online-agents">{{ $stats['online_agents'] }}</h3>
    </a>

    <!-- Offline Agents -->
    <a href="{{ route('admin.agents') }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-rose-500/40 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Offline</p>
        <h3 class="text-xl sm:text-2xl font-bold text-rose-400 font-mono mt-1" id="stat-offline-agents">{{ $stats['offline_agents'] ?? 0 }}</h3>
    </a>

    <!-- Virtual Queues -->
    <a href="{{ route('admin.profiles') }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Queues</p>
        <h3 class="text-xl sm:text-2xl font-bold text-indigo-400 font-mono mt-1" id="stat-total-profiles">{{ $stats['total_profiles'] }}</h3>
    </a>

    <!-- Jobs Today -->
    <a href="{{ route('admin.jobs', ['date_from' => now()->format('Y-m-d')]) }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Jobs Today</p>
        <h3 class="text-xl sm:text-2xl font-bold text-white font-mono mt-1" id="stat-today-jobs">{{ $stats['today_jobs'] }}</h3>
    </a>

    <!-- Pending Jobs -->
    <a href="{{ route('admin.jobs', ['status' => 'pending']) }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-amber-500/40 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Pending</p>
        <h3 class="text-xl sm:text-2xl font-bold text-amber-400 font-mono mt-1" id="stat-pending-jobs">{{ $stats['pending_jobs'] }}</h3>
    </a>

    <!-- Failed Jobs -->
    <a href="{{ route('admin.jobs', ['status' => 'failed']) }}" class="p-4 rounded-2xl bg-slate-900 border border-slate-800 hover:border-rose-500/40 transition shadow-xs group block">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Failed</p>
        <h3 class="text-xl sm:text-2xl font-bold text-rose-400 font-mono mt-1" id="stat-failed-jobs">{{ $stats['failed_jobs'] }}</h3>
    </a>

    <!-- Success Rate -->
    <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-xs">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Success Rate</p>
        <h3 class="text-xl sm:text-2xl font-bold {{ ($stats['success_rate'] ?? 100) >= 95 ? 'text-emerald-400' : 'text-amber-400' }} font-mono mt-1" id="stat-success-rate">
            {{ $stats['success_rate'] !== null ? $stats['success_rate'] . '%' : '—' }}
        </h3>
    </div>
</div>

{{-- Today's Print Telemetry --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-sm font-bold text-white">📋 Today's Print Telemetry</h3>
            <p class="text-xs text-slate-400">Live summary of jobs processed in the current 24-hour cycle</p>
        </div>
        <a href="{{ route('admin.jobs', ['date_from' => now()->format('Y-m-d')]) }}" class="btn-secondary btn-sm">View Jobs</a>
    </div>

    @if($stats['today_jobs'] > 0)
        @php
            $allToday = $stats['today_jobs'];
            $todayFromRecent = $recentJobs->filter(fn($j) => $j->created_at->isToday());
            $tdySuccess    = $todayFromRecent->where('status', 'success')->count();
            $tdyFailed     = $todayFromRecent->where('status', 'failed')->count();
            $tdyPending    = $todayFromRecent->where('status', 'pending')->count();
            $tdyProcessing = $todayFromRecent->where('status', 'processing')->count();
            $tdySum        = $tdySuccess + $tdyFailed + $tdyPending + $tdyProcessing;
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                <div class="text-xl font-bold font-mono text-white">{{ $allToday }}</div>
                <div class="text-[11px] text-slate-400 uppercase tracking-wider mt-0.5">Total Submitted</div>
            </div>
            <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-center">
                <div class="text-xl font-bold font-mono text-emerald-400">{{ $tdySum > 0 ? round(($tdySuccess / $tdySum) * 100) : 0 }}%</div>
                <div class="text-[11px] text-emerald-400 uppercase tracking-wider mt-0.5">Completed</div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $tdySuccess }} jobs</div>
            </div>
            <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-center">
                <div class="text-xl font-bold font-mono text-rose-400">{{ $tdyFailed }}</div>
                <div class="text-[11px] text-rose-400 uppercase tracking-wider mt-0.5">Failed</div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $tdySum > 0 ? round(($tdyFailed / $tdySum) * 100) : 0 }}% rate</div>
            </div>
            <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-center">
                <div class="text-xl font-bold font-mono text-amber-400">{{ $tdyPending + $tdyProcessing }}</div>
                <div class="text-[11px] text-amber-400 uppercase tracking-wider mt-0.5">In Pipeline</div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $tdyPending }} wait · {{ $tdyProcessing }} active</div>
            </div>
            <div class="p-3.5 rounded-xl bg-blue-500/10 border border-blue-500/20 text-center col-span-2 sm:col-span-1">
                <div class="text-xl font-bold font-mono text-blue-400">{{ $stats['success_rate'] !== null ? $stats['success_rate'] . '%' : '—' }}</div>
                <div class="text-[11px] text-blue-400 uppercase tracking-wider mt-0.5">All-Time Reliability</div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ number_format($stats['total_jobs']) }} total jobs</div>
            </div>
        </div>
    @else
        <div class="text-center py-6 text-xs text-slate-400 bg-slate-950 rounded-xl border border-slate-800">
            No print jobs submitted yet today. System is ready and standing by.
        </div>
    @endif
</div>

<!-- SLA Breach Warnings (if any) -->
@if($slaBreachJobs->count() > 0)
<div class="bg-slate-900 border border-rose-500/50 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
        <div class="flex items-center gap-2">
            <x-icon name="warning" size="18" class="text-rose-500" />
            <h3 class="text-sm font-bold text-white">SLA Breach — Overdue Jobs</h3>
        </div>
        <span class="badge badge-danger">{{ $slaBreachJobs->count() }} overdue</span>
    </div>
    
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800">
                <tr>
                    <th class="px-4 py-2.5">Job ID</th>
                    <th class="px-4 py-2.5">Template</th>
                    <th class="px-4 py-2.5">Agent</th>
                    <th class="px-4 py-2.5">Status</th>
                    <th class="px-4 py-2.5">Created</th>
                    <th class="px-4 py-2.5 text-right">Elapsed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @foreach($slaBreachJobs as $job)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-4 py-2.5 font-mono font-bold text-blue-400">#{{ $job->id }}</td>
                    <td class="px-4 py-2.5 text-white font-medium">{{ $job->template_name ?? $job->printer_name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $job->agent->name ?? '—' }}</td>
                    <td class="px-4 py-2.5"><span class="badge badge-warning">⏳ {{ ucfirst($job->status) }}</span></td>
                    <td class="px-4 py-2.5 text-xs text-slate-400">{{ $job->created_at->format('M j, H:i') }}</td>
                    <td class="px-4 py-2.5 text-right"><span class="badge badge-danger">{{ $job->created_at->diffForHumans(now(), true) }}</span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<!-- Two-Column Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Left Column: Agent Health & Status -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-white">🖥️ Agent Health Roster</h3>
                    <p class="text-xs text-slate-400">Live heartbeat & connectivity of print stations</p>
                </div>
                <a href="{{ route('admin.agents') }}" class="btn-secondary btn-sm">Manage Agents</a>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800/80">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/70 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Agent Name</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Jobs</th>
                            <th class="px-4 py-3 text-right">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($agents->take(6) as $agent)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-medium text-white">
                                <a href="{{ route('admin.agents') }}" class="hover:text-blue-400 transition">{{ $agent->name }}</a>
                                @if($agent->branch)
                                    <div class="text-[10px] text-slate-500">{{ $agent->branch->name }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($agent->isOnline())
                                    <span class="badge badge-success"><span class="dot dot-green"></span> Online</span>
                                @else
                                    <span class="badge badge-danger"><span class="dot dot-red"></span> Offline</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-400">{{ $agent->jobs_count ?? 0 }}</td>
                            <td class="px-4 py-3 text-right text-xs text-slate-400 font-mono">
                                {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-xs text-slate-500">No print agents connected</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Jobs Activity Feed -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
        <div>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-sm font-bold text-white">⚡ Recent Print Activity</h3>
                    <p class="text-xs text-slate-400">Real-time audit stream of submitted print requests</p>
                </div>
                <a href="{{ route('admin.jobs') }}" class="btn-secondary btn-sm">All Jobs</a>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-800/80">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/70 text-xs uppercase text-slate-400 border-b border-slate-800">
                        <tr>
                            <th class="px-4 py-3">Template / Target</th>
                            <th class="px-4 py-3">Agent</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @forelse($recentJobs->take(6) as $job)
                        <tr class="hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3 font-medium text-white text-xs">
                                <span class="font-semibold">{{ $job->template_name ?? $job->printer_name ?? 'Raw Job' }}</span>
                                @if($job->reference_id)
                                    <span class="block text-[10px] text-slate-500 font-mono">ref: {{ $job->reference_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-400">{{ $job->agent->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @switch($job->status)
                                    @case('success')
                                        <span class="badge badge-success">Done</span>
                                        @break
                                    @case('failed')
                                        <span class="badge badge-danger">Failed</span>
                                        @break
                                    @case('pending')
                                        <span class="badge badge-warning">Pending</span>
                                        @break
                                    @case('processing')
                                        <span class="badge badge-info">Printing</span>
                                        @break
                                    @default
                                        <span class="badge">{{ $job->status }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-right text-xs text-slate-400 font-mono">
                                {{ $job->created_at->diffForHumans() }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-xs text-slate-500">No recent print jobs found</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Live Real-Time Clock & Auto-Refresh Scripts -->
<script>
    // Live Digital Clock (matching attendance system banner)
    function updateLiveClock() {
        const timeEl = document.getElementById('live-clock-time');
        const dateEl = document.getElementById('live-clock-date');
        if (!timeEl || !dateEl) return;

        const now = new Date();
        timeEl.innerHTML = now.toLocaleTimeString('id-ID', { hour12: false }) + ' <span class="text-[10px] text-blue-400 font-sans">WIB</span>';
        dateEl.textContent = now.toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    updateLiveClock();
    setInterval(updateLiveClock, 1000);

    // Dashboard Telemetry Auto-Refresh (every 30s)
    (function() {
        let timer;
        function refreshTelemetry() {
            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    ['stat-total-agents','stat-online-agents','stat-offline-agents','stat-total-profiles',
                     'stat-today-jobs','stat-pending-jobs','stat-failed-jobs','stat-success-rate'].forEach(id => {
                        const el = document.getElementById(id);
                        const newEl = doc.getElementById(id);
                        if (el && newEl && el.textContent !== newEl.textContent) {
                            el.textContent = newEl.textContent;
                        }
                    });
                })
                .catch(() => {});
        }

        timer = setInterval(refreshTelemetry, 30000);
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) { clearInterval(timer); }
            else { refreshTelemetry(); timer = setInterval(refreshTelemetry, 30000); }
        });
    })();
</script>
@endsection
