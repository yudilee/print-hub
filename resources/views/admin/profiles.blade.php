@extends('admin.layout')
@section('title', 'Print Queues')

@section('content')
<x-breadcrumb :items="[['label' => 'Print Queues']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Queues & Virtual Profiles</h2>
        <p class="text-xs text-slate-400">Define paper standards, margin offsets, hardware tray routing, and dynamic watermarks</p>
    </div>
    <a href="#active-queues-list" class="btn-secondary btn-sm">
        📋 Configured Queues ({{ $profiles->count() }})
    </a>
</div>

{{-- Create Queue Panel --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <h3 class="text-sm font-bold text-white flex items-center gap-2">
            <span>✨ Create New Queue</span>
        </h3>
        <span class="badge badge-info">Profile & Routing Engine</span>
    </div>

    <form action="{{ route('admin.profiles.store') }}" method="POST">
        @csrf
        @if(isset($clonedFrom))
            <input type="hidden" name="cloned_from" value="{{ $clonedFrom }}">
        @endif

        @if($errors->any())
            <div class="mb-4 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
                <strong class="font-bold block mb-1">Please fix the following:</strong>
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-4">
            {{-- Left Column: Basic Config & Assignment --}}
            <div class="space-y-4">
                <!-- Section 1: Basic Config -->
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-blue-400 uppercase tracking-wider pb-1 border-b border-slate-800">1. Queue Identity</h4>
                    <div>
                        <label for="name" class="block text-xs font-semibold text-slate-400 mb-1">Queue Identifier (slug) <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" required placeholder="e.g. invoice_sewa" value="{{ isset($clonedProfile) ? $clonedProfile->name . '_copy' : old('name') }}"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <span class="text-[10px] text-slate-500 mt-1 block">Lower case, underscores only, no spaces.</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="description" class="block text-xs font-semibold text-slate-400 mb-1">Display Name</label>
                            <input type="text" name="description" id="description" placeholder="e.g. Rental Invoice A4" value="{{ isset($clonedProfile) ? $clonedProfile->description : old('description') }}"
                                class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label for="branch_id" class="block text-xs font-semibold text-slate-400 mb-1">Branch Scoping <span class="text-rose-500">*</span></label>
                            <select name="branch_id" id="branch_id" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                                <option value="">-- Select Branch --</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (isset($clonedProfile) && $clonedProfile->branch_id == $branch->id) || old('branch_id') == $branch->id ? 'selected' : '' }}>
                                        {{ $branch->company->code ?? '' }} / {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Physical Assignment -->
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider pb-1 border-b border-slate-800">2. Physical Routing & Hardware</h4>
                    <div>
                        <label for="routing_type" class="block text-xs font-semibold text-slate-400 mb-1">Routing Strategy</label>
                        <select id="routing_type" onchange="toggleRoutingType(this.value)" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="single" {{ !old('pool_id') && !(isset($clonedProfile) && $clonedProfile->pool_id) ? 'selected' : '' }}>Single Assigned Printer</option>
                            <option value="pool" {{ old('pool_id') || (isset($clonedProfile) && $clonedProfile->pool_id) ? 'selected' : '' }}>Pooled Printers (Failover / Load Balanced)</option>
                        </select>
                    </div>

                    <div id="agent_group">
                        <label for="print_agent_id" class="block text-xs font-semibold text-slate-400 mb-1">Target Workstation / Agent <span class="text-rose-500">*</span></label>
                        <select name="print_agent_id" id="print_agent_id" required onchange="updatePrinterDropdown(this.value); updateAdvancedOptions(this.value);"
                            class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="">-- Select Agent --</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" 
                                    data-printers='{{ json_encode($agent->printers ?? []) }}'
                                    data-capabilities='{{ json_encode($agent->capabilities ?? []) }}'
                                    {{ (isset($clonedProfile) && $clonedProfile->print_agent_id == $agent->id) || old('print_agent_id') == $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }} ({{ $agent->isOnline() ? 'Online' : 'Offline' }})
                                </option>
                            @endforeach
                        </select>
                        <div id="agent-capability-summary" class="text-[11px] text-slate-500 mt-1"></div>
                    </div>

                    <div id="printer_group">
                        <label for="default_printer" class="block text-xs font-semibold text-slate-400 mb-1">Target Printer Device <span class="text-rose-500">*</span></label>
                        <div id="printer_input_container">
                            <input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D" value="{{ isset($clonedProfile) ? $clonedProfile->default_printer : old('default_printer') }}"
                                class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div id="pool_group" class="hidden">
                        <label for="pool_id" class="block text-xs font-semibold text-slate-400 mb-1">Printer Pool <span class="text-rose-500">*</span></label>
                        <select name="pool_id" id="pool_id" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="">-- Select Printer Pool --</option>
                            @foreach($pools as $pool)
                                <option value="{{ $pool->id }}" {{ (isset($clonedProfile) && $clonedProfile->pool_id == $pool->id) || old('pool_id') == $pool->id ? 'selected' : '' }}>
                                    {{ $pool->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Right Column: Media, Margins, Advanced & Watermarks --}}
            <div class="space-y-4">
                <!-- Section 3: Media Standard -->
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-wider pb-1 border-b border-slate-800">3. Media Specs & Margins</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="paper_size" class="block text-xs font-semibold text-slate-400 mb-1">Paper Size</label>
                            <select name="paper_size" id="paper_size" onchange="toggleCustomSize(this.value)" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                                <option value="A4" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'A4') || old('paper_size') == 'A4' ? 'selected' : '' }}>A4 (210×297mm)</option>
                                <option value="A5" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'A5') || old('paper_size') == 'A5' ? 'selected' : '' }}>A5 (148×210mm)</option>
                                <option value="Letter" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Letter') || old('paper_size') == 'Letter' ? 'selected' : '' }}>Letter (8.5" x 11")</option>
                                <option value="Half Letter" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Half Letter') || old('paper_size') == 'Half Letter' ? 'selected' : '' }}>Half Letter (8.5" x 5.5")</option>
                                <option value="Legal" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Legal') || old('paper_size') == 'Legal' ? 'selected' : '' }}>Legal (8.5" x 14")</option>
                                <option value="F4" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'F4') || old('paper_size') == 'F4' ? 'selected' : '' }}>F4 / Folio (215×330mm)</option>
                                <option value="CUSTOM" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'CUSTOM') || old('paper_size') == 'CUSTOM' ? 'selected' : '' }}>-- Custom Size --</option>
                            </select>
                        </div>
                        <div>
                            <label for="orientation" class="block text-xs font-semibold text-slate-400 mb-1">Orientation</label>
                            <select name="orientation" id="orientation" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                                <option value="portrait" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'portrait') || old('orientation') == 'portrait' ? 'selected' : '' }}>Portrait</option>
                                <option value="landscape" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'landscape') || old('orientation') == 'landscape' ? 'selected' : '' }}>Landscape</option>
                            </select>
                        </div>
                    </div>

                    <!-- Custom Dimensions block -->
                    <div id="custom-dims" class="hidden grid grid-cols-3 gap-2 pt-2">
                        <div>
                            <label id="width-label" class="block text-[11px] text-slate-500 mb-1">Width (mm)</label>
                            <input type="number" name="custom_width" step="0.001" placeholder="210" value="{{ isset($clonedProfile) ? $clonedProfile->custom_width : old('custom_width') }}"
                                class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                        </div>
                        <div>
                            <label id="height-label" class="block text-[11px] text-slate-500 mb-1">Height (mm)</label>
                            <input type="number" name="custom_height" step="0.001" placeholder="297" value="{{ isset($clonedProfile) ? $clonedProfile->custom_height : old('custom_height') }}"
                                class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                        </div>
                        <div class="flex items-center pt-4">
                            <label class="inline-flex items-center gap-1.5 text-xs text-slate-400 cursor-pointer">
                                <input type="checkbox" name="use_inches" id="use_inches" value="1" onchange="toggleUnit(this.checked)" {{ old('use_inches') ? 'checked' : '' }} class="rounded border-slate-700 bg-slate-900">
                                <span>Inches</span>
                            </label>
                        </div>
                    </div>

                    <!-- Margins Grid -->
                    <div class="pt-2">
                        <label class="block text-xs font-semibold text-slate-400 mb-1.5">Margins (mm)</label>
                        <div class="grid grid-cols-4 gap-2">
                            <div>
                                <span class="block text-[10px] text-slate-500">Top</span>
                                <input type="number" name="margin_top" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_top : (old('margin_top') ?? '0') }}" class="w-full px-2 py-1 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500">Bottom</span>
                                <input type="number" name="margin_bottom" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_bottom : (old('margin_bottom') ?? '0') }}" class="w-full px-2 py-1 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500">Left</span>
                                <input type="number" name="margin_left" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_left : (old('margin_left') ?? '0') }}" class="w-full px-2 py-1 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            </div>
                            <div>
                                <span class="block text-[10px] text-slate-500">Right</span>
                                <input type="number" name="margin_right" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_right : (old('margin_right') ?? '0') }}" class="w-full px-2 py-1 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 4: Watermarks & Copies -->
                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <h4 class="text-xs font-bold text-indigo-400 uppercase tracking-wider pb-1 border-b border-slate-800">4. Copies & Watermark Security</h4>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="copies" class="block text-xs font-semibold text-slate-400 mb-1">Copies</label>
                            <input type="number" name="copies" id="copies" value="{{ isset($clonedProfile) ? $clonedProfile->copies : (old('copies') ?? '1') }}" min="1" max="99" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label for="watermark_text" class="block text-xs font-semibold text-slate-400 mb-1">Watermark Overlay</label>
                            <input type="text" name="watermark_text" id="watermark_text" placeholder="e.g. COPY, CONFIDENTIAL" value="{{ isset($clonedProfile) ? $clonedProfile->watermark_text : old('watermark_text') }}"
                                class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div id="per-copy-watermark-section" class="hidden pt-2 border-t border-slate-800">
                        <span class="block text-xs font-bold text-blue-400 mb-2">Custom Watermark Per Copy</span>
                        <div id="copy-watermark-configs" class="space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-800 flex justify-end">
            <button type="submit" class="btn-primary">
                <x-icon name="plus" size="14" />
                <span>Save Print Queue</span>
            </button>
        </div>
    </form>
