@extends('admin.layout')
@section('title', 'Scheduled Jobs')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Scheduled Jobs']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Scheduled & Recurring Jobs</h1>
        <p>Manage print jobs that are scheduled for future printing or recur on a schedule.</p>
    </div>
    <div>
        <a href="{{ route('admin.scheduled-jobs.create') }}" class="btn btn-primary">
            + Schedule New Job
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form action="{{ route('admin.scheduled-jobs.index') }}" method="GET" style="display:flex; gap:0.75rem; align-items:center; width:100%; flex-wrap: wrap;">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>📅 Scheduled</option>
            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>📋 Queued</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>✓ Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>✗ Failed</option>
        </select>
        <select name="recurrence">
            <option value="">All Types</option>
            <option value="none" {{ request('recurrence') === 'none' ? 'selected' : '' }}>One-Time</option>
            <option value="daily" {{ request('recurrence') === 'daily' ? 'selected' : '' }}>🔄 Daily</option>
            <option value="weekly" {{ request('recurrence') === 'weekly' ? 'selected' : '' }}>🔄 Weekly</option>
            <option value="monthly" {{ request('recurrence') === 'monthly' ? 'selected' : '' }}>🔄 Monthly</option>
        </select>
        <input type="text" name="search" placeholder="Search by ID, template, reference..." value="{{ request('search') }}"
               style="padding: 6px 10px; font-size: 0.8rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 6px; min-width: 200px;">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.scheduled-jobs.index') }}" class="btn btn-sm" style="color: var(--text-muted);">Clear</a>
        <div style="margin-left: auto;">
            <select name="per_page" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 0.75rem; width: auto;">
                <option value="10" {{ (request('per_page', 25) == 10) ? 'selected' : '' }}>10</option>
                <option value="25" {{ (request('per_page', 25) == 25) ? 'selected' : '' }}>25</option>
                <option value="50" {{ (request('per_page', 25) == 50) ? 'selected' : '' }}>50</option>
                <option value="100" {{ (request('per_page', 25) == 100) ? 'selected' : '' }}>100</option>
            </select>
        </div>
    </form>
</div>

{{-- Scheduled Jobs List --}}
<div class="card">
    <div class="card-header">
        <h2>Scheduled Jobs ({{ $scheduledJobs->total() }})</h2>
    </div>
    <div style="overflow-x: auto;">
        <table role="table">
            <caption class="sr-only">Scheduled print jobs list</caption>
            <thead>
                <tr>
                    <th scope="col">Job ID</th>
                    <th scope="col">Template / Profile</th>
                    <th scope="col">Agent</th>
                    <th scope="col">Printer</th>
                    <th scope="col">Schedule</th>
                    <th scope="col">Recurrence</th>
                    <th scope="col">Next Run</th>
                    <th scope="col">Status</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($scheduledJobs as $job)
                <tr>
                    <td><code class="mono" style="font-size: 0.7rem;">{{ $job->job_id }}</code></td>
                    <td>
                        <strong>{{ $job->template_name ?? '—' }}</strong>
                        @if($job->reference_id)
                            <div style="font-size: 0.7rem; color: var(--text-muted);">ref: {{ $job->reference_id }}</div>
                        @endif
                    </td>
                    <td style="font-size: 0.8rem;">{{ $job->agent->name ?? '—' }}</td>
                    <td style="font-size: 0.75rem; color: var(--text-muted);">{{ $job->printer_name ?? '—' }}</td>
                    <td style="font-size: 0.8rem; white-space: nowrap;">
                        {{ $job->scheduled_at ? $job->scheduled_at->format('M j, H:i') : '—' }}
                    </td>
                    <td>
                        @if($job->recurrence && $job->recurrence !== 'none')
                            <span class="badge badge-info">
                                🔄 {{ ucfirst($job->recurrence) }}
                            </span>
                        @else
                            <span class="badge" style="color: var(--text-muted);">One-Time</span>
                        @endif
                    </td>
                    <td style="font-size: 0.8rem; white-space: nowrap;">
                        @if($job->status === 'scheduled' && $job->scheduled_at)
                            @if($job->scheduled_at->isFuture())
                                <span style="color: var(--info);">{{ $job->scheduled_at->diffForHumans() }}</span>
                            @else
                                <span style="color: var(--warning);">Due now</span>
                            @endif
                        @elseif($job->recurrence && $job->recurrence !== 'none' && $job->status === 'queued')
                            <span style="color: var(--success);">Running</span>
                        @else
                            <span style="color: var(--text-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        @switch($job->status)
                            @case('scheduled')
                                <span class="badge badge-info">📅 Scheduled</span>
                                @break
                            @case('queued')
                                <span class="badge badge-warning">📋 Queued</span>
                                @break
                            @case('processing')
                                <span class="badge badge-info">⚙ Processing</span>
                                @break
                            @case('success')
                                <span class="badge badge-success">✓ Done</span>
                                @break
                            @case('failed')
                                <span class="badge badge-danger">✗ Failed</span>
                                @break
                            @default
                                <span class="badge">{{ $job->status }}</span>
                        @endswitch
                    </td>
                    <td>
                        <div style="display: flex; gap: 4px;">
                            <form action="{{ route('admin.scheduled-jobs.destroy', $job) }}" method="POST"
                                  onsubmit="return confirm('Cancel this scheduled job? This action cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" title="Cancel scheduled job">Cancel</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <x-empty-state icon="📅" title="No scheduled jobs"
                            description="Schedule a print job to run at a specific time or on a recurring basis." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($scheduledJobs->hasPages())
        <div class="pagination">
            {{ $scheduledJobs->links() }}
        </div>
    @endif
</div>
@endsection
