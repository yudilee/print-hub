@extends('admin.layout')
@section('title', 'Webhook Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'Webhook Dispatchers']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Webhook Integrations & Subscriptions</h2>
        <p class="text-xs text-slate-400">Real-time HTTP event callbacks dispatched to third-party endpoints upon print lifecycle events</p>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Configured Webhooks: <span class="text-white font-mono font-bold">{{ $clientApps->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Client App</th>
                    <th class="px-5 py-3.5">Webhook Endpoint URL</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Subscribed Events</th>
                    <th class="px-5 py-3.5">Last Delivery</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($clientApps as $app)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-bold text-white">
                        {{ $app->name }}
                        @if($app->webhook_url)
                            <span class="block text-[10px] text-slate-500 font-normal mt-0.5">
                                {{ $app->webhook_retry_count ?? 3 }} retries · {{ $app->webhook_timeout ?? 10 }}s timeout
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-slate-300 max-w-xs truncate">
                        @if($app->webhook_url)
                            <span class="text-blue-400">{{ $app->webhook_url }}</span>
                        @else
                            <span class="text-slate-500 italic">Not configured</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if(!$app->is_active)
                            <span class="badge badge-danger">Disabled</span>
                        @elseif($app->webhook_url)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-warning">No URL</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if(!empty($app->webhook_events) && is_array($app->webhook_events))
                            <div class="flex flex-wrap gap-1">
                                @foreach($app->webhook_events as $event)
                                    <span class="badge badge-info text-[9px]">{{ $event }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-slate-400 italic">All events (*)</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @php $lastStatus = $app->last_delivery_status ?? 'none'; @endphp
                        @if($lastStatus === 'success')
                            <span class="badge badge-success text-[10px]">Delivered</span>
                        @elseif($lastStatus === 'failed')
                            <span class="badge badge-danger text-[10px]">Failed</span>
                        @elseif($lastStatus === 'retrying')
                            <span class="badge badge-warning text-[10px]">Retrying</span>
                        @else
                            <span class="text-slate-500 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button class="btn-secondary btn-sm" onclick="openWebhookModal({{ $app->id }})">Configure</button>
                            <a href="{{ route('admin.webhooks.deliveries', $app) }}" class="btn-secondary btn-sm">Logs</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="🔗" title="No client applications" description="Register client apps to configure automated event notifications." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modals for Webhooks --}}
@foreach($clientApps as $app)
<div id="webhook-modal-{{ $app->id }}" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Webhook: {{ $app->name }}</h3>
            <button onclick="closeWebhookModal({{ $app->id }})" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form action="{{ route('admin.webhooks.update', $app) }}" method="POST" class="space-y-4">
            @csrf @method('PUT')

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Webhook Target URL</label>
                <input type="url" name="webhook_url" value="{{ old('webhook_url', $app->webhook_url) }}" placeholder="https://api.domain.com/webhook"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">HMAC Signing Secret (Optional)</label>
                <input type="text" name="webhook_secret" value="{{ old('webhook_secret', $app->webhook_secret) }}" placeholder="Secret string for X-Webhook-Signature"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Retry Attempts</label>
                    <input type="number" name="webhook_retry_count" min="0" max="10" value="{{ old('webhook_retry_count', $app->webhook_retry_count ?? 3) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Timeout (Seconds)</label>
                    <input type="number" name="webhook_timeout" min="1" max="30" value="{{ old('webhook_timeout', $app->webhook_timeout ?? 10) }}"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ $app->is_active ? 'checked' : '' }} class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Dispatcher Active</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="closeWebhookModal({{ $app->id }})">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Save Configuration</button>
            </div>
        </form>
    </div>
</div>
@endforeach

<script>
function openWebhookModal(appId) {
    const modal = document.getElementById('webhook-modal-' + appId);
    if (modal) modal.classList.remove('hidden');
}
function closeWebhookModal(appId) {
    const modal = document.getElementById('webhook-modal-' + appId);
    if (modal) modal.classList.add('hidden');
}
</script>
@endsection