</div>

{{-- Active Queues Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs" id="active-queues-list">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Active Print Queues: <span class="text-white font-mono font-bold">{{ $profiles->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Queue Name</th>
                    <th class="px-5 py-3.5">Branch</th>
                    <th class="px-5 py-3.5">Target Workstation / Pool</th>
                    <th class="px-5 py-3.5">Assigned Device</th>
                    <th class="px-5 py-3.5">Paper</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($profiles as $profile)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-mono font-bold text-blue-400 text-xs">{{ $profile->name }}</span>
                        @if($profile->description)
                            <span class="block text-[11px] text-slate-400">{{ $profile->description }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($profile->branch)
                            <span class="badge badge-info">{{ $profile->branch->company->code ?? '' }}</span>
                            <span class="text-xs text-slate-200 ml-1">{{ $profile->branch->name }}</span>
                        @else
                            <span class="badge badge-warning">Unassigned</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($profile->agent)
                            <span class="badge {{ $profile->agent->isOnline() ? 'badge-success' : 'badge-danger' }}">
                                <span class="dot {{ $profile->agent->isOnline() ? 'dot-green' : 'dot-red' }}"></span>
                                {{ $profile->agent->name }}
                            </span>
                        @else
                            <span class="text-xs text-slate-500 italic">Generic Node</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-slate-300">
                        @if($profile->pool)
                            <span class="badge badge-info">Pool: {{ $profile->pool->name }}</span>
                        @else
                            {{ $profile->default_printer ?: 'OS Default' }}
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info">{{ $profile->paper_size }}</span>
                        <span class="text-[10px] text-slate-500 block capitalize">{{ $profile->orientation }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.profiles.clone', $profile) }}" class="btn-secondary btn-sm" title="Clone">Clone</a>
                            <button type="button" class="btn-secondary btn-sm" onclick="openTestModal('{{ $profile->id }}', '{{ $profile->name }}', '{{ $profile->agent->name ?? 'Any Online Agent' }}', '{{ $profile->pool ? 'Pool: ' . $profile->pool->name : ($profile->default_printer ?: 'Default') }}')">Test</button>
                            <form action="{{ route('admin.profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Delete this queue?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="🖨️" title="No print queues defined" description="Set up a virtual queue above to route incoming print jobs to local printers." />
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
const agentPrinters = {
    @foreach($agents as $agent)
    "{{ $agent->id }}": {!! json_encode($agent->printers ?? []) !!},
    @endforeach
};

const agentCapabilities = {
    @foreach($agents as $agent)
    "{{ $agent->id }}": {!! json_encode($agent->capabilities ?? []) !!},
    @endforeach
};

function updatePrinterDropdown(agentId) {
    const container = document.getElementById('printer_input_container');
    const printers = agentPrinters[agentId];

    if (!agentId) {
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" placeholder="e.g. Brother-HL-L2360D" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200">`;
        return;
    }

    let html = `<select name="default_printer" id="default_printer" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200">`;
    if (!printers || printers.length === 0) {
        html += `<option value="">-- No printers reported by Agent --</option>`;
    } else {
        html += `<option value="">-- Agent Default Printer --</option>`;
        printers.forEach(p => {
            html += `<option value="${p}">${p}</option>`;
        });
    }
    html += `</select>`;
    container.innerHTML = html;
}

function updateAdvancedOptions(agentId) {
    const caps = agentCapabilities[agentId];
    const summaryEl = document.getElementById('agent-capability-summary');
    if (!summaryEl) return;

    if (!caps || !caps.printers || Object.keys(caps.printers).length === 0) {
        summaryEl.innerHTML = '<span class="text-slate-500">No telemetry reported. Using system defaults.</span>';
        return;
    }

    const count = Object.keys(caps.printers).length;
    summaryEl.innerHTML = `<span class="text-emerald-400 font-semibold">✓ Discovered ${count} physical printer(s) from agent</span>`;
}

function toggleCustomSize(val) {
    const dims = document.getElementById('custom-dims');
    if (dims) {
        if (val === 'CUSTOM') dims.classList.remove('hidden');
        else dims.classList.add('hidden');
    }
}

function toggleUnit(isInch) {
    const wLabel = document.getElementById('width-label');
    const hLabel = document.getElementById('height-label');
    if (wLabel) wLabel.innerText = isInch ? 'Width (Inch)' : 'Width (mm)';
    if (hLabel) hLabel.innerText = isInch ? 'Height (Inch)' : 'Height (mm)';
}

function toggleRoutingType(type) {
    const printerGroup = document.getElementById('printer_group');
    const poolGroup = document.getElementById('pool_group');
    const printerInput = document.getElementById('default_printer');
    const poolSelect = document.getElementById('pool_id');

    if (type === 'single') {
        if (printerGroup) printerGroup.classList.remove('hidden');
        if (poolGroup) poolGroup.classList.add('hidden');
        if (printerInput) printerInput.required = true;
        if (poolSelect) { poolSelect.required = false; poolSelect.value = ''; }
    } else {
        if (printerGroup) printerGroup.classList.add('hidden');
        if (poolGroup) poolGroup.classList.remove('hidden');
        if (printerInput) { printerInput.required = false; printerInput.value = ''; }
        if (poolSelect) poolSelect.required = true;
    }
}

function openTestModal(id, name, agent, printer) {
    const modal = document.getElementById('test-modal');
    const form = document.getElementById('test-print-form');
    const title = document.getElementById('modal-title');
    const info = document.getElementById('modal-target-info');

    if (title) title.innerText = `Test Queue: ${name}`;
    if (info) info.innerText = `Workstation: ${agent} | Device: ${printer}`;
    if (form) form.action = `/profiles/${id}/test-print`;
    if (modal) modal.classList.remove('hidden');
}

function closeTestModal() {
    const modal = document.getElementById('test-modal');
    if (modal) modal.classList.add('hidden');
}

function initPerCopyWatermark() {
    const copiesInput = document.getElementById('copies');
    if (!copiesInput) return;

    copiesInput.addEventListener('input', updateCopyWatermarkConfigs);
    updateCopyWatermarkConfigs();
}

function updateCopyWatermarkConfigs() {
    const copies = parseInt(document.getElementById('copies')?.value || 1);
    const section = document.getElementById('per-copy-watermark-section');
    const container = document.getElementById('copy-watermark-configs');

    if (!section || !container) return;

    if (copies <= 1) {
        section.classList.add('hidden');
        return;
    }

    section.classList.remove('hidden');
    let html = '';
    for (let i = 0; i < copies; i++) {
        html += `<div class="p-2.5 rounded-lg bg-slate-900 border border-slate-800 flex items-center gap-2">
            <span class="text-[11px] font-bold text-blue-400 w-16">Copy ${i + 1}:</span>
            <input type="text" name="watermark_copies[${i}][text]" placeholder="e.g. Accounting Copy" class="flex-1 px-2 py-1 bg-slate-950 border border-slate-800 rounded text-xs text-slate-200">
        </div>`;
    }
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', () => {
    initPerCopyWatermark();
});
</script>
@endsection
