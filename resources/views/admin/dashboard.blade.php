@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1>Dashboard</h1>
        <p>
            Overview of your print infrastructure
            @if(!auth()->user()->isSuperAdmin() && auth()->user()->branch)
                — <strong>{{ auth()->user()->branch->name }}</strong>
            @endif
        </p>
    </div>
    <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
        <span id="refresh-indicator" style="display: flex; align-items: center; gap: 0.35rem;" aria-label="Dashboard auto-refreshes every 30 seconds" role="status" aria-live="polite">
            <span class="dot dot-green" style="animation: pulse 2s infinite;"></span> Auto-refreshing
        </span>
    </div>
</div>

{{-- Getting Started Checklist --}}
@if($stats['total_agents'] == 0 || $stats['total_profiles'] == 0 || \App\Models\PrintTemplate::count() == 0)
<div class="card" style="border: 1px solid rgba(99, 102, 241, 0.3); background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(168, 85, 247, 0.04));">
    <div class="card-header"><h2>🚀 Getting Started</h2></div>
    <p style="color: var(--text-muted); margin-bottom: 1.25rem;">Complete these steps to get your print infrastructure running:</p>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        @php
            $agentCount    = $stats['total_agents'];
            $profileCount  = $stats['total_profiles'];
            $templateCount = \App\Models\PrintTemplate::count();
        @endphp
        @foreach([
            ['done' => $agentCount > 0,    'icon' => '1️⃣', 'label' => 'Register a Print Agent',      'sub' => 'Install TrayPrint on a workstation and register it here',              'route' => route('admin.agents'),          'count' => $agentCount ? "{$agentCount} registered" : null],
            ['done' => $profileCount > 0,  'icon' => '2️⃣', 'label' => 'Create a Print Queue',         'sub' => 'Define paper size, margins, target printer, and advanced options',     'route' => route('admin.profiles'),        'count' => $profileCount ? "{$profileCount} queues" : null],
            ['done' => $templateCount > 0, 'icon' => '3️⃣', 'label' => 'Design a Print Template',      'sub' => 'Use the drag-and-drop designer to create form layouts',                 'route' => route('admin.templates.create'), 'count' => $templateCount ? "{$templateCount} templates" : null],
        ] as $step)
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
            <span style="font-size: 1.2rem;">{{ $step['done'] ? '✅' : $step['icon'] }}</span>
            <div style="flex: 1;"><strong>{{ $step['label'] }}</strong><br><span style="font-size: 0.8rem; color: var(--text-muted);">{{ $step['sub'] }}</span></div>
            @if($step['done'])
                <span style="color: var(--success); font-size: 0.8rem; font-weight: 600;">{{ $step['count'] }}</span>
            @else
                <a href="{{ $step['route'] }}" class="btn btn-primary btn-sm">Go →</a>
            @endif
        </div>
        @endforeach
        <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.6rem 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
            <span style="font-size: 1.2rem;">4️⃣</span>
            <div style="flex: 1;"><strong>Connect a Client App</strong><br><span style="font-size: 0.8rem; color: var(--text-muted);">Register an app, get an API key, and start sending print jobs</span></div>
            @if(auth()->user()?->isSuperAdmin())
                <a href="{{ route('admin.clients') }}" class="btn btn-primary btn-sm">Go →</a>
            @else
                <a href="{{ route('admin.sdk-docs') }}" class="btn btn-secondary btn-sm">View Docs</a>
            @endif
        </div>
    </div>
</div>
@endif

