@extends('admin.layout')
@section('title', 'Version History')

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Dashboard', 'url' => route('admin.dashboard')],
    ['label' => 'Templates', 'url' => route('admin.templates')],
    ['label' => $template->name, 'url' => route('admin.templates.edit', $template)],
    ['label' => 'Version History'],
]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Version History: {{ $template->name }}</h1>
        <p>Snapshot and restore previous template designs</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="{{ route('admin.templates.edit', $template) }}" class="btn btn-secondary">&larr; Back to Designer</a>
        <button onclick="document.getElementById('create-snapshot-modal').style.display='flex'" class="btn btn-primary">+ Create Snapshot</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Versions ({{ $versions->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Version</th>
                <th>Label</th>
                <th>Changelog</th>
                <th>Created By</th>
                <th>Created At</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($versions as $version)
            <tr>
                <td>
                    <span class="badge badge-info">v{{ $version->version_number }}</span>
                </td>
                <td>
                    @if($version->label)
                        <strong>{{ $version->label }}</strong>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                    @endif
                </td>
                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    {{ $version->changelog ?? '—' }}
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">
                    @if($version->creator)
                        {{ $version->creator->email }}
                    @else
                        <span style="font-style: italic;">System</span>
                    @endif
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">
                    {{ $version->created_at->diffForHumans() }}
                </td>
                <td>
                    <div style="display: flex; gap: 0.4rem; justify-content: flex-end;">
                        <form action="{{ route('templates.versions.restore', [$template, $version]) }}" method="POST"
                              onsubmit="return confirm('Restore to version {{ $version->version_number }}? Current design will be overwritten.')"
                              style="display: inline;">
                            @csrf
                            <button class="btn btn-warning btn-sm">Restore</button>
                        </form>
                        <button class="btn btn-secondary btn-sm diff-select"
                                data-version="{{ $version->version_number }}"
                                data-id="{{ $version->id }}"
                                onclick="toggleDiffSelection(this)">
                            Diff
                        </button>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                    No versions recorded yet. Save the template or create a snapshot to get started.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($versions->hasPages())
    <div class="pagination">
        {{ $versions->links() }}
    </div>
    @endif
</div>

{{-- Hidden form for diff comparison --}}
<form id="diff-form" method="GET" action="" style="display: none;">
    @csrf
</form>

{{-- Create Snapshot Modal --}}
<div id="create-snapshot-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:var(--surface); width:480px; border-radius:12px; padding:1.5rem; border:1px solid var(--border);">
        <h3 style="margin:0 0 1rem 0; font-size:1.1rem;">📸 Create Version Snapshot</h3>
        <p style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1rem;">
            Creates a snapshot of the current template state. The latest saved data will be used.
        </p>
        <form action="{{ route('templates.versions.create', $template) }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="label">Label <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
                <input type="text" id="label" name="label" placeholder="e.g. Pre-release v2, Before major change" maxlength="255">
            </div>
            <div class="form-group">
                <label for="changelog">Changelog <span style="color: var(--text-muted); font-weight: 400;">(optional)</span></label>
                <textarea id="changelog" name="changelog" rows="3" placeholder="Describe what changed in this version..." maxlength="1000"></textarea>
            </div>
            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex: 1; justify-content: center;">Create Snapshot</button>
                <button type="button" onclick="document.getElementById('create-snapshot-modal').style.display='none'" class="btn btn-secondary" style="flex: 1; justify-content: center;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    let diffIds = [];

    function toggleDiffSelection(btn) {
        const versionId = btn.dataset.id;
        const versionNum = btn.dataset.version;

        if (diffIds.includes(versionId)) {
            diffIds = diffIds.filter(id => id !== versionId);
            btn.style.borderColor = '';
            btn.style.color = '';
        } else {
            if (diffIds.length >= 2) {
                alert('You can only compare two versions at a time. Click Diff again to deselect.');
                return;
            }
            diffIds.push(versionId);
            btn.style.borderColor = 'var(--primary)';
            btn.style.color = 'var(--primary)';
        }

        if (diffIds.length === 2) {
            // Sort by version number so v1 < v2
            const allBtns = document.querySelectorAll('.diff-select');
            const selected = [];
            allBtns.forEach(b => {
                if (diffIds.includes(b.dataset.id)) {
                    selected.push({ id: b.dataset.id, version: parseInt(b.dataset.version) });
                }
            });
            selected.sort((a, b) => a.version - b.version);
            const url = '{{ route("templates.versions.diff", [$template, "v1", "v2"]) }}'
                .replace('v1', selected[0].id)
                .replace('v2', selected[1].id);
            window.location.href = url;
        }
    }
</script>
@endsection
