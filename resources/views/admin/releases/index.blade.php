@extends('admin.layout')
@section('title', 'Agent Releases')

@section('content')
<x-breadcrumb :items="[['label' => 'Agent Software Updates']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Agent Releases & Binary OTA</h2>
        <p class="text-xs text-slate-400">Deploy updated workstation binaries (Windows, Linux, macOS) for automatic client updates</p>
    </div>
</div>

{{-- Upload New Release Form --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
        Publish Agent Release
    </h3>

    <form action="{{ route('admin.releases.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Semver Version <span class="text-rose-500">*</span></label>
                <input type="text" name="version" required placeholder="e.g. 3.2.0" value="{{ old('version') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Target Platform <span class="text-rose-500">*</span></label>
                <select name="platform" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="">-- Select --</option>
                    <option value="linux" @selected(old('platform') === 'linux')>Linux (.deb, .rpm, .tar.gz)</option>
                    <option value="windows" @selected(old('platform') === 'windows')>Windows (.exe, .msi, .zip)</option>
                    <option value="macos" @selected(old('platform') === 'macos')>macOS (.dmg, .pkg, .tar.gz)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Release Channel <span class="text-rose-500">*</span></label>
                <select name="channel" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="stable" @selected(old('channel') === 'stable')>Stable</option>
                    <option value="beta" @selected(old('channel') === 'beta')>Beta</option>
                    <option value="alpha" @selected(old('channel') === 'alpha')>Alpha</option>
                </select>
            </div>

            <div class="pt-6">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory')) class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Mandatory Upgrade</span>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-400 mb-1">Changelog / Release Notes</label>
                <textarea name="release_notes" rows="2" placeholder="Summary of bugfixes and improvements..."
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">{{ old('release_notes') }}</textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Installer Package File <span class="text-rose-500">*</span></label>
                <div class="p-2.5 rounded-xl bg-slate-950 border border-dashed border-slate-800 text-center">
                    <input type="file" name="installer_file" required accept=".exe,.msi,.deb,.rpm,.AppImage,.dmg,.pkg,.tar.gz,.zip"
                        class="text-xs text-slate-300 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer">
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary btn-sm">
                <x-icon name="plus" size="13" />
                <span>Upload & Publish Release</span>
            </button>
        </div>
    </form>
</div>

{{-- Releases Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Available Releases: <span class="text-white font-mono font-bold">{{ $releases->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Version</th>
                    <th class="px-5 py-3.5">Platform</th>
                    <th class="px-5 py-3.5">Channel</th>
                    <th class="px-5 py-3.5">File & Size</th>
                    <th class="px-5 py-3.5">SHA-256 Checksum</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($releases as $release)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        v{{ $release->version }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-300">
                        @switch($release->platform)
                            @case('linux') 🐧 Linux @break
                            @case('windows') 🪟 Windows @break
                            @case('macos') 🍏 macOS @break
                            @default {{ $release->platform }}
                        @endswitch
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge {{ $release->channel === 'stable' ? 'badge-success' : ($release->channel === 'beta' ? 'badge-warning' : 'badge-danger') }} text-[10px] uppercase">
                            {{ $release->channel }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="font-mono text-slate-300">{{ \Illuminate\Support\Str::limit($release->file_original_name, 25) }}</span>
                        <span class="block text-[10px] text-slate-500 font-mono">{{ $release->formatted_size }}</span>
                    </td>
                    <td class="px-5 py-3.5 font-mono text-[11px] text-slate-400">
                        <span title="{{ $release->sha256_hash }}">{{ \Illuminate\Support\Str::limit($release->sha256_hash, 16) }}</span>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($release->is_latest)
                            <span class="badge badge-info text-[10px]">LATEST</span>
                        @else
                            <span class="text-slate-500 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            @if(!$release->is_latest)
                            <form action="{{ route('admin.releases.mark-latest', $release) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="btn-secondary btn-sm" title="Mark as Latest" onclick="return confirm('Promote this release to latest?')">★</button>
                            </form>
                            @endif
                            <form action="{{ route('admin.releases.destroy', $release) }}" method="POST" class="inline" onsubmit="return confirm('Delete release?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state icon="📦" title="No agent binaries published" description="Upload installer packages to enable automatic desktop agent updates." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