{{-- Stats Row --}}
<div class="stats-grid" id="stats-grid">
    <div class="stat-card" onclick="location.href='{{ route('admin.agents') }}'" style="cursor: pointer;">
        <div class="stat-value" style="color: var(--info);" id="stat-total-agents">{{ $stats['total_agents'] }}</div>
        <div class="stat-label">Total Agents</div>
    </div>
    <div class="stat-card" onclick="location.href='{{ route('admin.agents') }}'" style="cursor: pointer;">
        <div class="stat-value" style="color: var(--success);" id="stat-online-agents">{{ $stats['online_agents'] }}</div>
        <div class="stat-label">Online Now</div>
    </div>
    <div class="stat-card" onclick="location.href='{{ route('admin.agents') }}'" style="cursor: pointer;">
        <div class="stat-value" style="color: var(--danger);" id="stat-offline-agents">{{ $stats['offline_agents'] ?? 0 }}</div>
        <div class="stat-label">Offline Agents</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);" id="stat-total-profiles">{{ $stats['total_profiles'] }}</div>
        <div class="stat-label">Virtual Queues</div>
    </div>
    <div class="stat-card" onclick="location.href='{{ route('admin.jobs') }}'" style="cursor: pointer;">
        <div class="stat-value" id="stat-today-jobs">{{ $stats['today_jobs'] }}</div>
        <div class="stat-label">Jobs Today</div>
    </div>
    <div class="stat-card" onclick="location.href='{{ route('admin.jobs') }}'" style="cursor: pointer;">
        <div class="stat-value" style="color: var(--warning);" id="stat-pending-jobs">{{ $stats['pending_jobs'] }}</div>
        <div class="stat-label">Pending Jobs</div>
    </div>
    <div class="stat-card" onclick="location.href='{{ route('admin.jobs') }}'" style="cursor: pointer;">
        <div class="stat-value" style="color: var(--danger);" id="stat-failed-jobs">{{ $stats['failed_jobs'] }}</div>
        <div class="stat-label">Failed Jobs</div>
    </div>
    @if($stats['success_rate'] !== null)
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);" id="stat-success-rate">{{ $stats['success_rate'] }}%</div>
        <div class="stat-label">Success Rate</div>
    </div>
    @endif
</div>

