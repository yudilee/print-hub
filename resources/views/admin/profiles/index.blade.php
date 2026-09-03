@extends('admin.layout')
@section('title', 'Print Queues')

@section('content')
<x-breadcrumb :items="[['label' => 'Print Queues']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Queues & Virtual Profiles</h2>
        <p class="text-xs text-slate-400">Define paper standards, driver settings, tray routing, and dynamic watermarks for branch printers</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.profiles.create') }}" class="btn-primary btn-sm flex items-center gap-1.5 shadow-lg shadow-blue-500/20">
            <x-icon name="plus" size="14" />
            <span>Create New Queue</span>
        </a>
    </div>
</div>


{{-- Quick Stats Summary --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
    <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1">Total Queues</span>
        <span class="text-lg font-bold text-white">{{ $profiles->count() }}</span>
    </div>
    <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1">Branches Covered</span>
        <span class="text-lg font-bold text-blue-400">{{ $profiles->pluck('branch_id')->unique()->filter()->count() }}</span>
    </div>
    <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1">Pooled Queues</span>
        <span class="text-lg font-bold text-emerald-400">{{ $profiles->whereNotNull('pool_id')->count() }}</span>
    </div>
    <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800">
        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1">Direct Printers</span>
        <span class="text-lg font-bold text-amber-400">{{ $profiles->whereNull('pool_id')->count() }}</span>
    </div>
</div>

{{-- Queues Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <h3 class="text-sm font-bold text-white">Configured Queues</h3>
            <span class="badge badge-info">{{ $profiles->count() }}</span>
        </div>
        <div class="w-full sm:w-64">
            <input type="text" id="queue-search" placeholder="Search queues or printers..." onkeyup="filterQueues(this.value)"
                   class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder:text-slate-500 focus:outline-none focus:border-blue-500">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-slate-300">
            <thead class="bg-slate-950/60 text-[10px] uppercase font-bold text-slate-500 tracking-wider border-b border-slate-800">
                <tr>
                    <th class="px-5 py-3">Queue Name &amp; Description</th>
                    <th class="px-5 py-3">Branch Scoping</th>
                    <th class="px-5 py-3">Assigned Workstation Agent</th>
                    <th class="px-5 py-3">Hardware Destination</th>
                    <th class="px-5 py-3">Paper &amp; Layout</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="queues-table-body" class="divide-y divide-slate-800/60">
                @forelse($profiles as $profile)
                <tr class="queue-row hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-mono font-bold text-blue-400 text-xs block queue-slug">{{ $profile->name }}</span>
                        @if($profile->description)
                            <span class="text-[11px] text-slate-400 block queue-desc">{{ $profile->description }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($profile->branch)
                            <div class="flex items-center gap-1.5">
                                <span class="badge badge-info">{{ $profile->branch->company->code ?? '' }}</span>
                                <span class="text-xs text-slate-200 font-medium">{{ $profile->branch->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-warning">All Branches</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($profile->agent)
                            <span class="badge {{ $profile->agent->isOnline() ? 'badge-success' : 'badge-danger' }} flex items-center gap-1.5 w-fit">
                                <span class="w-1.5 h-1.5 rounded-full {{ $profile->agent->isOnline() ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                                <span>{{ $profile->agent->name }}</span>
                            </span>
                        @else
                            <span class="text-xs text-slate-500 italic">Generic Node</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs">
                        @if($profile->pool)
                            <span class="badge badge-info">🏊 Pool: {{ $profile->pool->name }}</span>
                        @else
                            <span class="text-slate-200">{{ $profile->default_printer ?: 'Agent Default' }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1.5">
                            <span class="badge badge-info">{{ $profile->paper_size }}</span>
                            @if($profile->duplex && $profile->duplex !== 'one-sided')
                                <span class="badge badge-warning text-[10px]">Duplex</span>
                            @endif
                        </div>
                        <span class="text-[10px] text-slate-500 block capitalize mt-0.5">{{ $profile->orientation }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.profiles.edit', $profile) }}" class="btn-secondary btn-sm" title="Edit queue settings">
                                Edit
                            </a>
                            <a href="{{ route('admin.profiles.clone', $profile) }}" class="btn-secondary btn-sm" title="Clone queue into new">
                                Clone
                            </a>
                            <button type="button" class="btn-secondary btn-sm" onclick="openTestModal('{{ $profile->id }}', '{{ $profile->name }}', '{{ $profile->agent->name ?? 'Any Online Agent' }}', '{{ $profile->pool ? 'Pool: ' . $profile->pool->name : ($profile->default_printer ?: 'Default') }}')">
                                Test
                            </button>
                            <form action="{{ route('admin.profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete print queue \'{{ $profile->name }}\'?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="p-8 text-center">
                        <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                            <div class="w-12 h-12 rounded-2xl bg-slate-800 flex items-center justify-center text-xl mb-3">🖨️</div>
                            <h4 class="text-sm font-bold text-white mb-1">No print queues configured yet</h4>
                            <p class="text-xs text-slate-400 mb-4 text-center">Create your first virtual print queue to define paper formats, hardware margins, and target branch printers.</p>
                            <a href="{{ route('admin.profiles.create') }}" class="btn-primary btn-sm">
                                <x-icon name="plus" size="13" />
                                <span>Create Your First Queue</span>
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Test Print Modal --}}
<div id="test-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 id="modal-title" class="text-base font-bold text-white">Test Print Queue</h3>
            <button onclick="closeTestModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <p class="text-xs text-slate-400 mb-4">
            Upload a sample PDF to dispatch directly to target destination:
            <strong id="modal-target-info" class="block text-blue-400 font-mono mt-1"></strong>
        </p>

        <form id="test-print-form" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="p-4 rounded-xl bg-slate-950 border border-dashed border-slate-800 text-center">
                <input type="file" name="file" accept="application/pdf" required class="text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 cursor-pointer">
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="closeTestModal()">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">🚀 Send Test Job</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterQueues(query) {
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.queue-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function openTestModal(profileId, profileName, agentName, printerName) {
    document.getElementById('modal-title').textContent = 'Test Print: ' + profileName;
    document.getElementById('modal-target-info').textContent = agentName + ' → ' + printerName;
    document.getElementById('test-print-form').action = '/profiles/' + profileId + '/test-print';
    document.getElementById('test-modal').classList.remove('hidden');
}

function closeTestModal() {
    document.getElementById('test-modal').classList.add('hidden');
}
</script>
@endsection
