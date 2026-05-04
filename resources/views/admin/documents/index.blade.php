@extends('admin.layout')
@section('title', 'Documents')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Documents']]" />

<div class="page-header">
    <h1>📄 Document Management</h1>
    <p>Uploaded documents that can be associated with print jobs</p>
</div>

{{-- Upload Button --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <div style="color:var(--text-muted); font-size:0.875rem;">
        {{ $documents->total() }} document(s) total
    </div>
    <button onclick="document.getElementById('upload-modal').style.display='flex'"
            class="btn btn-primary">+ Upload Document</button>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Pages</th>
                <th>Uploaded By</th>
                <th>Uploaded At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($documents as $doc)
            <tr>
                <td style="font-weight:500; max-width:200px; overflow:hidden; text-overflow:ellipsis;">
                    {{ $doc->original_name }}
                </td>
                <td>
                    <span class="badge badge-info">{{ strtoupper(pathinfo($doc->original_name, PATHINFO_EXTENSION)) }}</span>
                </td>
                <td style="font-size:0.85rem;">{{ $doc->formatted_size }}</td>
                <td>{{ $doc->page_count ?? '—' }}</td>
                <td>{{ $doc->user?->name ?? '—' }}</td>
                <td style="color:var(--text-muted); font-size:0.8rem; white-space:nowrap;">
                    {{ $doc->created_at->format('d M Y H:i') }}
                </td>
                <td>
                    <div style="display:flex; gap:6px;">
                        <a href="{{ route('api.documents.preview', $doc->id) }}" target="_blank"
                           class="btn btn-secondary btn-sm">Preview</a>
                        <a href="{{ route('api.documents.download', $doc->id) }}" target="_blank"
                           class="btn btn-secondary btn-sm">Download</a>
                        <form method="POST" action="{{ route('admin.documents.destroy', $doc->id) }}"
                              onsubmit="return confirm('Delete this document? It will be soft-deleted but remain accessible for existing print jobs.')"
                              style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7">
                    <x-empty-state icon="📄" title="No documents uploaded" description="Upload a PDF or image document to associate with print jobs." />
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if($documents->hasPages())
    <div class="pagination">
        @if($documents->onFirstPage())
            <span>← Prev</span>
        @else
            <a href="{{ $documents->previousPageUrl() }}">← Prev</a>
        @endif

        @foreach($documents->getUrlRange(1, $documents->lastPage()) as $page => $url)
            @if($page == $documents->currentPage())
                <span class="active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($documents->hasMorePages())
            <a href="{{ $documents->nextPageUrl() }}">Next →</a>
        @else
            <span>Next →</span>
        @endif
    </div>
    @endif
</div>

{{-- Upload Modal --}}
<div id="upload-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2rem; width:450px; max-width:90vw;">
        <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:0.5rem;">Upload Document</h2>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">Allowed: PDF, PNG, JPG (max 50 MB)</p>
        <form method="POST" action="{{ route('admin.documents.upload') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>File</label>
                <input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg" required
                       style="background:var(--bg); border:1px dashed var(--border); padding:1rem; width:100%; border-radius:8px; color:var(--text);">
            </div>
            <div class="form-group">
                <label>Page Count <span style="font-weight:normal; color:var(--text-muted);">(optional)</span></label>
                <input type="number" name="page_count" min="1" placeholder="e.g. 2">
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.5rem;">
                <button type="button" onclick="document.getElementById('upload-modal').style.display='none'"
                        style="padding:0.6rem 1.25rem; background:transparent; border:1px solid var(--border); color:var(--text); border-radius:8px; cursor:pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary">Upload</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Hide modal on successful upload via session flash
    @if(session('success'))
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('upload-modal').style.display = 'none';
        });
    @endif
</script>
@endsection
