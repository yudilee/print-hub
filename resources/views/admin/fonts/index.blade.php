@extends('admin.layout')
@section('title', 'Fonts Management')

@section('content')
<x-breadcrumb :items="[['label' => 'Custom Typography']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Custom Typography & Typefaces</h2>
        <p class="text-xs text-slate-400">Manage TrueType (.ttf) and OpenType (.otf) fonts used across print templates</p>
    </div>
    <button class="btn-primary btn-sm" onclick="document.getElementById('upload-modal').classList.remove('hidden')">
        <x-icon name="plus" size="13" />
        <span>Upload Font File</span>
    </button>
</div>

{{-- Filters Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xs">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <x-icon name="search" size="14" class="text-slate-500 absolute left-3 top-2.5" />
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search fonts..."
                class="w-full pl-9 pr-4 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
        </div>

        <select name="file_type" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Formats</option>
            <option value="ttf" {{ request('file_type') === 'ttf' ? 'selected' : '' }}>TTF</option>
            <option value="otf" {{ request('file_type') === 'otf' ? 'selected' : '' }}>OTF</option>
        </select>

        <select name="is_active" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Statuses</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
        </select>

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.fonts') }}" class="btn-secondary btn-sm">Reset</a>
    </form>
</div>

{{-- Fonts Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Uploaded Fonts: <span class="text-white font-mono font-bold">{{ $fonts->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Font Name</th>
                    <th class="px-5 py-3.5">Family</th>
                    <th class="px-5 py-3.5">Style</th>
                    <th class="px-5 py-3.5">Type</th>
                    <th class="px-5 py-3.5">Size</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($fonts as $font)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-bold text-white">
                        {{ $font->name }}
                        @if($font->preview_text)
                            <span class="block text-[10px] text-slate-500 font-normal italic mt-0.5">{{ Str::limit($font->preview_text, 35) }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-blue-400">
                        {{ $font->font_family }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info">{{ ucfirst($font->font_style) }}</span>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-slate-400">
                        {{ strtoupper($font->file_type) }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ number_format($font->file_size / 1024, 1) }} KB
                    </td>
                    <td class="px-5 py-3.5">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" {{ $font->is_active ? 'checked' : '' }} onchange="toggleActive({{ $font->id }}, this.checked)" class="rounded border-slate-700 bg-slate-950 text-blue-600">
                            <span class="text-xs {{ $font->is_active ? 'text-emerald-400' : 'text-slate-500' }}">{{ $font->is_active ? 'Active' : 'Inactive' }}</span>
                        </label>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.fonts.download', $font) }}" class="btn-secondary btn-sm" title="Download">⬇</a>
                            <button class="btn-secondary btn-sm" onclick="openEditModal({{ json_encode($font) }})">Edit</button>
                            <form action="{{ route('admin.fonts.destroy', $font) }}" method="POST" onsubmit="return confirm('Delete font?')" class="inline">
                                @csrf @method('DELETE')
                                <button class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state icon="🔤" title="No custom fonts uploaded" description="Upload TTF or OTF fonts to customize label designs." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($fonts->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $fonts->links() }}
    </div>
    @endif
</div>

{{-- Upload Modal --}}
<div id="upload-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Upload Custom Typeface</h3>
            <button onclick="document.getElementById('upload-modal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form action="{{ route('admin.fonts.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Display Name</label>
                <input type="text" name="name" required placeholder="e.g. JetBrains Mono Bold"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Family Alias (Optional)</label>
                <input type="text" name="font_family" placeholder="Auto-detected if empty"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div class="p-4 rounded-xl bg-slate-950 border border-dashed border-slate-800 text-center">
                <input type="file" name="font_file" accept=".ttf,.otf" required
                    class="text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer">
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('upload-modal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Upload Font</button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Edit Font Metadata</h3>
            <button onclick="document.getElementById('edit-modal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="edit-form" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Display Name</label>
                <input type="text" name="name" id="edit-name" required
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Preview Sample Text</label>
                <textarea name="preview_text" id="edit-preview-text" rows="2" placeholder="Sample string..."
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"></textarea>
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Font Active & Available in Designer</span>
                </label>
            </div>
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('edit-modal').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(font) {
    document.getElementById('edit-form').action = `/fonts/${font.id}`;
    document.getElementById('edit-name').value = font.name;
    document.getElementById('edit-preview-text').value = font.preview_text || '';
    document.getElementById('edit-active').checked = font.is_active;
    document.getElementById('edit-modal').classList.remove('hidden');
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
    });
}
</script>
@endsection
