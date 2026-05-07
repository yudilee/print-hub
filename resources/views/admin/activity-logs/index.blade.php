@extends('admin.layout')
@section('title', 'Activity Log')

@section('content')
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Activity Log</h1>
        <p>Audit trail of all system actions</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="{{ route('admin.activity-logs.export', request()->query()) }}" class="btn btn-primary btn-sm">
            ⬇ Export CSV
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="card" style="padding: 1rem;">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: end; flex-wrap: wrap;">
        {{-- Entity Type --}}
        <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
            <label>Entity Type</label>
            <select name="loggable_type">
                <option value="">All Entities</option>
                @foreach($entityTypes as $type)
                    <option value="App\Models\{{ $type }}" {{ request('loggable_type') == "App\Models\\{$type}" ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Event --}}
        <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
            <label>Event</label>
            <select name="event">
                <option value="">All Events</option>
                @foreach($events as $event)
                    <option value="{{ $event }}" {{ request('event') == $event ? 'selected' : '' }}>{{ $event }}</option>
                @endforeach
            </select>
        </div>

        {{-- Action (existing) --}}
        <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
            <label>Action</label>
            <select name="action">
                <option value="">All Actions</option>
                @foreach($actionTypes as $action)
                    <option value="{{ $action }}" {{ request('action') == $action ? 'selected' : '' }}>{{ $action }}</option>
                @endforeach
            </select>
        </div>

        {{-- User --}}
        <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
            <label>User</label>
            <select name="user_id">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        {{-- Branch (existing) --}}
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

        {{-- Date From --}}
        <div class="form-group" style="margin-bottom: 0;">
            <label>From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}">
        </div>

        {{-- Date To --}}
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
        <h2>Activity Records ({{ $logs->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th style="width: 40px;"></th>
                <th>Time</th>
                <th>User</th>
                <th>Branch</th>
                <th>Action</th>
                <th>Entity</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
            <tr class="expandable" onclick="toggleLogDetail({{ $log->id }})" style="cursor: pointer;">
                <td>
                    <span class="expandable-arrow" id="arrow-{{ $log->id }}" style="color: var(--text-muted); font-size: 0.8rem;">▸</span>
                </td>
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
                            'login'   => 'var(--primary)',
                            'logout'  => 'var(--text-muted)',
                        ];
                        $actionWord = last(explode('.', $log->action));
                        $color = $actionColors[$actionWord] ?? 'var(--text-muted)';
                    @endphp
                    <span class="mono" style="color: {{ $color }};">{{ $log->action }}</span>
                </td>
                <td style="font-size: 0.8rem;">
                    @if($log->subject_type)
                        <span class="badge badge-info" style="font-size: 0.65rem;">
                            {{ class_basename($log->subject_type) }}
                        </span>
                        @if($log->subject_id)
                            <code style="font-size: 0.7rem;">#{{ $log->subject_id }}</code>
                        @endif
                    @else
                        <span style="color: var(--text-muted);">—</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    @if($log->properties)
                        @foreach(array_slice($log->properties, 0, 3) as $key => $value)
                            <span style="color: var(--text);">{{ $key }}:</span>
                            {{ is_array($value) ? json_encode($value) : $value }}{{ !$loop->last ? ', ' : '' }}
                        @endforeach
                        @if(count($log->properties) > 3)
                            <span style="color: var(--text-muted);">…</span>
                        @endif
                    @else
                        —
                    @endif
                </td>
            </tr>
            {{-- Expanded detail row --}}
            <tr id="detail-{{ $log->id }}" class="expandable-content" style="display: none;">
                <td colspan="7" style="padding: 0.5rem 1rem 1rem 3rem; background: var(--bg);">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.8rem;">
                        <div>
                            <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Properties</strong>
                            @if($log->properties && count($log->properties) > 0)
                                <pre style="background: var(--surface); padding: 0.75rem; border-radius: 6px; font-size: 0.75rem; overflow-x: auto; max-height: 300px; overflow-y: auto; border: 1px solid var(--border);">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            @else
                                <span style="color: var(--text-muted); font-style: italic;">No properties</span>
                            @endif
                        </div>
                        <div>
                            <strong style="color: var(--text-muted); display: block; margin-bottom: 0.25rem;">Metadata</strong>
                            <table style="font-size: 0.75rem; width: auto;">
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">ID</td><td><code class="mono">{{ $log->id }}</code></td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">IP Address</td><td><code class="mono">{{ $log->ip_address ?? '—' }}</code></td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">User Agent</td><td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $log->user_agent ?? '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Subject</td><td>{{ $log->subject_type ? class_basename($log->subject_type) . ' #' . $log->subject_id : '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Timestamp</td><td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td></tr>
                            </table>
                        </div>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align: center; color: var(--text-muted);">No activity recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($logs->hasPages())
        <div class="pagination" style="margin-top: 1rem;">
            {{ $logs->appends(request()->query())->links() }}
        </div>
    @endif
</div>

<script>
function toggleLogDetail(id) {
    const detailRow = document.getElementById('detail-' + id);
    const arrow = document.getElementById('arrow-' + id);
    if (!detailRow) return;

    const isOpen = detailRow.style.display !== 'none';
    detailRow.style.display = isOpen ? 'none' : 'table-row';
    if (arrow) arrow.textContent = isOpen ? '▸' : '▾';
}
</script>
@endsection
