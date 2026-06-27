@extends('admin.layout')
@section('title', $pool->exists ? 'Edit Pool: ' . $pool->name : 'New Printer Pool')

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>{{ $pool->exists ? 'Edit Pool: ' . $pool->name : 'New Printer Pool' }}</h1>
            <p>{{ $pool->exists ? 'Modify the pool configuration.' : 'Create a new printer pool for load-balanced distribution.' }}</p>
        </div>
        <a href="{{ route('admin.pools') }}" class="btn btn-secondary">← Back to Pools</a>
    </div>
</div>

<div class="card">
    <form action="{{ $pool->exists ? route('admin.pools.update', $pool) : route('admin.pools.store') }}" method="POST">
        @csrf
        @if($pool->exists)
            @method('PUT')
        @endif

        @if($errors->any())
            <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Pool Details --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem;">
                📋 Pool Details
            </legend>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="name">Pool Name <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $pool->name) }}" required placeholder="e.g. Invoice Printers">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="strategy">Distribution Strategy</label>
                    <select name="strategy" id="strategy">
                        <option value="round_robin" {{ old('strategy', $pool->strategy) == 'round_robin' ? 'selected' : '' }}>Round Robin</option>
                        <option value="least_busy" {{ old('strategy', $pool->strategy) == 'least_busy' ? 'selected' : '' }}>Least Busy</option>
                        <option value="random" {{ old('strategy', $pool->strategy) == 'random' ? 'selected' : '' }}>Random</option>
                        <option value="failover" {{ old('strategy', $pool->strategy) == 'failover' ? 'selected' : '' }}>Failover</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 1; display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="active" value="1" {{ old('active', $pool->active ?? true) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Active
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea name="description" id="description" rows="2" placeholder="What is this pool for?">{{ old('description', $pool->description) }}</textarea>
            </div>
        </fieldset>

        {{-- Printers in Pool --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem;">
                🖨️ Printers in Pool
            </legend>

            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">
                Add printers to this pool. The strategy determines how jobs are distributed.
                @if($pool->exists && $pool->strategy === 'failover')
                    <span class="badge badge-info" style="margin-left: 0.5rem;">Health tracking active</span>
                @endif
            </p>

            <div id="printers-container">
                @php
                    $existingPrinters = $pool->exists ? $pool->printers : old('printers', []);
                @endphp

                @if(count($existingPrinters) > 0)
                    @foreach($existingPrinters as $idx => $pp)
                        @php 
                            $ppData = $pp instanceof \App\Models\PrinterPoolPrinter ? $pp : (object) $pp; 
                            $ownerAgent = $agents->first(function($a) use ($ppData) {
                                return in_array($ppData->printer_name, $a->printers ?? []);
                            });
                            $ownerAgentId = $ownerAgent ? $ownerAgent->id : '';
                        @endphp
                        <div class="printer-row" style="display: flex; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
                            <div class="form-group" style="flex: 1.5; margin-bottom: 0;">
                                <label>Target Workstation / Agent</label>
                                <select class="agent-select" onchange="updateRowPrinters(this)" required style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
                                    <option value="">-- Select Agent --</option>
                                    @foreach($agents as $agent)
                                        <option value="{{ $agent->id }}" data-branch="{{ $agent->branch_id }}" {{ $ownerAgentId == $agent->id ? 'selected' : '' }}>
                                            {{ $agent->name }} ({{ $agent->branch->name ?? 'No Branch' }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="flex: 1.5; margin-bottom: 0;">
                                <label>Printer Name</label>
                                <select name="printers[{{ $idx }}][name]" class="printer-select" required style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
                                    <option value="">-- Select Printer --</option>
                                    @if($ownerAgent)
                                        @foreach($ownerAgent->printers as $printer)
                                            <option value="{{ $printer }}" {{ $ppData->printer_name == $printer ? 'selected' : '' }}>{{ $printer }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="form-group" style="flex: 0.8; margin-bottom: 0;">
                                <label>Priority</label>
                                <input type="number" name="printers[{{ $idx }}][priority]" value="{{ old('printers.' . $idx . '.priority', $ppData->priority ?? $idx) }}" min="0" placeholder="0" style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
                            </div>
                            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                                <label>Status</label>
                                @php
                                    $isHealthy = $ppData->is_healthy ?? true;
                                    $failureCount = $ppData->failure_count ?? 0;
                                    $lastHealthy = isset($ppData->last_healthy_at) ? \Carbon\Carbon::parse($ppData->last_healthy_at)->diffForHumans() : null;
                                @endphp
                                <div style="display: flex; align-items: center; gap: 0.4rem; padding-top: 0.3rem;">
                                    @if($isHealthy)
                                        <span class="dot dot-green"></span>
                                        <span style="font-size: 0.8rem; color: var(--success);">Healthy</span>
                                    @else
                                        <span class="dot dot-red"></span>
                                        <span style="font-size: 0.8rem; color: var(--danger);">Unhealthy</span>
                                    @endif
                                </div>
                            </div>
                            <div class="form-group" style="flex: 0.8; display: flex; align-items: center; padding-bottom: 0.5rem; margin-bottom: 0;">
                                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.8rem;">
                                    <input type="checkbox" name="printers[{{ $idx }}][active]" value="1" {{ old('printers.' . $idx . '.active', $ppData->active ?? true) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                                    Active
                                </label>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" style="margin-bottom: 0.4rem;" title="Remove printer" aria-label="Remove printer from pool">✕</button>
                        </div>
                    @endforeach
                @endif
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="addPrinterRow()">+ Add Printer</button>
            @if($pool->exists)
                <form action="{{ route('admin.pools.reset-health', $pool) }}" method="POST" style="display: inline; margin-left: 0.5rem;" data-confirm="Reset health for ALL printers in this pool?">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-sm">🔄 Reset All Health</button>
                </form>
            @endif
        </fieldset>

        <div style="display: flex; gap: 10px; margin-top: 1rem;">
            <button type="submit" class="btn btn-primary">{{ $pool->exists ? 'Update Pool' : 'Create Pool' }}</button>
            <a href="{{ route('admin.pools') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<script>
// Dynamic data injected from PHP
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
    
    // Build agent options
    let agentOptionsHtml = '<option value="">-- Select Agent --</option>';
    @foreach($agents as $agent)
        agentOptionsHtml += `<option value="{{ $agent->id }}" data-branch="{{ $agent->branch_id }}">{{ $agent->name }} ({{ $agent->branch->name ?? 'No Branch' }})</option>`;
    @endforeach

    const html = `
        <div class="printer-row" style="display: flex; gap: 0.75rem; align-items: flex-end; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border);">
            <div class="form-group" style="flex: 1.5; margin-bottom: 0;">
                <label>Target Workstation / Agent</label>
                <select class="agent-select" onchange="updateRowPrinters(this)" required style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
                    ${agentOptionsHtml}
                </select>
            </div>
            <div class="form-group" style="flex: 1.5; margin-bottom: 0;">
                <label>Printer Name</label>
                <select name="printers[${idx}][name]" class="printer-select" required style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">-- Select Printer --</option>
                </select>
            </div>
            <div class="form-group" style="flex: 0.8; margin-bottom: 0;">
                <label>Priority</label>
                <input type="number" name="printers[${idx}][priority]" value="${idx}" min="0" placeholder="0" style="width: 100%; padding: 0.4rem 0.55rem; background: var(--surface); color: var(--text); border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label>Status</label>
                <div style="display: flex; align-items: center; gap: 0.4rem; padding-top: 0.3rem;">
                    <span class="dot dot-green"></span>
                    <span style="font-size: 0.8rem; color: var(--success);">New</span>
                </div>
            </div>
            <div class="form-group" style="flex: 0.8; display: flex; align-items: center; padding-bottom: 0.5rem; margin-bottom: 0;">
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.8rem;">
                    <input type="checkbox" name="printers[${idx}][active]" value="1" checked style="width: 18px; height: 18px;">
                    Active
                </label>
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)" style="margin-bottom: 0.4rem;" title="Remove printer" aria-label="Remove printer from pool">✕</button>
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

    // Find the first row that has an agent selected
    for (let i = 0; i < rows.length; i++) {
        const agentSelect = rows[i].querySelector('.agent-select');
        if (agentSelect && agentSelect.value) {
            lockedBranchId = agentBranches[agentSelect.value];
            break;
        }
    }

    // Apply branch lock: enable only agents in the same branch, disable others
    rows.forEach(row => {
        const agentSelect = row.querySelector('.agent-select');
        if (agentSelect) {
            const options = agentSelect.querySelectorAll('option');
            options.forEach(opt => {
                if (!opt.value) return; // skip placeholder
                const branchId = opt.getAttribute('data-branch');
                if (lockedBranchId === null || branchId === lockedBranchId) {
                    opt.disabled = false;
                    opt.style.opacity = '1';
                } else {
                    opt.disabled = true;
                    opt.style.opacity = '0.5';
                }
            });
        }
    });
}

// Run initially to apply any pre-existing constraints
document.addEventListener('DOMContentLoaded', () => {
    applyBranchRestrictions();
});
</script>
@endsection
