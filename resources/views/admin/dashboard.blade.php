@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
<div class="page-header">
    <h1>Dashboard</h1>
    <p>Overview of your print infrastructure</p>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info);">{{ $stats['total_agents'] }}</div>
        <div class="stat-label">Total Agents</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);">{{ $stats['online_agents'] }}</div>
        <div class="stat-label">Online Now</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--primary);">{{ $stats['total_profiles'] }}</div>
        <div class="stat-label">Virtual Queues</div>
    </div>
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total_jobs'] }}</div>
        <div class="stat-label">Total Jobs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--danger);">{{ $stats['failed_jobs'] }}</div>
        <div class="stat-label">Failed Jobs</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">

    {{-- Agents --}}
    <div class="card">
        <div class="card-header">
            <h2>Agents</h2>
            <a href="{{ route('admin.agents') }}" class="btn btn-primary btn-sm">Manage</a>
        </div>
        <table>
            <thead>
                <tr><th>Name</th><th>Status</th><th>Last Seen</th></tr>
            </thead>
            <tbody>
                @forelse($agents as $agent)
                <tr>
                    <td>{{ $agent->name }}</td>
                    <td>
                        @if($agent->isOnline())
                            <span class="dot dot-green"></span><span class="badge badge-success">Online</span>
                        @else
                            <span class="dot dot-red"></span><span class="badge badge-danger">Offline</span>
                        @endif
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;">
                        {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" style="color: var(--text-muted);">No agents registered</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Recent Jobs --}}
    <div class="card">
        <div class="card-header">
            <h2>Recent Jobs</h2>
            <a href="{{ route('admin.jobs') }}" class="btn btn-primary btn-sm">View All</a>
        </div>
        <table>
            <thead>
                <tr><th>Agent</th><th>Printer</th><th>Status</th><th>Time</th></tr>
            </thead>
            <tbody>
                @forelse($recentJobs->take(10) as $job)
                <tr>
                    <td>{{ $job->agent->name ?? '—' }}</td>
                    <td style="font-size: 0.8rem;">{{ $job->printer_name }}</td>
                    <td>
                        @if($job->status === 'success')
                            <span class="badge badge-success">✓ Success</span>
                        @else
                            <span class="badge badge-danger">✗ Failed</span>
                        @endif
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.8rem;">{{ $job->created_at->diffForHumans() }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="color: var(--text-muted);">No jobs yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
