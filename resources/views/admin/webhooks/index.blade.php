@extends('admin.layout')
@section('title', 'Webhook Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Webhook Settings']]" />

<div class="page-header">
    <h1>Webhook Settings</h1>
    <p>Configure webhook endpoints for client applications</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Client App Webhooks ({{ $clientApps->count() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>App Name</th>
                <th>Webhook URL</th>
                <th>Secret</th>
                <th>Status</th>
                <th>Events</th>
                <th>Last Delivery</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($clientApps as $app)
            <tr>
                <td>
                    <strong>{{ $app->name }}</strong>
                    @if($app->webhook_url)
                        <br><span style="font-size: 0.75rem; color: var(--text-muted);">
                            Retries: {{ $app->webhook_retry_count ?? 3 }} · Timeout: {{ $app->webhook_timeout ?? 10 }}s
                        </span>
                    @endif
                </td>
                <td style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    @if($app->webhook_url)
                        <code class="mono" style="font-size: 0.75rem;">{{ $app->webhook_url }}</code>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">Not configured</span>
                    @endif
                </td>
                <td>
                    @if($app->webhook_secret)
                        <span class="mono secret-mask" data-secret="{{ $app->webhook_secret }}" style="cursor: pointer; font-size: 0.75rem;" onclick="toggleSecret(this)">
                            ****************
                        </span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                    @endif
                </td>
                <td>
                    @if(!$app->is_active)
                        <span class="badge badge-danger">Disabled</span>
                    @elseif($app->webhook_url)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-warning">No URL</span>
                    @endif
                </td>
                <td style="max-width: 200px;">
                    @if(!empty($app->webhook_events) && is_array($app->webhook_events))
                        <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                            @foreach($app->webhook_events as $event)
                                <span class="badge badge-info" style="font-size: 0.65rem;">{{ $event }}</span>
                            @endforeach
                        </div>
                    @else
                        <span style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">All events</span>
                    @endif
                </td>
                <td>
                    @php $lastStatus = $app->last_delivery_status ?? 'none'; @endphp
                    @if($lastStatus === 'none')
                        <span style="color: var(--text-muted); font-style: italic; font-size: 0.8rem;">—</span>
                    @elseif($lastStatus === 'success')
                        <span class="badge badge-success">Success</span>
                    @elseif($lastStatus === 'failed')
                        <span class="badge badge-danger">Failed</span>
                    @elseif($lastStatus === 'retrying')
                        <span class="badge badge-warning">Retrying</span>
                    @else
                        <span class="badge badge-info">{{ ucfirst($lastStatus) }}</span>
                    @endif
                </td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="openWebhookModal({{ $app->id }})">
                            Edit
                        </button>
                        <a href="{{ route('admin.webhooks.deliveries', $app) }}" class="btn btn-secondary btn-sm">
                            Log
                        </a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state icon="🔗" title="No client apps registered"
                        description="Client apps will appear here once they are registered." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Webhook Modal --}}
@foreach($clientApps as $app)
<x-modal id="webhook-modal-{{ $app->id }}" title="Webhook Configuration — {{ $app->name }}" maxWidth="lg">
    <form action="{{ route('admin.webhooks.update', $app) }}" method="POST" data-loading>
        @csrf
        @method('PUT')

        <div class="form-group">
            <label for="webhook_url_{{ $app->id }}">
                Webhook URL
                <span class="help-tip">?
                    <span class="help-tip-popover">The endpoint URL that will receive POST requests when events occur. Must be a valid URL.</span>
                </span>
            </label>
            <input type="url" name="webhook_url" id="webhook_url_{{ $app->id }}"
                   value="{{ old('webhook_url', $app->webhook_url) }}"
                   placeholder="https://example.com/webhook" maxlength="500">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="webhook_secret_{{ $app->id }}">
                    Webhook Secret
                    <span class="help-tip">?
                        <span class="help-tip-popover">Used to sign webhook payloads with HMAC-SHA256. The signature is sent in the X-Webhook-Signature header.</span>
                    </span>
                </label>
                <input type="text" name="webhook_secret" id="webhook_secret_{{ $app->id }}"
                       value="{{ old('webhook_secret', $app->webhook_secret) }}"
                       placeholder="Leave empty to keep current" maxlength="255">
            </div>
            <div class="form-group">
                <label for="is_active_{{ $app->id }}" style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-top: 1.5rem;">
                    <input type="checkbox" name="is_active" id="is_active_{{ $app->id }}" value="1"
                           style="width: 18px; height: 18px;"
                           {{ $app->is_active ? 'checked' : '' }}>
                    Webhook Active
                </label>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="webhook_retry_count_{{ $app->id }}">
                    Max Retries
                    <span class="help-tip">?
                        <span class="help-tip-popover">Number of times to retry delivery on failure, with exponential backoff (30s, 2min, 5min).</span>
                    </span>
                </label>
                <input type="number" name="webhook_retry_count" id="webhook_retry_count_{{ $app->id }}"
                       value="{{ old('webhook_retry_count', $app->webhook_retry_count ?? 3) }}"
                       min="0" max="10">
            </div>
            <div class="form-group">
                <label for="webhook_timeout_{{ $app->id }}">
                    Timeout (seconds)
                    <span class="help-tip">?
                        <span class="help-tip-popover">Maximum time in seconds to wait for the webhook endpoint to respond.</span>
                    </span>
                </label>
                <input type="number" name="webhook_timeout" id="webhook_timeout_{{ $app->id }}"
                       value="{{ old('webhook_timeout', $app->webhook_timeout ?? 10) }}"
                       min="1" max="30">
            </div>
        </div>

        {{-- Enhanced Event Selector with Categories, Select All, and Count Indicator --}}
        <div class="form-group">
            <label style="display: block; margin-bottom: 0.5rem; font-size: 0.8rem; font-weight: 500; color: var(--text-muted);">
                Subscribe to Events
                <span class="help-tip">?
                    <span class="help-tip-popover">Select which events should trigger a webhook notification. If none selected, all events are sent (backwards compatibility).</span>
                </span>
            </label>

            @php
                $availableEvents = ['job.completed', 'job.failed', 'job.queued', 'agent.online', 'agent.offline'];
                $selectedEvents = old('webhook_events', $app->webhook_events ?? []);
                $selectedCount = count($selectedEvents);
                $totalEvents = count($availableEvents);

                $eventCategories = [
                    'Job Events' => ['job.completed', 'job.failed', 'job.queued'],
                    'Agent Events' => ['agent.online', 'agent.offline'],
                ];
            @endphp

            {{-- Select All / Deselect All Toggle & Selected Count --}}
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 0.75rem; padding: 0.5rem 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; font-weight: 500;">
                    <input type="checkbox" id="select-all-events-{{ $app->id }}"
                           style="width: 16px; height: 16px;"
                           {{ $selectedCount === $totalEvents ? 'checked' : '' }}
                           onchange="toggleSelectAll({{ $app->id }}, this.checked)">
                    Select All
                </label>
                <span style="color: var(--text-muted); font-size: 0.8rem;">|</span>
                <span id="selected-count-{{ $app->id }}" style="font-size: 0.8rem; color: var(--text-muted);">
                    <span id="selected-count-value-{{ $app->id }}" style="font-weight: 600; color: {{ $selectedCount > 0 ? 'var(--primary)' : 'var(--text-muted)' }};">{{ $selectedCount }}</span>
                    / {{ $totalEvents }} selected
                </span>
                @if($selectedCount === 0)
                    <span class="badge badge-warning" style="font-size: 0.65rem; margin-left: auto;">All events (backward compat)</span>
                @endif
            </div>

            {{-- Collapsible Event Categories --}}
            <div class="event-categories" style="display: flex; flex-direction: column; gap: 0.5rem;">
                @foreach($eventCategories as $categoryName => $events)
                <div class="event-category" style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                    {{-- Category Header (collapsible toggle) --}}
                    <div class="category-header" onclick="toggleCategory(this)"
                         style="display: flex; align-items: center; gap: 8px; padding: 0.5rem 0.75rem; background: var(--bg); cursor: pointer; user-select: none; font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">
                        <span class="category-toggle" style="transition: transform 0.2s; display: inline-block;">▼</span>
                        <span>{{ $categoryName }}</span>
                        <span style="font-weight: 400; font-size: 0.7rem; color: var(--text-muted);">
                            (@php
                                $catSelected = count(array_intersect($selectedEvents, $events));
                                $catTotal = count($events);
                            @endphp
                            <span class="cat-selected-count">{{ $catSelected }}</span>/{{ $catTotal }})
                        </span>
                    </div>
                    {{-- Category Body (checkboxes) --}}
                    <div class="category-body" style="display: flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.75rem;">
                        @foreach($events as $event)
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.85rem; padding: 0.4rem 0.6rem; background: var(--surface); border-radius: 6px; border: 1px solid var(--border); transition: all 0.15s;">
                                <input type="checkbox" name="webhook_events[]" value="{{ $event }}"
                                       class="event-checkbox-{{ $app->id }}"
                                       data-app-id="{{ $app->id }}"
                                       data-category="{{ $categoryName }}"
                                       style="width: 16px; height: 16px;"
                                       {{ in_array($event, $selectedEvents) ? 'checked' : '' }}
                                       onchange="updateEventCount({{ $app->id }})">
                                <code style="font-size: 0.75rem;">{{ $event }}</code>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border);">
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('webhook-modal-{{ $app->id }}').close()">Cancel</button>
            <button type="submit" class="btn btn-primary" data-loading-text="Saving...">Save Configuration</button>
        </div>
    </form>
