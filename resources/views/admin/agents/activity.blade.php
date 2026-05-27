@extends('admin.layout')
@section('title', 'Agent Activity: ' . $agent->name)

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Agents', 'url' => route('admin.agents')], ['label' => $agent->name . ' Activity']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>🖥️ {{ $agent->name }} — Activity Timeline</h1>
        <p>Audit trail for this print agent</p>
    </div>
    <a href="{{ route('admin.agents') }}" class="btn btn-secondary">← Back to Agents</a>
</div>

<div class="card">
    <div class="card-header">
        <h2>Agent Info</h2>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
        <div>
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Status</div>
            <div>
                @if($agent->isOnline())
                    <span class="badge badge-success"><span class="dot dot-green"></span> Online</span>
                @else
                    <span class="badge badge-danger"><span class="dot dot-red"></span> Offline</span>
                @endif
            </div>
        </div>
        <div>
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Branch</div>
            <div style="font-size: 0.85rem;">{{ $agent->branch->name ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Location</div>
            <div style="font-size: 0.85rem;">{{ $agent->location ?? '—' }}</div>
        </div>
        <div>
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Last Seen</div>
            <div style="font-size: 0.85rem;">{{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}</div>
        </div>
        <div>
            <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Key Age</div>
            <div style="font-size: 0.85rem;">{{ $agent->last_key_rotated_at ? $agent->last_key_rotated_at->diffInDays(now()) . ' days' : 'N/A' }}</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Activity Log ({{ $activities->total() }})</h2>
    </div>

    @if($activities->count() > 0)
    <div style="position: relative; padding-left: 2rem;">
        {{-- Timeline line --}}
        <div style="position: absolute; left: 12px; top: 0; bottom: 0; width: 2px; background: var(--border);"></div>

        @foreach($activities as $activity)
        @php
            $iconMap = [
                'agent.created'       => ['icon' => '🆕', 'color' => 'var(--success)'],
                'agent.updated'       => ['icon' => '✏️', 'color' => 'var(--info)'],
                'agent.deleted'       => ['icon' => '🗑️', 'color' => 'var(--danger)'],
                'agent.key_regenerated' => ['icon' => '🔑', 'color' => 'var(--warning)'],
                'agent.status_changed'  => ['icon' => '🔄', 'color' => 'var(--info)'],
            ];
            $meta = $iconMap[$activity->event] ?? ['icon' => '📝', 'color' => 'var(--text-muted)'];
            $details = is_array($activity->details) ? $activity->details : [];
            $description = $details['description'] ?? $details['name'] ?? $activity->event;
        @endphp
        <div style="position: relative; padding-bottom: 1.25rem; padding-left: 1rem;">
            {{-- Timeline dot --}}
            <div style="position: absolute; left: -1.65rem; top: 0.25rem; width: 12px; height: 12px; border-radius: 50%; background: {{ $meta['color'] }}; border: 2px solid var(--bg);"></div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="font-size: 1rem; margin-right: 0.4rem;">{{ $meta['icon'] }}</span>
                    <strong style="font-size: 0.85rem;">{{ ucwords(str_replace('_', ' ', str_replace('agent.', '', $activity->event))) }}</strong>
                    <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 0.2rem;">
                        {{ $description }}
                        @if(!empty($details) && count($details) > 1)
                            <br>
                            @foreach($details as $k => $v)
                                @if($k !== 'description' && $k !== 'name')
                                    <span style="font-size: 0.7rem;">{{ $k }}: {{ is_string($v) ? $v : json_encode($v) }}</span>
                                    @if(!$loop->last) · @endif
                                @endif
                            @endforeach
                        @endif
                    </div>
                </div>
                <div style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; margin-left: 1rem;">
                    {{ $activity->created_at->format('d M Y H:i') }}
                    <br><span style="font-size: 0.65rem;">{{ $activity->created_at->diffForHumans() }}</span>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    @if($activities->hasPages())
    <div class="pagination" style="margin-top: 1rem;">
        {{ $activities->links() }}
    </div>
    @endif
    @else
    <div style="text-align: center; padding: 2rem; color: var(--text-muted); font-size: 0.85rem;">
        No activity recorded for this agent yet.
    </div>
    @endif
</div>
@endsection
