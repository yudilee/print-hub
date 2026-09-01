@extends('admin.layout')
@section('title', 'Backup & Restore')

@section('content')
<x-breadcrumb :items="[['label' => 'Backup & Restore']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Disaster Recovery & Snapshots</h2>
        <p class="text-xs text-slate-400">Export and import complete cluster configurations (agents, profiles, templates, settings)</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Export Section --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
        <div>
            <h3 class="text-xs font-bold text-blue-400 uppercase tracking-wider mb-2">📤 Export Hub Configuration</h3>
            <p class="text-xs text-slate-400 mb-4">
                Generates a portable JSON archive of all active print queues, workstation agents, printer pools, policies, and template definitions.
            </p>
        </div>
        <form action="{{ route('admin.backup.export') }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary btn-sm">
                <x-icon name="download" size="13" />
                <span>Export Snapshot JSON</span>
            </button>
        </form>
    </div>

    {{-- Import Section --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">📥 Restore from Snapshot</h3>
        <p class="text-xs text-slate-400 mb-4">
            Import an existing configuration JSON. Changes are processed transactionally with automatic rollback on error.
        </p>

        <form action="{{ route('admin.backup.import') }}" method="POST" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div class="p-3 rounded-xl bg-slate-950 border border-dashed border-slate-800">
                <input type="file" name="backup_file" accept=".json" required
                    class="text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="btn-warning btn-sm" onclick="return confirm('Importing will overwrite conflicting entities. Proceed?')">
                    Import Configuration
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Existing Backups Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">💾 Available Backup Archives</h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Filename</th>
                    <th class="px-5 py-3.5">Archive Size</th>
                    <th class="px-5 py-3.5">Generated</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($backupFiles as $file)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $file['name'] }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">{{ $file['size'] }}</td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">{{ $file['modified'] }}</td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.backup.download', basename($file['name'])) }}" class="btn-secondary btn-sm">⬇ Download</a>
                            <form action="{{ route('admin.backup.delete', basename($file['name'])) }}" method="POST" onsubmit="return confirm('Delete backup file?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4">
                        <x-empty-state icon="💾" title="No backup archives stored" description="Export a snapshot above to generate recovery points." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
