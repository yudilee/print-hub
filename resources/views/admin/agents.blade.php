@extends('admin.layout')
@section('title', 'Print Agents')

@section('content')
<x-breadcrumb :items="[['label' => 'Print Agents']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Agents & Workstations</h2>
        <p class="text-xs text-slate-400">Manage Trayprint connector nodes, device profiles, and auto-sync status</p>
    </div>
</div>

{{-- Register Agent Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <h3 class="text-sm font-bold text-white mb-3">Register New Print Agent</h3>
    <form action="{{ route('admin.agents.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-400 mb-1">Agent Name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" required placeholder="e.g. Front Desk PC"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label for="branch_id" class="block text-xs font-semibold text-slate-400 mb-1">Branch</label>
                <select name="branch_id" id="branch_id"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="">-- Global (All Branches) --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="location" class="block text-xs font-semibold text-slate-400 mb-1">Location</label>
                <input type="text" name="location" id="location" placeholder="e.g. Lobby counter"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label for="department" class="block text-xs font-semibold text-slate-400 mb-1">Department</label>
                <input type="text" name="department" id="department" placeholder="e.g. Operations"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>
        <button type="submit" class="btn-primary btn-sm">
            <x-icon name="plus" size="13" />
            <span>Register Agent</span>
        </button>
    </form>
</div>

{{-- Agent List Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs" x-data="{ search: '' }">
    <div class="p-4 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Connected Agents: <span class="text-white font-mono font-bold">{{ $agents->count() }}</span>
        </h3>
        <div class="relative">
            <x-icon name="search" size="14" class="text-slate-500 absolute left-3 top-2.5" />
            <input type="text" x-model="search" placeholder="Search agents..."
                class="pl-9 pr-4 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 w-full sm:w-64">
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Agent Details</th>
                    <th class="px-5 py-3.5">Branch / Org</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Installed Printers</th>
                    <th class="px-5 py-3.5">Telemetry & Heartbeat</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($agents as $agent)
                <tr x-show="search === '' || $el.textContent.toLowerCase().includes(search.toLowerCase())" class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-bold text-white">{{ $agent->name }}</span>
                        @if($agent->department || $agent->location)
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                {{ $agent->department ?? 'General' }} {{ $agent->location ? '• ' . $agent->location : '' }}
                            </div>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($agent->branch)
                            <span class="badge badge-info">{{ $agent->branch->company->code ?? '' }}</span>
                            <span class="text-xs text-slate-200 ml-1">{{ $agent->branch->name }}</span>
                        @else
                            <span class="text-xs text-slate-500 italic">Global Node</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if(!$agent->is_active)
                            <span class="badge badge-danger">Disabled</span>
                        @elseif($agent->isOnline())
                            <span class="badge badge-success"><span class="dot dot-green"></span> Online</span>
                        @else
                            <span class="badge badge-danger"><span class="dot dot-red"></span> Offline</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if(!empty($agent->printers))
                            <div class="flex flex-wrap gap-1 max-w-xs">
                                @foreach(array_slice($agent->printers, 0, 3) as $printer)
                                    <span class="px-2 py-0.5 rounded-md bg-slate-950 border border-slate-800 text-[10px] font-mono text-slate-300">{{ $printer }}</span>
                                @endforeach
                                @if(count($agent->printers) > 3)
                                    <span class="text-[10px] text-slate-500 font-mono">+{{ count($agent->printers) - 3 }} more</span>
                                @endif
                            </div>
                        @else
                            <span class="text-xs text-slate-500 italic">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        <div>Seen: {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}</div>
                        <div class="text-[10px] text-slate-500">Jobs: {{ $agent->jobs_count ?? 0 }}</div>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.agents.activity', $agent) }}" class="btn-secondary btn-sm" title="Timeline">Activity</a>
                            <button type="button" class="btn-secondary btn-sm"
                                onclick="openEditModal({{ $agent->id }}, '{{ e($agent->name) }}', '{{ $agent->branch_id }}', '{{ e($agent->location ?? '') }}', '{{ e($agent->department ?? '') }}', {{ $agent->is_active ? 'true' : 'false' }})">
                                Edit
                            </button>
                            <form action="{{ route('admin.agents.destroy', $agent) }}" method="POST" onsubmit="return confirm('Remove this agent?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="🖥️" title="No print agents found" description="Register your first print agent to link local printers." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Quick Setup Guide --}}
@if($agents->count() > 0)
<div class="mt-6 bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
    <h3 class="text-sm font-bold text-white mb-2">⚡ Quick Setup Guide</h3>
    <p class="text-xs text-slate-400 mb-3">On the workstation, configure <code class="text-blue-400 font-mono">config.json</code> inside Trayprint directory:</p>
    <pre class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 overflow-x-auto">{
  "hub_url": "{{ url('/') }}",
  "agent_key": "<span class="text-amber-400">PASTE_AGENT_KEY_HERE</span>"
}</pre>
</div>
@endif

{{-- Edit Agent Modal --}}
<div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Edit Agent</h3>
            <button onclick="closeEditModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="edit-form" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="edit_name" class="block text-xs font-semibold text-slate-400 mb-1">Agent Name <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label for="edit_branch_id" class="block text-xs font-semibold text-slate-400 mb-1">Branch</label>
                <select name="branch_id" id="edit_branch_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="">-- Global (All Branches) --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="edit_location" class="block text-xs font-semibold text-slate-400 mb-1">Location</label>
                    <input type="text" name="location" id="edit_location" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label for="edit_department" class="block text-xs font-semibold text-slate-400 mb-1">Department</label>
                    <input type="text" name="department" id="edit_department" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-0">
                    <span>Agent Enabled & Active</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                <button type="button" class="btn-warning btn-sm" onclick="regenerateKey()">Regenerate Key</button>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn-secondary btn-sm" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-primary btn-sm">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentAgentId = null;

function openEditModal(id, name, branchId, location, department, isActive) {
    currentAgentId = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_branch_id').value = branchId || '';
    document.getElementById('edit_location').value = location || '';
    document.getElementById('edit_department').value = department || '';
    document.getElementById('edit_is_active').checked = isActive;
    document.getElementById('edit-form').action = '/agents/' + id;
    document.getElementById('edit-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}

function regenerateKey() {
    if (confirm('This will invalidate the current agent key. The agent will need to be reconfigured. Continue?')) {
        const form = document.getElementById('edit-form');
        form.action = '/agents/' + currentAgentId + '/regenerate-key';
        form.method = 'POST';
        const methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) methodInput.remove();
        form.submit();
    }
}
</script>
@endsection