{{-- Today's Print Jobs --}}
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2>📋 Today's Print Jobs</h2>
        <a href="{{ route('admin.jobs', ['date_from' => now()->format('Y-m-d')]) }}" class="btn btn-primary btn-sm">View All</a>
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
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; padding: 1rem 0;">
            <div style="text-align: center; padding: 1rem; background: var(--bg); border-radius: 8px; border: 1px solid var(--border);">
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--text);">{{ $allToday }}</div>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Total</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(34,197,94,0.06); border-radius: 8px; border: 1px solid rgba(34,197,94,0.2);">
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--success);">{{ $tdySum > 0 ? round(($tdySuccess / $tdySum) * 100) : 0 }}%</div>
                <div style="font-size: 0.75rem; color: var(--success); margin-top: 0.25rem;">Completed</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $tdySuccess }} jobs</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(239,68,68,0.06); border-radius: 8px; border: 1px solid rgba(239,68,68,0.2);">
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--danger);">{{ $tdyFailed }}</div>
                <div style="font-size: 0.75rem; color: var(--danger); margin-top: 0.25rem;">Failed</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $tdySum > 0 ? round(($tdyFailed / $tdySum) * 100) : 0 }}%</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(245,158,11,0.06); border-radius: 8px; border: 1px solid rgba(245,158,11,0.2);">
                <div style="font-size: 1.8rem; font-weight: 700; color: var(--warning);">{{ $tdyPending + $tdyProcessing }}</div>
                <div style="font-size: 0.75rem; color: var(--warning); margin-top: 0.25rem;">In Progress</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $tdyPending }} pending · {{ $tdyProcessing }} processing</div>
            </div>
            <div style="text-align: center; padding: 1rem; background: rgba(99,102,241,0.06); border-radius: 8px; border: 1px solid rgba(99,102,241,0.2);">
                <div style="font-size: 1rem; font-weight: 700;">
                    @if($stats['success_rate'] !== null)
                        <span style="color: var(--primary);">{{ $stats['success_rate'] }}%</span>
                    @else
                        <span style="color: var(--text-muted);">—</span>
                    @endif
                </div>
                <div style="font-size: 0.75rem; color: var(--primary); margin-top: 0.25rem;">Overall Success Rate</div>
                <div style="font-size: 0.7rem; color: var(--text-muted);">{{ $stats['total_jobs'] }} total jobs</div>
            </div>
        </div>
        @if($tdySum > 0 && $tdySum < $allToday)
            <div style="font-size: 0.7rem; color: var(--text-muted); text-align: center; padding: 0.25rem 0 0.5rem; border-top: 1px solid var(--border);">
                ⓘ Status breakdown based on recent jobs ({{ $tdySum }} of {{ $allToday }} today's jobs shown).
                <a href="{{ route('admin.jobs', ['date_from' => now()->format('Y-m-d')]) }}" style="color: var(--primary);">View full list →</a>
            </div>
        @endif
    @else
        <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem;">
            No print jobs submitted yet today.
        </div>
    @endif
</div>

{{-- Agent Uptime Section (Item 3.1) --}}
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2>🖥️ Agent Uptime</h2>
        <a href="{{ route('admin.agents') }}" class="btn btn-primary btn-sm">Manage Agents</a>
    </div>
    <div style="overflow-x: auto;">
        <table role="table">
            <caption class="sr-only">Agent uptime summary</caption>
            <thead>
                <tr>
                    <th scope="col">Agent</th>
                    <th scope="col">Status</th>
                    <th scope="col">Uptime</th>
                    <th scope="col">Last Seen</th>
                    <th scope="col">Branch</th>
                </tr>
            </thead>
            <tbody>
                @forelse($agentsWithUptime ?? $agents as $agent)
                <tr>
                    <td><strong>{{ $agent->name }}</strong></td>
                    <td>
                        @if($agent->isOnline())
                            <span class="badge badge-success"><span class="dot dot-green"></span> Online</span>
                        @else
                            <span class="badge badge-danger"><span class="dot dot-red"></span> Offline</span>
                        @endif
                    </td>
                    <td style="font-size: 0.85rem;">
                        @if($agent->isOnline())
                            <span style="color: var(--success);">🟢 {{ $agent->uptime_duration ?? 'Just now' }}</span>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;">
                        {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">
                        {{ $agent->branch->name ?? '—' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="color: var(--text-muted); text-align: center;">No agents registered</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- SLA Breach Indicators (Item 3.2) --}}
@if($slaBreachJobs->count() > 0)
<div class="card" style="margin-bottom: 1.5rem; border-color: var(--danger);">
    <div class="card-header" style="border-bottom-color: rgba(239,68,68,0.2);">
        <h2>⚠️ SLA Breach — Overdue Jobs</h2>
        <span class="badge badge-danger">{{ $slaBreachJobs->count() }} overdue</span>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>Job ID</th>
                    <th>Template</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Time Since</th>
                </tr>
            </thead>
            <tbody>
                @foreach($slaBreachJobs as $job)
                @php
                    $hoursOverdue = $job->created_at->diffInHours(now());
                    $severity = $hoursOverdue >= 4 ? 'danger' : ($hoursOverdue >= 2 ? 'warning' : 'info');
                @endphp
                <tr style="border-left: 3px solid var(--{{ $severity }});">
                    <td style="font-family: monospace; font-size: 0.8rem;">#{{ $job->id }}</td>
                    <td style="font-size: 0.85rem;">
                        {{ $job->template_name ?? $job->printer_name ?? '—' }}
                        @if($job->reference_id)
                            <div style="font-size: 0.7rem; color: var(--text-muted);">ref: {{ $job->reference_id }}</div>
                        @endif
                    </td>
                    <td style="font-size: 0.85rem; color: var(--text-muted);">{{ $job->agent->name ?? '—' }}</td>
                    <td>
                        <span class="badge badge-{{ $severity === 'danger' ? 'danger' : ($severity === 'warning' ? 'warning' : 'info') }}">
                            ⏳ {{ ucfirst($job->status) }}
                        </span>
                    </td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $job->created_at->format('M j, H:i') }}</td>
                    <td>
                        <span class="badge badge-{{ $severity === 'danger' ? 'danger' : ($severity === 'warning' ? 'warning' : 'info') }}">
                            {{ $job->created_at->diffForHumans(now(), true) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding: 0.5rem 1rem 0.75rem; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border);">
        ⓘ Jobs in <strong>pending</strong> or <strong>queued</strong> status for more than 1 hour.
        <a href="{{ route('admin.jobs', ['status' => 'pending']) }}" style="color: var(--primary);">View all pending jobs →</a>
    </div>
</div>
@endif

{{-- Recent Failures Widget (Task 2.7) --}}
<div class="card" style="margin-bottom: 1.5rem; {{ $recentFailures->count() > 0 ? 'border-color: var(--danger);' : '' }}">
    <div class="card-header">
        <h2>❌ Recent Failures</h2>
        <div style="display: flex; gap: 0.5rem;">
            @if($recentFailures->count() > 0)
            <form action="{{ route('admin.jobs.retry-all-failed') }}" method="POST" style="display:inline;" onsubmit="return confirm('Retry ALL failed jobs?')">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm">🔄 Retry All Failed</button>
            </form>
            @endif
            <a href="{{ route('admin.jobs', ['status' => 'failed']) }}" class="btn btn-primary btn-sm">View All Failed</a>
        </div>
    </div>
    @if($recentFailures->count() > 0)
    <div style="overflow-x: auto;">
        <table role="table">
            <caption class="sr-only">Recent failed jobs</caption>
            <thead>
                <tr>
                    <th scope="col">Job ID</th>
                    <th scope="col">Agent</th>
                    <th scope="col">Printer</th>
                    <th scope="col">Error</th>
                    <th scope="col">Time</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recentFailures as $job)
                <tr>
                    <td><code class="mono" style="font-size: 0.75rem;">{{ $job->job_id }}</code></td>
                    <td style="font-size: 0.8rem;">{{ $job->agent->name ?? '—' }}</td>
                    <td style="font-size: 0.75rem; color: var(--text-muted);">{{ $job->printer_name }}</td>
                    <td style="font-size: 0.75rem; color: var(--danger); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $job->error }}">
                        {{ Str::limit($job->error, 60) ?? '—' }}
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.75rem; white-space: nowrap;">{{ $job->created_at->diffForHumans() }}</td>
                    <td>
                        <form action="{{ route('admin.jobs.retry', $job) }}" method="POST" style="display:inline;" onsubmit="return confirm('Retry this job?')">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="padding: 2px 6px; font-size: 0.65rem; background: var(--primary); color: white; border: none; border-radius: 3px; cursor: pointer;" title="Retry this job">Retry</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
        ✅ No recent failures. All jobs running smoothly.
    </div>
    @endif
