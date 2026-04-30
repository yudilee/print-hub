@extends('admin.layout')
@section('title', 'Activity Log')

@section('content')
<div class="page-header">
    <h1>Activity Log</h1>
    <p>Audit trail of all system actions.</p>
</div>

{{-- Filters --}}
<div class="card" style="padding: 1rem;">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
            <label>Action</label>
            <select name="action">
                <option value="">All Actions</option>
                @foreach($actionTypes as $action)
                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>{{ $action }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
            <label>Branch</label>
            <select name="branch_id">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->company->code ?? '' }} / {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label>To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.activity-logs') }}" class="btn btn-secondary btn-sm" style="text-decoration: none;">Clear</a>
    </form>
</div>

{{-- Log Table --}}
<div class="card">
    <div class="card-header">
        <h2>Recent Activity ({{ $logs->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Time</th>
                <th>User</th>
                <th>Branch</th>
                <th>Action</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr>
                <td style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap;">
                    {{ $log->created_at->format('M d, H:i:s') }}
                </td>
                <td>
                    @if($log->user)
                        {{ $log->user->name }}
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">System</span>
                    @endif
                </td>
                <td>
                    @if($log->branch)
                        <span class="badge badge-info" style="font-size: 0.65rem;">{{ $log->branch->name }}</span>
                    @else
                        <span style="color: var(--text-muted);">—</span>
                    @endif
                </td>
                <td>
                    @php
                        $actionColors = [
                            'created' => 'var(--success)',
                            'updated' => 'var(--info)',
                            'deleted' => 'var(--danger)',
                            'retried' => 'var(--warning)',
                        ];
                        $actionWord = last(explode('.', $log->action));
                        $color = $actionColors[$actionWord] ?? 'var(--text-muted)';
                    @endphp
                    <span class="mono" style="color: {{ $color }};">{{ $log->action }}</span>
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                    @if($log->properties)
                        @foreach($log->properties as $key => $value)
                            <span style="color: var(--text);">{{ $key }}:</span> {{ is_array($value) ? json_encode($value) : $value }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                    @else
                        —
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No activity recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($logs->hasPages())
        <div class="pagination" style="margin-top: 1rem;">
            {{ $logs->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection
