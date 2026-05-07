@extends('admin.layout')
@section('title', 'Webhook Deliveries — ' . $clientApp->name)

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Webhook Settings', 'url' => route('admin.webhooks.index')],
    ['label' => $clientApp->name . ' Deliveries'],
]" />

<div class="page-header">
    <h1>Webhook Deliveries</h1>
    <p>
        Delivery history for <strong>{{ $clientApp->name }}</strong>
        @if($clientApp->webhook_url)
            — <code class="mono" style="font-size: 0.75rem;">{{ $clientApp->webhook_url }}</code>
        @endif
    </p>
</div>

{{-- Summary Stats --}}
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value">{{ $stats['total'] ?? $deliveries->total() }}</div>
        <div class="stat-label">Total</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--success);">{{ $stats['success'] ?? 0 }}</div>
        <div class="stat-label">Successful</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--danger);">{{ $stats['failed'] ?? 0 }}</div>
        <div class="stat-label">Failed</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--warning);">{{ $stats['retrying'] ?? 0 }}</div>
        <div class="stat-label">Retrying</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: var(--info);">{{ $stats['pending'] ?? 0 }}</div>
        <div class="stat-label">Pending</div>
    </div>
</div>

{{-- Filters & Actions --}}
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
        <h2>Delivery Log</h2>
        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
            {{-- Bulk Retry Button --}}
            @if(($stats['failed'] ?? 0) > 0 || ($stats['retrying'] ?? 0) > 0)
            <form action="{{ route('admin.webhooks.deliveries.bulk-retry', $clientApp) }}" method="POST" style="display: inline;" data-loading>
                @csrf
                <button type="submit" class="btn btn-warning btn-sm"
                        onclick="return confirm('Retry all failed/retrying deliveries for {{ e($clientApp->name) }}?')">
                    🔄 Bulk Retry ({{ ($stats['failed'] ?? 0) + ($stats['retrying'] ?? 0) }})
                </button>
            </form>
            @endif
            {{-- CSV Export Button --}}
            <a href="{{ route('admin.webhooks.deliveries.export-csv', $clientApp) }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
               class="btn btn-secondary btn-sm">
                ⬇ CSV Export
            </a>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.webhooks.deliveries', $clientApp) }}" class="filter-bar">
        <select name="status">
            <option value="">All Statuses</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
            <option value="retrying" {{ request('status') === 'retrying' ? 'selected' : '' }}>Retrying</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
        </select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" placeholder="From date"
               style="width: auto; min-width: 140px;">
        <input type="date" name="date_to" value="{{ request('date_to') }}" placeholder="To date"
               style="width: auto; min-width: 140px;">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.webhooks.deliveries', $clientApp) }}" class="btn btn-secondary btn-sm">Clear</a>
    </form>

    <table id="deliveries-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Event Type</th>
                <th>Status</th>
                <th>HTTP Code</th>
                <th>Attempts</th>
                <th>Duration</th>
                <th>Attempted At</th>
                <th>Response Preview</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($deliveries as $delivery)
            <tr data-delivery-id="{{ $delivery->id }}" data-status="{{ $delivery->status }}">
                <td><code style="font-size: 0.75rem;">#{{ $delivery->id }}</code></td>
                <td>
                    <span class="badge badge-info" style="font-size: 0.7rem;">{{ $delivery->event_type }}</span>
                </td>
                <td>
                    @if($delivery->status === 'success')
                        <span class="badge badge-success">Success</span>
                    @elseif($delivery->status === 'failed')
                        <span class="badge badge-danger">Failed</span>
                    @elseif($delivery->status === 'retrying')
                        <span class="badge badge-warning">Retrying</span>
                    @elseif($delivery->status === 'pending')
                        <span class="badge badge-info">Pending</span>
                    @else
                        <span class="badge">{{ ucfirst($delivery->status) }}</span>
                    @endif
                </td>
                <td>
                    @if($delivery->response_code)
                        <code style="font-size: 0.8rem;">{{ $delivery->response_code }}</code>
                    @else
                        <span style="color: var(--text-muted);">—</span>
                    @endif
                </td>
                <td style="font-size: 0.85rem;">
                    {{ $delivery->attempts }}/{{ $delivery->max_attempts }}
                </td>
                <td style="font-size: 0.8rem; white-space: nowrap;">
                    @php
                        $duration = $delivery->delivery_duration ?? ($delivery->created_at && $delivery->updated_at
                            ? $delivery->created_at->diffInSeconds($delivery->updated_at)
                            : null);
                    @endphp
                    @if($duration !== null)
                        <span class="duration-badge" title="Delivery duration">
                            @if($duration < 60)
                                {{ $duration }}s
                            @elseif($duration < 3600)
                                {{ floor($duration / 60) }}m {{ $duration % 60 }}s
                            @else
                                {{ floor($duration / 3600) }}h {{ floor(($duration % 3600) / 60) }}m
                            @endif
                        </span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                    @if($delivery->last_attempt_at)
                        {{ $delivery->last_attempt_at->format('Y-m-d H:i:s') }}
                        <br><span style="font-size: 0.7rem;">{{ $delivery->last_attempt_at->diffForHumans() }}</span>
                    @else
                        <span style="font-style: italic;">—</span>
                    @endif
                </td>
                <td style="max-width: 220px;">
                    @if($delivery->response_body)
                        <div class="response-preview" style="font-size: 0.7rem;">
                            <code class="mono preview-truncated" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer;"
                                  onclick="toggleResponsePreview(this)"
                                  title="Click to expand/collapse">
                                {{ Str::limit($delivery->response_body, 60) }}
                            </code>
                            <code class="mono preview-full" style="display: none; white-space: pre-wrap; word-break: break-all; cursor: pointer;"
                                  onclick="toggleResponsePreview(this)"
                                  title="Click to expand/collapse">
                                {{ Str::limit($delivery->response_body, 500) }}
                            </code>
                            @if(strlen($delivery->response_body) > 500)
                                <span style="color: var(--text-muted); font-size: 0.65rem; display: block; margin-top: 2px;">
                                    (truncated to 500 chars)
                                </span>
                            @endif
                        </div>
                    @elseif($delivery->error_message)
                        <div class="response-preview" style="font-size: 0.7rem;">
                            <code class="mono preview-truncated" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--danger); cursor: pointer;"
                                  onclick="toggleResponsePreview(this)"
                                  title="Click to expand/collapse">
                                {{ Str::limit($delivery->error_message, 60) }}
                            </code>
                            <code class="mono preview-full" style="display: none; white-space: pre-wrap; word-break: break-all; color: var(--danger); cursor: pointer;"
                                  onclick="toggleResponsePreview(this)"
                                  title="Click to expand/collapse">
                                {{ Str::limit($delivery->error_message, 500) }}
                            </code>
                        </div>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                    @endif
                </td>
                <td>
                    @if(in_array($delivery->status, ['failed', 'retrying']))
                        <form action="{{ route('admin.webhooks.deliveries.retry', $delivery) }}" method="POST"
                              style="display: inline;" data-loading>
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">Retry</button>
                        </form>
                    @else
                        <span style="color: var(--text-muted); font-size: 0.75rem;">—</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9">
                    <x-empty-state icon="📭" title="No deliveries found"
                        description="No webhook deliveries match the current filters." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Pagination --}}
    @if($deliveries->hasPages())
    <div class="pagination">
        {{ $deliveries->appends(request()->query())->links() }}
    </div>
    @endif
</div>

{{-- Auto-refresh polling for in-progress deliveries --}}
@if(($stats['retrying'] ?? 0) > 0 || ($stats['pending'] ?? 0) > 0)
<script>
(function() {
    const POLL_INTERVAL = 10000; // 10 seconds
    let pollTimer = null;

    function pollDeliveries() {
        const currentUrl = window.location.href;
        fetch(currentUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('deliveries-table');
            const oldTable = document.getElementById('deliveries-table');

            if (newTable && oldTable) {
                oldTable.innerHTML = newTable.innerHTML;
            }

            // Update stats cards
            const newStats = doc.querySelector('.stats-grid');
            const oldStats = document.querySelector('.stats-grid');
            if (newStats && oldStats) {
                oldStats.innerHTML = newStats.innerHTML;
            }

            // Check if there are still in-progress deliveries
            const hasInProgress = oldTable.querySelector('tr[data-status="retrying"], tr[data-status="pending"]');
            if (!hasInProgress) {
                clearInterval(pollTimer);
            }
        })
        .catch(() => {
            // Silently fail — will retry on next interval
        });
    }

    // Start polling if there are in-progress deliveries
    if (document.querySelector('tr[data-status="retrying"], tr[data-status="pending"]')) {
        pollTimer = setInterval(pollDeliveries, POLL_INTERVAL);
    }
})();
</script>
@endif

<script>
/**
 * Toggle response/error preview between truncated and full view.
 */
function toggleResponsePreview(element) {
    const container = element.closest('.response-preview');
    if (!container) return;

    const truncated = container.querySelector('.preview-truncated');
    const full = container.querySelector('.preview-full');

    if (truncated && full) {
        const isTruncatedVisible = truncated.style.display !== 'none';
        truncated.style.display = isTruncatedVisible ? 'none' : 'block';
        full.style.display = isTruncatedVisible ? 'block' : 'none';
    }
}
</script>
@endsection
