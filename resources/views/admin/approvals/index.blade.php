@extends('admin.layout')
@section('title', 'Pending Approvals')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Approvals']]" />

<div class="page-header">
    <h1>✅ Pending Approvals</h1>
    <p>Print jobs requiring manual approval before processing</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Pending Jobs ({{ $pendingJobs->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Agent</th>
                <th>Printer</th>
                <th>Type</th>
                <th>Reference</th>
                <th>Template</th>
                <th>Submitted At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pendingJobs as $job)
            <tr>
                <td><code class="mono">{{ $job->job_id }}</code></td>
                <td>{{ $job->agent?->name ?? '—' }}</td>
                <td style="font-size:0.8rem;">{{ $job->printer_name }}</td>
                <td>
                    <span class="badge badge-info">{{ strtoupper($job->type) }}</span>
                </td>
                <td style="font-size:0.8rem; color:var(--text-muted);">{{ $job->reference_id ?? '—' }}</td>
                <td style="font-size:0.8rem;">{{ $job->template_name ?? '—' }}</td>
                <td style="color:var(--text-muted); font-size:0.8rem; white-space:nowrap;">
                    {{ $job->created_at->format('d M H:i') }}
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <form method="POST" action="{{ route('admin.approvals.approve', $job->id) }}"
                              style="display:inline;">
                            @csrf
                            <button type="submit" class="btn btn-sm"
                                    style="background:var(--success); color:white; border:none; padding:0.3rem 0.8rem; border-radius:4px; cursor:pointer;"
                                    onclick="return confirm('Approve this job for printing?')">✓ Approve</button>
                        </form>
                        <button type="button" class="btn btn-sm btn-danger"
                                onclick="showRejectModal({{ $job->id }}, '{{ $job->job_id }}')">✗ Reject</button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-empty-state icon="✅" title="No pending approvals" description="All jobs are auto-approved. Configure approval rules to require manual approval for certain jobs." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($pendingJobs->hasPages())
    <div class="pagination">
        @if($pendingJobs->onFirstPage())
            <span>← Prev</span>
        @else
            <a href="{{ $pendingJobs->previousPageUrl() }}">← Prev</a>
        @endif

        @foreach($pendingJobs->getUrlRange(1, $pendingJobs->lastPage()) as $page => $url)
            @if($page == $pendingJobs->currentPage())
                <span class="active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($pendingJobs->hasMorePages())
            <a href="{{ $pendingJobs->nextPageUrl() }}">Next →</a>
        @else
            <span>Next →</span>
        @endif
    </div>
    @endif
</div>

{{-- Reject Modal --}}
<div id="reject-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2rem; width:400px; max-width:90vw;">
        <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:0.5rem;">Reject Job</h2>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;" id="reject-job-info">Job: —</p>
        <form method="POST" action="" id="reject-form">
            @csrf
            <div class="form-group">
                <label>Reason <span style="font-weight:normal; color:var(--text-muted);">(optional)</span></label>
                <textarea name="reason" rows="3" placeholder="Why is this job being rejected?" style="resize:vertical;"></textarea>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.5rem;">
                <button type="button" onclick="document.getElementById('reject-modal').style.display='none'"
                        style="padding:0.6rem 1.25rem; background:transparent; border:1px solid var(--border); color:var(--text); border-radius:8px; cursor:pointer;">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Job</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRejectModal(jobId, jobIdStr) {
        document.getElementById('reject-job-info').textContent = 'Job: ' + jobIdStr;
        document.getElementById('reject-form').action = '{{ url('/admin/approvals') }}/' + jobId + '/reject';
        document.getElementById('reject-modal').style.display = 'flex';
    }
</script>
@endsection