</div>

{{-- Print Reduction Goals (Task 8) --}}
@if(count($branchGoals) > 0)
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header">
        <h2>🎯 Print Reduction Goals — {{ now()->format('F Y') }}</h2>
        <a href="{{ route('admin.branches') }}" class="btn btn-primary btn-sm">Manage Branches</a>
    </div>
    <div style="overflow-x: auto;">
        <table role="table">
            <caption class="sr-only">Monthly print reduction goals per branch</caption>
            <thead>
                <tr>
                    <th scope="col">Branch</th>
                    <th scope="col">Company</th>
                    <th scope="col">Monthly Goal</th>
                    <th scope="col">Current Usage</th>
                    <th scope="col">Progress</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($branchGoals as $bg)
                <tr>
                    <td><strong>{{ $bg['branch']->name }}</strong></td>
                    <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $bg['company_name'] }}</td>
                    <td style="font-size: 0.85rem;">{{ number_format($bg['goal']) }} pages</td>
                    <td style="font-size: 0.85rem;">{{ number_format($bg['current_usage']) }} pages</td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <div style="flex: 1; height: 8px; background: var(--bg); border-radius: 4px; overflow: hidden; min-width: 120px;">
                                @php
                                    $barWidth = min($bg['percentage'], 100);
                                    $barColor = $bg['on_track'] ? 'var(--success)' : 'var(--danger)';
                                @endphp
                                <div style="height: 100%; width: {{ $barWidth }}%; background: {{ $barColor }}; border-radius: 4px; transition: width 0.5s ease;"></div>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">{{ $bg['percentage'] }}%</span>
                        </div>
                    </td>
                    <td>
                        @if($bg['on_track'])
                            <span class="badge badge-success">✅ On Track</span>
                        @else
                            <span class="badge badge-danger">⚠️ Needs Attention</span>
                            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">
                                Expected: ~{{ $bg['expected_progress'] }}%
                            </div>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div style="padding: 0.5rem 1rem 0.75rem; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border);">
        ⓘ Comparing current month's successful print jobs against the monthly page goal.
        <a href="{{ route('admin.branches') }}" style="color: var(--primary);">Set goals for your branches →</a>
    </div>
