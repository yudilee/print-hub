@extends('admin.layout')
@section('title', 'Fonts Management')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Fonts']]" />

<div class="page-header">
    <h1>Custom Fonts</h1>
    <p>Upload and manage TrueType (.ttf) and OpenType (.otf) fonts for use in template designs.</p>
</div>

{{-- Upload Modal Trigger --}}
<button class="btn btn-primary" onclick="openUploadModal()" style="margin-bottom: 1rem;">+ Upload Font</button>

{{-- Filters --}}
<div class="filter-bar">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <input type="text" name="search" placeholder="Search fonts..." value="{{ request('search') }}"
               style="width: 200px; background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem 0.75rem; border-radius: 6px; font-size: 0.85rem;">
        <select name="file_type" onchange="this.form.submit()" style="background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem; border-radius: 6px; font-size: 0.85rem;">
            <option value="">All Types</option>
            <option value="ttf" {{ request('file_type') === 'ttf' ? 'selected' : '' }}>TTF</option>
            <option value="otf" {{ request('file_type') === 'otf' ? 'selected' : '' }}>OTF</option>
        </select>
        <select name="is_active" onchange="this.form.submit()" style="background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem; border-radius: 6px; font-size: 0.85rem;">
            <option value="">All Status</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        @if(request()->anyFilled(['search', 'file_type', 'is_active']))
            <a href="{{ route('admin.fonts') }}" class="btn btn-secondary btn-sm" style="text-decoration: none;">Clear</a>
        @endif
    </form>
</div>

{{-- Fonts Table --}}
<div class="card">
    <div class="card-header"><h2>All Fonts ({{ $fonts->total() }})</h2></div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Family</th>
                <th>Style</th>
                <th>Type</th>
                <th>Size</th>
                <th>Status</th>
                <th>Uploaded</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($fonts as $font)
            <tr>
                <td>
                    <strong>{{ $font->name }}</strong>
                    @if($font->preview_text)
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px; font-style: italic;">{{ Str::limit($font->preview_text, 40) }}</div>
                    @endif
                </td>
                <td><span class="mono">{{ $font->font_family }}</span></td>
                <td>
                    @php
                        $styleLabels = ['regular' => 'Regular', 'bold' => 'Bold', 'italic' => 'Italic', 'bold_italic' => 'Bold Italic'];
                        $styleColors = ['regular' => 'badge-info', 'bold' => 'badge-warning', 'italic' => 'badge-info', 'bold_italic' => 'badge-warning'];
                    @endphp
                    <span class="badge {{ $styleColors[$font->font_style] ?? 'badge-info' }}">
                        {{ $styleLabels[$font->font_style] ?? $font->font_style }}
                    </span>
                </td>
                <td><span class="mono">{{ strtoupper($font->file_type) }}</span></td>
                <td>
                    @if($font->file_size >= 1048576)
                        {{ number_format($font->file_size / 1048576, 1) }} MB
                    @else
                        {{ number_format($font->file_size / 1024, 1) }} KB
                    @endif
                </td>
                <td>
                    <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" {{ $font->is_active ? 'checked' : '' }}
                               onchange="toggleActive({{ $font->id }}, this.checked)" style="width: 16px; height: 16px;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            {{ $font->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </label>
                </td>
                <td style="font-size: 0.75rem; color: var(--text-muted);">
                    <div>{{ $font->created_at->format('d M Y') }}</div>
                    @if($font->uploader)
                        <div style="font-size: 0.65rem;">by {{ $font->uploader->name }}</div>
                    @endif
                </td>
                <td>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <a href="{{ route('admin.fonts.download', $font) }}" class="btn btn-secondary btn-sm"
                           style="text-decoration: none;" title="Download font file">⬇</a>
                        <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ json_encode($font) }})"
                                title="Edit font metadata">✎</button>
                        <form action="{{ route('admin.fonts.destroy', $font) }}" method="POST"
                              onsubmit="return confirm('Delete font \'{{ $font->name }}\'? The file will be retained on disk.')"
                              style="display: inline;">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" title="Soft-delete font">🗑</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8">
                <x-empty-state icon="🔤" title="No fonts uploaded"
                    description="Upload TrueType or OpenType fonts to use them in the template designer." />
            </td></tr>
            @endforelse
        </tbody>
    </table>

    @if($fonts->hasPages())
        <div class="pagination">
            {{ $fonts->links() }}
        </div>
    @endif
</div>

{{-- Upload Modal --}}
<div id="upload-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 480px; padding: 2rem;">
        <div class="card-header"><h2>Upload Font</h2></div>
        <form action="{{ route('admin.fonts.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label for="name">Font Display Name</label>
                <input type="text" name="name" id="upload-name" required placeholder="e.g. Roboto Regular">
            </div>
            <div class="form-group">
                <label for="font_family">Font Family (optional, auto-detected from filename)</label>
                <input type="text" name="font_family" id="upload-family" placeholder="e.g. Roboto">
            </div>
            <div class="form-group">
                <label for="font_file">Font File (.ttf or .otf, max 5MB)</label>
                <input type="file" name="font_file" id="upload-file" accept=".ttf,.otf" required
                       style="background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem; border-radius: 6px; width: 100%;">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 480px; padding: 2rem;">
        <div class="card-header"><h2>Edit Font</h2></div>
        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label for="edit-name">Font Display Name</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div class="form-group">
                <label for="edit-preview-text">Preview Text (optional)</label>
                <textarea name="preview_text" id="edit-preview-text" rows="2"
                          placeholder="Sample text shown in preview..."
                          style="background: var(--bg); border: 1px solid var(--border); color: var(--text); padding: 0.5rem; border-radius: 6px; width: 100%; font-size: 0.85rem;"></textarea>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" style="width: 18px; height: 18px;">
                    Active
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal() {
    document.getElementById('upload-modal').style.display = 'flex';
}
function closeUploadModal() {
    document.getElementById('upload-modal').style.display = 'none';
}

function openEditModal(font) {
    document.getElementById('edit-form').action = `/fonts/${font.id}`;
    document.getElementById('edit-name').value = font.name;
    document.getElementById('edit-preview-text').value = font.preview_text || '';
    document.getElementById('edit-active').checked = font.is_active;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

function toggleActive(fontId, isActive) {
    fetch(`/fonts/${fontId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        },
        body: JSON.stringify({ is_active: isActive })
    })
    .then(r => {
        if (!r.ok) throw new Error('Update failed');
        showToast(isActive ? 'Font activated' : 'Font deactivated', 'success');
    })
    .catch(err => {
        showToast('Failed to update status: ' + err.message, 'error');
    });
}
</script>
@endsection