</x-modal>
@endforeach

<script>
function openWebhookModal(appId) {
    const modal = document.getElementById('webhook-modal-' + appId);
    if (modal) modal.showModal();
}

function toggleSecret(el) {
    const secret = el.getAttribute('data-secret');
    if (el.textContent.includes('****')) {
        el.textContent = secret;
    } else {
        el.textContent = '****************';
    }
}

/**
 * Toggle select all / deselect all for event checkboxes.
 */
function toggleSelectAll(appId, checked) {
    const checkboxes = document.querySelectorAll('.event-checkbox-' + appId);
    checkboxes.forEach(cb => cb.checked = checked);
    updateEventCount(appId);
}

/**
 * Update the selected count indicator and category counts.
 */
function updateEventCount(appId) {
    const checkboxes = document.querySelectorAll('.event-checkbox-' + appId);
    const total = checkboxes.length;
    const selected = Array.from(checkboxes).filter(cb => cb.checked).length;

    // Update main count
    const countValue = document.getElementById('selected-count-value-' + appId);
    if (countValue) {
        countValue.textContent = selected;
        countValue.style.color = selected > 0 ? 'var(--primary)' : 'var(--text-muted)';
    }

    // Update select-all checkbox
    const selectAll = document.getElementById('select-all-events-' + appId);
    if (selectAll) {
        selectAll.checked = selected === total;
        selectAll.indeterminate = selected > 0 && selected < total;
    }

    // Update category counts
    const categories = document.querySelectorAll('.event-category');
    categories.forEach(cat => {
        const catCheckboxes = cat.querySelectorAll('.event-checkbox-' + appId);
        const catSelected = Array.from(catCheckboxes).filter(cb => cb.checked).length;
        const catCountSpan = cat.querySelector('.cat-selected-count');
        if (catCountSpan) {
            catCountSpan.textContent = catSelected;
        }
    });
}

/**
 * Toggle collapsible event category.
 */
function toggleCategory(header) {
    const body = header.nextElementSibling;
    const toggle = header.querySelector('.category-toggle');
    if (body && toggle) {
        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? 'flex' : 'none';
        toggle.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
    }
}

// Close modal on backdrop click
document.querySelectorAll('.ph-modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        if (e.clientX < rect.left || e.clientX > rect.right ||
            e.clientY < rect.top || e.clientY > rect.bottom) {
            this.close();
        }
    });
});
</script>
@endsection
