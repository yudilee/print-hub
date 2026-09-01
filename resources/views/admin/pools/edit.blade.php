@extends('admin.layout')
@section('title', $pool->exists ? 'Edit Pool: ' . $pool->name : 'New Printer Pool')

@section('content')
<x-breadcrumb :items="[['label' => 'Printer Pools', 'url' => route('admin.pools')], ['label' => $pool->exists ? 'Edit Pool' : 'New Pool']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">{{ $pool->exists ? 'Edit Pool: ' . $pool->name : 'Create Printer Pool' }}</h2>
        <p class="text-xs text-slate-400">Configure distribution strategies and assign agent hardware</p>
    </div>
    <a href="{{ route('admin.pools') }}" class="btn-secondary btn-sm">← Back to Pools</a>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xs">
    <form action="{{ $pool->exists ? route('admin.pools.update', $pool) : route('admin.pools.store') }}" method="POST">
        @csrf
        @if($pool->exists)
            @method('PUT')
        @endif

        @if($errors->any())
            <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Pool Settings -->
        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-4 mb-6">
            <span class="text-xs font-bold text-blue-400 uppercase tracking-wider block pb-1 border-b border-slate-800">Pool Properties</span>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label for="name" class="block text-xs font-semibold text-slate-400 mb-1">Pool Name <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $pool->name) }}" required placeholder="e.g. Invoicing Pool"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label for="strategy" class="block text-xs font-semibold text-slate-400 mb-1">Balancing Strategy</label>
                    <select name="strategy" id="strategy" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="round_robin" {{ old('strategy', $pool->strategy) == 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                        <option value="least_busy" {{ old('strategy', $pool->strategy) == 'least_busy' ? 'selected' : '' }}>Least Busy</option>
                        <option value="random" {{ old('strategy', $pool->strategy) == 'random' ? 'selected' : '' }}>Random</option>
                        <option value="failover" {{ old('strategy', $pool->strategy) == 'failover' ? 'selected' : '' }}>Failover (Priority Order)</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="description" class="block text-xs font-semibold text-slate-400 mb-1">Description / Notes</label>
                <textarea name="description" id="description" rows="2" placeholder="Purpose or location of this pool..."
                    class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">{{ old('description', $pool->description) }}</textarea>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', $pool->active ?? true) ? 'checked' : '' }} class="rounded border-slate-700 bg-slate-900 text-blue-600">
                    <span>Pool Active & Available for Job Dispatch</span>
                </label>
            </div>
        </div>

        <!-- Printers Container -->
        <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-4 mb-6">
            <div class="flex items-center justify-between pb-1 border-b border-slate-800">
                <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Assigned Printer Devices</span>
                @if($pool->exists)
                <form action="{{ route('admin.pools.reset-health', $pool) }}" method="POST" class="inline" onsubmit="return confirm('Reset health metrics for this pool?')">
                    @csrf
                    <button type="submit" class="btn-warning btn-sm">Reset Health Metrics</button>
                </form>
                @endif
            </div>

            <div id="printers-container" class="space-y-3">
                @php
                    $existingPrinters = $pool->exists ? $pool->printers : old('printers', []);
                @endphp

                @foreach($existingPrinters as $idx => $pp)
                    @php 
                        $ppData = $pp instanceof \App\Models\PrinterPoolPrinter ? $pp : (object) $pp; 
                        $ownerAgent = $agents->first(function($a) use ($ppData) {
                            return in_array($ppData->printer_name, $a->printers ?? []);
                        });
                        $ownerAgentId = $ownerAgent ? $ownerAgent->id : '';
                    @endphp
                    <div class="printer-row p-3 rounded-xl bg-slate-900 border border-slate-800 flex flex-wrap md:flex-nowrap items-center gap-3">
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-[10px] text-slate-500 mb-1">Agent Station</label>
                            <select class="agent-select w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200" onchange="updateRowPrinters(this)" required>
                                <option value="">-- Select Agent --</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" data-branch="{{ $agent->branch_id }}" {{ $ownerAgentId == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }} ({{ $agent->branch->name ?? 'No Branch' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-[10px] text-slate-500 mb-1">Printer Device</label>
                            <select name="printers[{{ $idx }}][name]" class="printer-select w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200" required>
                                <option value="">-- Select Printer --</option>
                                @if($ownerAgent)
                                    @foreach($ownerAgent->printers as $printer)
                                        <option value="{{ $printer }}" {{ $ppData->printer_name == $printer ? 'selected' : '' }}>{{ $printer }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="w-24">
                            <label class="block text-[10px] text-slate-500 mb-1">Priority</label>
                            <input type="number" name="printers[{{ $idx }}][priority]" value="{{ old('printers.' . $idx . '.priority', $ppData->priority ?? $idx) }}" min="0"
                                class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200 text-center">
                        </div>
                        <div class="w-24 pt-4">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-400 cursor-pointer">
                                <input type="checkbox" name="printers[{{ $idx }}][active]" value="1" {{ old('printers.' . $idx . '.active', $ppData->active ?? true) ? 'checked' : '' }} class="rounded border-slate-700 bg-slate-950 text-blue-600">
                                <span>Active</span>
                            </label>
                        </div>
                        <div class="pt-4">
                            <button type="button" class="p-1.5 rounded-lg text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition" onclick="removeRow(this)" title="Remove">
                                <x-icon name="trash" size="14" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="button" class="btn-secondary btn-sm" onclick="addPrinterRow()">
                <x-icon name="plus" size="13" />
                <span>Add Printer to Pool</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <button type="submit" class="btn-primary">{{ $pool->exists ? 'Update Pool' : 'Create Pool' }}</button>
            <a href="{{ route('admin.pools') }}" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
const agentPrinters = {
    @foreach($agents as $agent)
        "{{ $agent->id }}": {!! json_encode($agent->printers ?? []) !!},
    @endforeach
};

const agentBranches = {
    @foreach($agents as $agent)
        "{{ $agent->id }}": "{{ $agent->branch_id }}",
    @endforeach
};

let printerIdx = {{ count($existingPrinters) }};

function addPrinterRow() {
    const container = document.getElementById('printers-container');
    const idx = printerIdx++;
    
    let agentOptionsHtml = '<option value="">-- Select Agent --</option>';
    @foreach($agents as $agent)
        agentOptionsHtml += `<option value="{{ $agent->id }}" data-branch="{{ $agent->branch_id }}">{{ $agent->name }} ({{ $agent->branch->name ?? 'No Branch' }})</option>`;
    @endforeach

    const html = `
        <div class="printer-row p-3 rounded-xl bg-slate-900 border border-slate-800 flex flex-wrap md:flex-nowrap items-center gap-3">
            <div class="flex-1 min-w-[180px]">
                <label class="block text-[10px] text-slate-500 mb-1">Agent Station</label>
                <select class="agent-select w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200" onchange="updateRowPrinters(this)" required>
                    ${agentOptionsHtml}
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-[10px] text-slate-500 mb-1">Printer Device</label>
                <select name="printers[${idx}][name]" class="printer-select w-full px-2.5 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200" required>
                    <option value="">-- Select Printer --</option>
                </select>
            </div>
            <div class="w-24">
                <label class="block text-[10px] text-slate-500 mb-1">Priority</label>
                <input type="number" name="printers[${idx}][priority]" value="${idx}" min="0" class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-slate-200 text-center">
            </div>
            <div class="w-24 pt-4">
                <label class="inline-flex items-center gap-1.5 text-xs text-slate-400 cursor-pointer">
                    <input type="checkbox" name="printers[${idx}][active]" value="1" checked class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Active</span>
                </label>
            </div>
            <div class="pt-4">
                <button type="button" class="p-1.5 rounded-lg text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 transition" onclick="removeRow(this)" title="Remove">
                    <x-icon name="trash" size="14" />
                </button>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
    applyBranchRestrictions();
}

function updateRowPrinters(agentSelect) {
    const row = agentSelect.closest('.printer-row');
    const printerSelect = row.querySelector('.printer-select');
    const agentId = agentSelect.value;

    printerSelect.innerHTML = '<option value="">-- Select Printer --</option>';

    if (agentId && agentPrinters[agentId]) {
        agentPrinters[agentId].forEach(printer => {
            const opt = document.createElement('option');
            opt.value = printer;
            opt.textContent = printer;
            printerSelect.appendChild(opt);
        });
    }

    applyBranchRestrictions();
}

function removeRow(button) {
    button.closest('.printer-row').remove();
    applyBranchRestrictions();
}

function applyBranchRestrictions() {
    const rows = document.querySelectorAll('.printer-row');
    let lockedBranchId = null;

    for (let i = 0; i < rows.length; i++) {
        const agentSelect = rows[i].querySelector('.agent-select');
        if (agentSelect && agentSelect.value) {
            lockedBranchId = agentBranches[agentSelect.value];
            break;
        }
    }

    rows.forEach(row => {
        const agentSelect = row.querySelector('.agent-select');
        if (agentSelect) {
            agentSelect.querySelectorAll('option').forEach(opt => {
                if (!opt.value) return;
                const branchId = opt.getAttribute('data-branch');
                opt.disabled = (lockedBranchId !== null && branchId !== lockedBranchId);
            });
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    applyBranchRestrictions();
});
</script>
@endsection
