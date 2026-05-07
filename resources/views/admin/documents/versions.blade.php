@extends('admin.layout')
@section('title', 'Document Versions')

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Documents', 'url' => route('admin.documents')],
    ['label' => 'Versions: ' . $document->original_name],
]" />

<div class="page-header">
    <h1>📄 Version History</h1>
    <p>{{ $document->original_name }}</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Versions ({{ $versions->count() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Version</th>
                <th>File</th>
                <th>Size</th>
                <th>Pages</th>
                <th>Uploaded By</th>
                <th>Uploaded At</th>
                <th>Retention</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $version)
            <tr @if($version->id === $document->id) style="background:rgba(99,102,241,0.08);" @endif>
                <td>
                    <strong>v{{ $version->version_label ?? $version->version }}</strong>
                    @if($version->id === $document->id)
                        <span style="font-size:0.7rem; color:var(--primary);">(current)</span>
                    @endif
                </td>
                <td style="font-weight:500; max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                    {{ $version->original_name }}
                </td>
                <td style="font-size:0.85rem;">{{ $version->formatted_size }}</td>
                <td>{{ $version->page_count ?? '—' }}</td>
                <td>{{ $version->user?->name ?? '—' }}</td>
                <td style="color:var(--text-muted); font-size:0.8rem; white-space:nowrap;">
                    {{ $version->created_at->format('d M Y H:i') }}
                </td>
                <td style="font-size:0.8rem; white-space:nowrap;">
                    @if($version->retain_until)
                        @if($version->retain_until->isPast())
                            <span style="color:var(--danger);">Expired</span>
                        @else
                            <span style="color:var(--text-muted);">{{ $version->retain_until->format('d M Y') }}</span>
                        @endif
                    @else
                        <span style="color:var(--text-muted);">Forever</span>
                    @endif
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <a href="{{ route('api.documents.preview', $version->id) }}" target="_blank"
                           class="btn btn-secondary btn-sm">Preview</a>
                        <a href="{{ route('api.documents.download', $version->id) }}" target="_blank"
                           class="btn btn-secondary btn-sm">Download</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8">
                    <x-empty-state icon="📄" title="No versions found" description="This document has no version history." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">
    <a href="{{ route('admin.documents') }}" class="btn btn-secondary">← Back to Documents</a>
</div>
@endsection
