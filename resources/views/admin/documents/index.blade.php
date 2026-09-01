@extends('admin.layout')
@section('title', 'Documents')

@section('content')
<x-breadcrumb :items="[['label' => 'Documents']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Document Repository & Retention</h2>
        <p class="text-xs text-slate-400">Uploaded static PDFs and templates linked with print dispatch tasks</p>
    </div>
    <div class="flex items-center gap-2">
        <form method="POST" action="{{ route('admin.documents.purge-expired') }}"
              onsubmit="return confirm('Purge all documents past their retain_until date? This action is irreversible.')"
              class="inline">
            @csrf
            <button type="submit" class="btn-warning btn-sm">
                <x-icon name="trash" size="13" />
                <span>Purge Expired</span>
            </button>
        </form>
        <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" class="btn-primary btn-sm">
            <x-icon name="plus" size="13" />
            <span>Upload Document</span>
        </button>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Documents: <span class="text-white font-mono font-bold">{{ $documents->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Filename</th>
                    <th class="px-5 py-3.5">Format</th>
                    <th class="px-5 py-3.5">Size</th>
                    <th class="px-5 py-3.5">Version</th>
                    <th class="px-5 py-3.5">Retention Policy</th>
                    <th class="px-5 py-3.5">Uploaded By</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($documents as $doc)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-medium text-white max-w-xs truncate">
                        {{ $doc->original_name }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info">{{ strtoupper(pathinfo($doc->original_name, PATHINFO_EXTENSION)) }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">{{ $doc->formatted_size }}</td>
                    <td class="px-5 py-3.5 text-xs font-mono">
                        @if($doc->version > 1 || $doc->subsequent_versions_count > 0)
                            <a href="{{ route('admin.documents.versions', $doc->id) }}" class="text-blue-400 hover:underline">
                                v{{ $doc->version }}
                                @if($doc->subsequent_versions_count > 0)
                                    <span class="text-slate-500">(+{{ $doc->subsequent_versions_count }})</span>
                                @endif
                            </a>
                        @else
                            <span class="text-slate-500">v1</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($doc->retain_until)
                            @if($doc->retain_until->isPast())
                                <span class="badge badge-danger">Expired {{ $doc->retain_until->format('d M Y') }}</span>
                            @else
                                <span class="text-slate-300 font-mono">{{ $doc->retain_until->format('d M Y') }}</span>
                            @endif
                        @else
                            <span class="text-slate-500 italic">Permanent</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400">
                        {{ $doc->user?->name ?? 'System' }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('api.documents.preview', $doc->id) }}" target="_blank" class="btn-secondary btn-sm">Preview</a>
                            <a href="{{ route('api.documents.download', $doc->id) }}" target="_blank" class="btn-secondary btn-sm">Download</a>
                            <form method="POST" action="{{ route('admin.documents.destroy', $doc->id) }}"
                                  onsubmit="return confirm('Delete this document?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state icon="📄" title="No documents found" description="Upload PDFs or graphics to queue with custom print jobs." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($documents->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $documents->links() }}
    </div>
    @endif
</div>

{{-- Upload Modal --}}
<div id="upload-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Upload New Document</h3>
            <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.documents.upload') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="p-4 rounded-xl bg-slate-950 border border-dashed border-slate-800 text-center">
                <input type="file" name="file" accept=".pdf,.png,.jpg,.jpeg" required
                    class="text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Retain Until (Optional)</label>
                <input type="date" name="retain_until" min="{{ date('Y-m-d', strtotime('+1 day')) }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="auto_delete" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Auto-delete file when retention expires</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('upload-modal').classList.add('hidden')" class="btn-secondary btn-sm">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Upload File</button>
            </div>
        </form>
    </div>
</div>
@endsection
