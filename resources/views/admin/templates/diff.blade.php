@extends('admin.layout')
@section('title', 'Version Diff')

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Templates', 'url' => route('admin.templates')],
    ['label' => $template->name, 'url' => route('admin.templates.edit', $template)],
    ['label' => 'Version History', 'url' => route('templates.versions', $template)],
    ['label' => "v{$v1->version_number} vs v{$v2->version_number}"],
]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Comparing v{{ $v1->version_number }} vs v{{ $v2->version_number }}</h1>
        <p style="color: var(--text-muted); font-size: 0.85rem;">
            <strong>{{ $template->name }}</strong>
            &middot; v{{ $v1->version_number }} created {{ $v1->created_at->diffForHumans() }}
            &middot; v{{ $v2->version_number }} created {{ $v2->created_at->diffForHumans() }}
        </p>
    </div>
    <a href="{{ route('templates.versions', $template) }}" class="btn btn-secondary">&larr; Back to Versions</a>
</div>

{{-- Summary Cards --}}
<div class="stats-grid">
    <div class="stat-card" style="border-color: rgba(34, 197, 94, 0.3);">
        <div class="stat-value" style="color: var(--success);">✅ {{ count($diff['unchanged'] ?? []) }}</div>
        <div class="stat-label">Unchanged Elements</div>
    </div>
    <div class="stat-card" style="border-color: rgba(34, 197, 94, 0.3);">
        <div class="stat-value" style="color: var(--success);">🟢 {{ count($diff['added'] ?? []) }}</div>
        <div class="stat-label">Added Elements</div>
    </div>
    <div class="stat-card" style="border-color: rgba(239, 68, 68, 0.3);">
        <div class="stat-value" style="color: var(--danger);">🔴 {{ count($diff['removed'] ?? []) }}</div>
        <div class="stat-label">Removed Elements</div>
    </div>
    <div class="stat-card" style="border-color: rgba(245, 158, 11, 0.3);">
        <div class="stat-value" style="color: var(--warning);">🟡 {{ count($diff['modified'] ?? []) }}</div>
        <div class="stat-label">Modified Elements</div>
    </div>
</div>