</div>
@else
<div class="card" style="margin-bottom: 1.5rem; border-color: var(--info); opacity: 0.7;">
    <div class="card-header">
        <h2>🎯 Print Reduction Goals</h2>
        <a href="{{ route('admin.branches') }}" class="btn btn-primary btn-sm">Set Goals</a>
    </div>
    <div style="text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem;">
        No print reduction goals configured. Set a <strong>monthly page goal</strong> for your branches to track printing reduction progress.
    </div>
</div>
@endif

{{-- Main Content Grid --}}
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;" id="dashboard-grid">

    {{-- Agents --}}
    <div class="card">
        <div class="card-header">
            <h2>Agents</h2>
            <a href="{{ route('admin.agents') }}" class="btn btn-primary btn-sm">Manage</a>
        </div>
        <table>
            <thead>
                <tr><th>Name</th><th>Status</th><th>Jobs</th><th>Last Seen</th></tr>
            </thead>
            <tbody id="agents-table-body">
                @forelse($agents as $agent)
                <tr>
                    <td>
                        {{ $agent->name }}
                        @if($agent->branch)
                            <div style="font-size: 0.72rem; color: var(--text-muted);">{{ $agent->branch->name ?? '' }}</div>
                        @endif
                    </td>
                    <td>
                        @if($agent->isOnline())
                            <span class="dot dot-green"></span><span class="badge badge-success">Online</span>
                        @else
                            <span class="dot dot-red"></span><span class="badge badge-danger">Offline</span>
                        @endif
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;">{{ $agent->jobs_count }}</td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;">
                        {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="color: var(--text-muted);">No agents registered</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Job Status Chart + Recent Jobs --}}
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">

        {{-- Mini Status Chart --}}
        @if($stats['total_jobs'] > 0)
        <div class="card">
            <div class="card-header">
                <h2>Job Status</h2>
                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ number_format($stats['total_jobs']) }} total</span>
            </div>
            @php
                $total      = $stats['total_jobs'];
                $byStatus   = $stats['jobs_by_status'];
                $barData = [
                    ['label' => 'Success',    'key' => 'success',    'color' => 'var(--success)', 'count' => $byStatus['success']    ?? 0],
                    ['label' => 'Failed',     'key' => 'failed',     'color' => 'var(--danger)',  'count' => $byStatus['failed']     ?? 0],
                    ['label' => 'Pending',    'key' => 'pending',    'color' => 'var(--warning)', 'count' => $byStatus['pending']    ?? 0],
                    ['label' => 'Processing', 'key' => 'processing', 'color' => 'var(--info)',    'count' => $byStatus['processing'] ?? 0],
                ];
            @endphp
            {{-- Stacked bar --}}
            <div style="display: flex; height: 10px; border-radius: 5px; overflow: hidden; margin-bottom: 1rem; gap: 1px;">
                @foreach($barData as $bar)
                    @if($bar['count'] > 0)
                        <div style="background: {{ $bar['color'] }}; width: {{ round(($bar['count'] / $total) * 100, 1) }}%; transition: width 0.5s ease;" title="{{ $bar['label'] }}: {{ $bar['count'] }}"></div>
                    @endif
                @endforeach
            </div>
            {{-- Legend --}}
            <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                @foreach($barData as $bar)
                <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.78rem;">
                    <span style="width: 10px; height: 10px; border-radius: 2px; background: {{ $bar['color'] }}; display: inline-block;"></span>
                    <span style="color: var(--text-muted);">{{ $bar['label'] }}</span>
                    <strong>{{ number_format($bar['count']) }}</strong>
                    <span style="color: var(--text-muted);">({{ $total > 0 ? round(($bar['count'] / $total) * 100) : 0 }}%)</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Recent Jobs --}}
        <div class="card">
            <div class="card-header">
                <h2>Recent Jobs</h2>
                <a href="{{ route('admin.jobs') }}" class="btn btn-primary btn-sm">View All</a>
            </div>
            <table>
                <thead>
                    <tr><th>Template</th><th>Agent</th><th>Status</th><th>Time</th></tr>
                </thead>
                <tbody>
                    @forelse($recentJobs->take(8) as $job)
                    <tr>
                        <td style="font-size: 0.8rem;">
                            {{ $job->template_name ?? $job->printer_name }}
                            @if($job->reference_id)
                                <div style="font-size: 0.7rem; color: var(--text-muted);">ref: {{ $job->reference_id }}</div>
                            @endif
                        </td>
                        <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $job->agent->name ?? '—' }}</td>
                        <td>
                            @switch($job->status)
                                @case('success')
                                    <span class="badge badge-success">✓ Done</span>
                                    @break
                                @case('failed')
                                    <span class="badge badge-danger">✗ Failed</span>
                                    @break
                                @case('pending')
                                    <span class="badge badge-warning">⏳ Pending</span>
                                    @break
                                @case('processing')
                                    <span class="badge badge-info">⚙ Processing</span>
                                    @break
                                @default
                                    <span class="badge">{{ $job->status }}</span>
                            @endswitch
                        </td>
                        <td style="color: var(--text-muted); font-size: 0.75rem;">{{ $job->created_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="color: var(--text-muted);">No jobs yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </div>

</div>

<style>
#dashboard-grid { margin-top: 0; }
@keyframes pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 6px var(--success); }
    50%       { opacity: 0.5; box-shadow: none; }
}
@media (max-width: 768px) {
    #dashboard-grid { grid-template-columns: 1fr !important; }
}
</style>

<script>
// Auto-refresh dashboard stats every 30 seconds
(function() {
    let timer;
    function refresh() {
        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc    = parser.parseFromString(html, 'text/html');

                // Refresh stat values
                ['stat-total-agents','stat-online-agents','stat-offline-agents','stat-total-profiles',
                 'stat-today-jobs','stat-pending-jobs','stat-failed-jobs','stat-success-rate'].forEach(id => {
                    const el    = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl && el.textContent !== newEl.textContent) {
                        el.textContent = newEl.textContent;
                        el.style.transition = 'color 0.3s';
                        el.style.color = 'var(--primary-hover)';
                        setTimeout(() => el.style.color = '', 800);
                    }
                });
            })
            .catch(() => {/* silent fail */});
    }

    timer = setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { clearInterval(timer); }
        else                 { refresh(); timer = setInterval(refresh, 30000); }
    });
})();
</script>
@endsection
