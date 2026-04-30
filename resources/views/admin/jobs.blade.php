@extends('admin.layout')
@section('title', 'Job History')

@section('content')
<div class="page-header">
    <h1>Print Job History</h1>
    <p>All print jobs reported by agents across your organization</p>
</div>

{{-- Filters --}}
<div class="filter-bar">
    <form action="{{ route('admin.jobs') }}" method="GET" style="display:flex; gap:0.75rem; align-items:center; width:100%;">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>✓ Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>✗ Failed</option>
        </select>
        <select name="agent_id">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.jobs') }}" class="btn btn-sm" style="color: var(--text-muted);">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>Jobs ({{ $jobs->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Agent</th>
                <th>Printer</th>
                <th>Type</th>
                <th>Status</th>
                <th>Options</th>
                <th>Error</th>
                <th>Time</th>
            </tr>
        </thead>
        <tbody>
            @forelse($jobs as $job)
            <tr id="job-row-{{ $job->job_id }}" style="transition: background-color 0.5s;">
                <td><code class="mono">{{ $job->job_id }}</code></td>
                <td>{{ $job->agent->name ?? '—' }}</td>
                <td style="font-size: 0.8rem;">{{ $job->printer_name }}</td>
                <td>
                    <span class="badge badge-info">{{ strtoupper($job->type) }}</span>
                    @if($job->file_path)
                        <br><a href="{{ route('admin.jobs.download', $job) }}" style="font-size: 0.7rem; color: var(--primary); text-decoration: underline;" target="_blank">View PDF</a>
                    @endif
                </td>
                <td id="job-status-{{ $job->job_id }}">
                    @if($job->status === 'success')
                        <span class="badge badge-success">✓ Success</span>
                    @elseif($job->status === 'failed')
                        <span class="badge badge-danger">✗ Failed</span>
                        <form action="{{ route('admin.jobs.retry', $job) }}" method="POST" style="display:inline; margin-left: 5px;">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="padding: 2px 5px; font-size: 0.65rem; background: var(--primary); color: white; border: none; border-radius: 3px; cursor: pointer;" title="Retry this job">
                                Retry
                            </button>
                        </form>
                    @else
                        <span class="badge badge-warning">{{ $job->status }}</span>
                        <form action="{{ route('admin.jobs.status', $job) }}" method="POST" style="display:inline; margin-left: 5px;">
                            @csrf
                            <input type="hidden" name="status" value="success">
                            <button type="submit" class="btn btn-sm" style="padding: 2px 5px; font-size: 0.65rem; background: var(--success); color: white; border: none; border-radius: 3px; cursor: pointer;" title="Manually mark as success">
                                Mark Success
                            </button>
                        </form>
                    @endif
                </td>
                <td style="font-size: 0.75rem; color: var(--text-muted);">
                    @if($job->webhook_url)<div>Webhook: yes</div>@endif
                    @if($job->options)
                        @foreach($job->options as $k => $v)
                            {{ $k }}={{ $v }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    @endif
                </td>
                <td id="job-error-{{ $job->job_id }}" style="font-size: 0.75rem; color: var(--danger); max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                    {{ $job->error ?? '—' }}
                </td>
                <td style="color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;">
                    {{ $job->created_at->format('d M H:i') }}
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="color: var(--text-muted);">No jobs found.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($jobs->hasPages())
    <div class="pagination">
        @if($jobs->onFirstPage())
            <span>← Prev</span>
        @else
            <a href="{{ $jobs->previousPageUrl() }}">← Prev</a>
        @endif

        @foreach($jobs->getUrlRange(1, $jobs->lastPage()) as $page => $url)
            @if($page == $jobs->currentPage())
                <span class="active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($jobs->hasMorePages())
            <a href="{{ $jobs->nextPageUrl() }}">Next →</a>
        @else
            <span>Next →</span>
        @endif
    </div>
    @endif
    @endif
</div>

<script type="module">
    if (window.Echo) {
        window.Echo.channel('print-jobs')
            .listen('.job.status.updated', (e) => {
                const row = document.getElementById('job-row-' + e.job_id);
                if (row) {
                    const statusTd = document.getElementById('job-status-' + e.job_id);
                    const errorTd = document.getElementById('job-error-' + e.job_id);
                    
                    if (e.status === 'success') {
                        statusTd.innerHTML = '<span class="badge badge-success">✓ Success</span>';
                        row.style.backgroundColor = 'rgba(34, 197, 94, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                        if (errorTd) errorTd.innerText = '—';
                    } else if (e.status === 'failed') {
                        statusTd.innerHTML = '<span class="badge badge-danger">✗ Failed</span>';
                        row.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                        if (errorTd) errorTd.innerText = e.error || '—';
                    } else {
                        statusTd.innerHTML = '<span class="badge badge-warning">' + e.status + '</span>';
                        row.style.backgroundColor = 'rgba(245, 158, 11, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                    }
                }
            });
    }
</script>
@endsection