{{-- Diff List --}}
<div class="card">
    <div class="card-header">
        <h2>Changes</h2>
    </div>

    @if(empty($diff['added']) && empty($diff['removed']) && empty($diff['modified']))
        <p style="text-align: center; padding: 2rem; color: var(--text-muted);">
            No differences found between these two versions.
        </p>
    @endif

    {{-- Added Elements --}}
    @if(!empty($diff['added']))
    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.95rem; color: var(--success); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>🟢 Added ({{ count($diff['added']) }})</span>
        </h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($diff['added'] as $el)
            <div style="background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: 8px; padding: 0.75rem 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span class="badge badge-success">{{ $el['type'] ?? 'unknown' }}</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        @if(!empty($el['key']))
                            Key: <span class="mono">{{ $el['key'] }}</span>
                        @elseif(!empty($el['text']))
                            Text: "{{ \Illuminate\Support\Str::limit($el['text'], 40) }}"
                        @endif
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: auto;">
                        {{ $el['x'] ?? '?' }}×{{ $el['y'] ?? '?' }} &middot; {{ $el['width'] ?? '?' }}×{{ $el['height'] ?? '?' }}
                    </span>
                </div>
                <pre style="background: var(--bg); padding: 0.5rem; border-radius: 4px; font-size: 0.7rem; overflow-x: auto; max-height: 150px; color: var(--text); margin: 0;">{{ json_encode($el, JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Removed Elements --}}
    @if(!empty($diff['removed']))
    <div style="margin-bottom: 1.5rem;">
        <h3 style="font-size: 0.95rem; color: var(--danger); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>🔴 Removed ({{ count($diff['removed']) }})</span>
        </h3>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($diff['removed'] as $el)
            <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; padding: 0.75rem 1rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span class="badge badge-danger">{{ $el['type'] ?? 'unknown' }}</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        @if(!empty($el['key']))
                            Key: <span class="mono">{{ $el['key'] }}</span>
                        @elseif(!empty($el['text']))
                            Text: "{{ \Illuminate\Support\Str::limit($el['text'], 40) }}"
                        @endif
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: auto;">
                        {{ $el['x'] ?? '?' }}×{{ $el['y'] ?? '?' }} &middot; {{ $el['width'] ?? '?' }}×{{ $el['height'] ?? '?' }}
                    </span>
                </div>
                <pre style="background: var(--bg); padding: 0.5rem; border-radius: 4px; font-size: 0.7rem; overflow-x: auto; max-height: 150px; color: var(--text); margin: 0;">{{ json_encode($el, JSON_PRETTY_PRINT) }}</pre>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Modified Elements --}}
    @if(!empty($diff['modified']))
    <div>
        <h3 style="font-size: 0.95rem; color: var(--warning); margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem;">
            <span>🟡 Modified ({{ count($diff['modified']) }})</span>
        </h3>
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($diff['modified'] as $item)
            @php
                $oldEl = $item['old'];
                $newEl = $item['new'];
                // Find changed fields
                $allKeys = array_unique(array_merge(array_keys($oldEl), array_keys($newEl)));
                $changedFields = [];
                foreach ($allKeys as $k) {
                    $oldVal = $oldEl[$k] ?? null;
                    $newVal = $newEl[$k] ?? null;
                    if (json_encode($oldVal) !== json_encode($newVal)) {
                        $changedFields[] = $k;
                    }
                }
            @endphp
            <div style="border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                <div style="background: rgba(245, 158, 11, 0.1); padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 0.5rem;">
                    <span class="badge badge-warning">{{ $newEl['type'] ?? 'unknown' }}</span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">
                        @if(!empty($newEl['key']))
                            Key: <span class="mono">{{ $newEl['key'] }}</span>
                        @elseif(!empty($newEl['text']))
                            Text: "{{ \Illuminate\Support\Str::limit($newEl['text'], 40) }}"
                        @endif
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted); margin-left: auto;">
                        Changed: {{ implode(', ', $changedFields) }}
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0;">
                    {{-- Old (v1) --}}
                    <div style="border-right: 1px solid var(--border);">
                        <div style="background: rgba(239, 68, 68, 0.08); padding: 0.4rem 0.75rem; font-size: 0.7rem; font-weight: 600; color: var(--danger); border-bottom: 1px solid var(--border);">
                            v{{ $v1->version_number }} (old)
                        </div>
                        <pre style="background: rgba(239, 68, 68, 0.04); padding: 0.5rem; font-size: 0.65rem; overflow-x: auto; max-height: 200px; color: var(--text); margin: 0;">
@php
function highlightChanged($key, $oldVal, $newVal) {
    $oldJson = json_encode($oldVal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $newJson = json_encode($newVal, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($oldJson !== $newJson) {
        return '<span style="background: rgba(239,68,68,0.15); padding: 0 2px; border-radius: 2px;">"' . $key . '": ' . $oldJson . '</span>';
    }
    return '"' . $key . '": ' . $oldJson;
}
@endphp
@foreach($allKeys as $k)
{{ highlightChanged($k, $oldEl[$k] ?? null, $newEl[$k] ?? null) }}{{ !$loop->last ? ',' : '' }}

@endforeach
                        </pre>
                    </div>
                    {{-- New (v2) --}}
                    <div>
                        <div style="background: rgba(34, 197, 94, 0.08); padding: 0.4rem 0.75rem; font-size: 0.7rem; font-weight: 600; color: var(--success); border-bottom: 1px solid var(--border);">
                            v{{ $v2->version_number }} (new)
                        </div>
                        <pre style="background: rgba(34, 197, 94, 0.04); padding: 0.5rem; font-size: 0.65rem; overflow-x: auto; max-height: 200px; color: var(--text); margin: 0;">
@foreach($allKeys as $k)
{{ highlightChanged($k, $newEl[$k] ?? null, $oldEl[$k] ?? null) }}{{ !$loop->last ? ',' : '' }}

@endforeach
                        </pre>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
