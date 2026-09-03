@extends('admin.layout')
@section('title', isset($clonedProfile) ? 'Clone Print Queue' : 'Create Print Queue')

@section('content')
<x-breadcrumb :items="[
    ['label' => 'Print Queues', 'url' => route('admin.profiles')],
    ['label' => isset($clonedProfile) ? 'Clone Queue' : 'Create Queue']
]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <span>{{ isset($clonedProfile) ? '📑 Clone Print Queue' : '✨ Create New Print Queue' }}</span>
        </h2>
        <p class="text-xs text-slate-400">Configure paper standard, margins, hardware driver, duplex, and target branch printer</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.profiles') }}" class="btn-secondary btn-sm">
            <span>← Back to Queues</span>
        </a>
    </div>
</div>

<form action="{{ route('admin.profiles.store') }}" method="POST">
    @csrf
    @if(isset($clonedFrom))
        <input type="hidden" name="cloned_from" value="{{ $clonedFrom }}">
    @endif

    @if($errors->any())
        <div class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
            <strong class="font-bold block mb-1.5 flex items-center gap-1.5">
                <span>⚠️</span>
                <span>Please correct the following errors:</span>
            </strong>
            <ul class="list-disc pl-5 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
        {{-- Left Column: Identity & Hardware Routing --}}
        <div class="space-y-5">
            <!-- 1. Queue Identity -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h4 class="text-xs font-bold text-blue-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>1. Queue Identity</span>
                    </h4>
                    <span class="text-[10px] text-slate-500 font-mono">Unique Key</span>
                </div>

                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-300 mb-1">Queue Identifier (slug) <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" id="name" required placeholder="e.g. job_card_laser" 
                           value="{{ isset($clonedProfile) ? $clonedProfile->name . '_copy' : old('name') }}"
                           class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
                    <span class="text-[10px] text-slate-500 mt-1 block">Lower case, underscores only, no spaces. Must match queue name in Odoo Routing Rules.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="description" class="block text-xs font-semibold text-slate-300 mb-1">Display Name</label>
                        <input type="text" name="description" id="description" placeholder="e.g. Job Card Workshop Laser" 
                               value="{{ isset($clonedProfile) ? $clonedProfile->description : old('description') }}"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label for="branch_id" class="block text-xs font-semibold text-slate-300 mb-1">Branch Scoping <span class="text-rose-500">*</span></label>
                        <select name="branch_id" id="branch_id" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
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

            <!-- 2. Physical Routing & Hardware -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>2. Hardware Routing &amp; Target</span>
                    </h4>
                    <span class="text-[10px] text-slate-500 font-mono">Dispatcher Target</span>
                </div>

                <div>
                    <label for="routing_type" class="block text-xs font-semibold text-slate-300 mb-1">Routing Strategy</label>
                    <select id="routing_type" onchange="toggleRoutingType(this.value)" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="single" {{ !old('pool_id') && !(isset($clonedProfile) && $clonedProfile->pool_id) ? 'selected' : '' }}>Single Assigned Workstation Printer</option>
                        <option value="pool" {{ old('pool_id') || (isset($clonedProfile) && $clonedProfile->pool_id) ? 'selected' : '' }}>Pooled Printers (Failover / Load Balanced)</option>
                    </select>
                </div>

                <div id="agent_group" class="space-y-4">
                    <div>
                        <label for="print_agent_id" class="block text-xs font-semibold text-slate-300 mb-1">Target Workstation / Agent <span class="text-rose-500">*</span></label>
                        <select name="print_agent_id" id="print_agent_id" onchange="updatePrinterDropdown(this.value); updateAdvancedOptions(this.value);"
                            class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="">-- Select Agent Workstation --</option>
                            @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" 
                                    data-printers='{{ json_encode($agent->printers ?? []) }}'
                                    data-capabilities='{{ json_encode($agent->capabilities ?? []) }}'
                                    {{ (isset($clonedProfile) && $clonedProfile->print_agent_id == $agent->id) || old('print_agent_id') == $agent->id ? 'selected' : '' }}>
                                    {{ $agent->name }} ({{ $agent->isOnline() ? 'Online' : 'Offline' }})
                                </option>
                            @endforeach
                        </select>
                        <div id="agent-capability-summary" class="text-[11px] text-slate-500 mt-1.5"></div>
                    </div>

                    <div>
                        <label for="default_printer" class="block text-xs font-semibold text-slate-300 mb-1">Target Physical Printer <span class="text-rose-500">*</span></label>
                        <div id="printer_input_container">
                            <input type="text" name="default_printer" id="default_printer" placeholder="Select Agent to load discovered printers..." 
                                   value="{{ isset($clonedProfile) ? $clonedProfile->default_printer : old('default_printer') }}"
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>

                <div id="pool_group" class="hidden">
                    <label for="pool_id" class="block text-xs font-semibold text-slate-300 mb-1">Printer Pool <span class="text-rose-500">*</span></label>
                    <select name="pool_id" id="pool_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">-- Select Printer Pool --</option>
                        @foreach($pools as $pool)
                            <option value="{{ $pool->id }}" {{ (isset($clonedProfile) && $clonedProfile->pool_id == $pool->id) || old('pool_id') == $pool->id ? 'selected' : '' }}>
                                {{ $pool->name }} ({{ count($pool->printers ?? []) }} printers, {{ $pool->strategy }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Right Column: Media, Settings & Duplex --}}
        <div class="space-y-5">
            <!-- 3. Media Specs & Margins -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h4 class="text-xs font-bold text-emerald-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>3. Media Specs &amp; Margins</span>
                    </h4>
                    <span class="text-[10px] text-slate-500 font-mono">Page Geometry</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="paper_size" class="block text-xs font-semibold text-slate-300 mb-1">Paper Standard <span class="text-rose-500">*</span></label>
                        <select name="paper_size" id="paper_size" required onchange="toggleCustomPaper(this.value)"
                                class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            @foreach(['A4 (210×297mm)', 'A5 (148×210mm)', 'Letter (8.5×11in)', 'Legal (8.5×14in)', 'Continuous Form 9.5x11in', 'Thermal 80mm Receipt', 'Thermal 58mm Receipt', 'CUSTOM'] as $size)
                                <option value="{{ $size }}" {{ (isset($clonedProfile) && $clonedProfile->paper_size == $size) || old('paper_size', 'A4 (210×297mm)') == $size ? 'selected' : '' }}>
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="orientation" class="block text-xs font-semibold text-slate-300 mb-1">Orientation <span class="text-rose-500">*</span></label>
                        <select name="orientation" id="orientation" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="portrait" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'portrait') || old('orientation', 'portrait') == 'portrait' ? 'selected' : '' }}>Portrait (Tegak)</option>
                            <option value="landscape" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'landscape') || old('orientation') == 'landscape' ? 'selected' : '' }}>Landscape (Memanjang)</option>
                        </select>
                    </div>
                </div>

                <div id="custom_paper_container" class="hidden p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Width (mm)</label>
                            <input type="number" step="0.1" name="custom_width" value="{{ isset($clonedProfile) ? $clonedProfile->custom_width : old('custom_width') }}" class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200">
                        </div>
                        <div>
                            <label class="block text-[11px] text-slate-400 mb-1">Height (mm)</label>
                            <input type="number" step="0.1" name="custom_height" value="{{ isset($clonedProfile) ? $clonedProfile->custom_height : old('custom_height') }}" class="w-full px-3 py-1.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-slate-200">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Hardware Margins (mm)</label>
                    <div class="grid grid-cols-4 gap-2">
                        <div>
                            <span class="text-[10px] text-slate-500 block mb-0.5">Top</span>
                            <input type="number" step="0.5" name="margin_top" value="{{ isset($clonedProfile) ? $clonedProfile->margin_top : old('margin_top', 0) }}" class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 text-center">
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 block mb-0.5">Bottom</span>
                            <input type="number" step="0.5" name="margin_bottom" value="{{ isset($clonedProfile) ? $clonedProfile->margin_bottom : old('margin_bottom', 0) }}" class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 text-center">
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 block mb-0.5">Left</span>
                            <input type="number" step="0.5" name="margin_left" value="{{ isset($clonedProfile) ? $clonedProfile->margin_left : old('margin_left', 0) }}" class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 text-center">
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 block mb-0.5">Right</span>
                            <input type="number" step="0.5" name="margin_right" value="{{ isset($clonedProfile) ? $clonedProfile->margin_right : old('margin_right', 0) }}" class="w-full px-2 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 text-center">
                        </div>
                    </div>
                </div>

                <div>
                    <label for="tray_source" class="block text-xs font-semibold text-slate-300 mb-1">Hardware Tray / Paper Feeder</label>
                    <input type="text" name="tray_source" id="tray_source" placeholder="e.g. Tray 1, Manual Feed, Cassette (optional)"
                           value="{{ isset($clonedProfile) ? $clonedProfile->tray_source : old('tray_source') }}"
                           class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <!-- 4. Duplex & Print Settings -->
            <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 shadow-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h4 class="text-xs font-bold text-purple-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>4. Duplex &amp; Output Control</span>
                    </h4>
                    <span class="text-[10px] text-slate-500 font-mono">Driver Flags</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="duplex" class="block text-xs font-semibold text-slate-300 mb-1">Duplex Mode (Bolak-Balik)</label>
                        <select name="duplex" id="duplex" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="one-sided" {{ (isset($clonedProfile) && $clonedProfile->duplex == 'one-sided') || old('duplex', 'one-sided') == 'one-sided' ? 'selected' : '' }}>One-Sided (Simplex / Satu Sisi)</option>
                            <option value="two-sided-long" {{ (isset($clonedProfile) && $clonedProfile->duplex == 'two-sided-long') || old('duplex') == 'two-sided-long' ? 'selected' : '' }}>Two-Sided (Duplex Long Edge / Bolak-Balik Panjang)</option>
                            <option value="two-sided-short" {{ (isset($clonedProfile) && $clonedProfile->duplex == 'two-sided-short') || old('duplex') == 'two-sided-short' ? 'selected' : '' }}>Two-Sided (Duplex Short Edge / Bolak-Balik Pendek)</option>
                        </select>
                    </div>

                    <div>
                        <label for="copies" class="block text-xs font-semibold text-slate-300 mb-1">Default Copies</label>
                        <input type="number" min="1" max="99" name="copies" id="copies" required
                               value="{{ isset($clonedProfile) ? $clonedProfile->copies : old('copies', 1) }}"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="color_mode" class="block text-xs font-semibold text-slate-300 mb-1">Color Mode</label>
                        <select name="color_mode" id="color_mode" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                            <option value="monochrome" {{ (isset($clonedProfile) && $clonedProfile->color_mode == 'monochrome') || old('color_mode', 'monochrome') == 'monochrome' ? 'selected' : '' }}>Monochrome (Hitam Putih)</option>
                            <option value="color" {{ (isset($clonedProfile) && $clonedProfile->color_mode == 'color') || old('color_mode') == 'color' ? 'selected' : '' }}>Color (Warna)</option>
                        </select>
                    </div>

                    <div>
                        <label for="watermark_text" class="block text-xs font-semibold text-slate-300 mb-1">Dynamic Watermark Overlay</label>
                        <input type="text" name="watermark_text" id="watermark_text" placeholder="e.g. COPY / CONFIDENTIAL (optional)"
                               value="{{ isset($clonedProfile) ? $clonedProfile->watermark_text : old('watermark_text') }}"
                               class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Bottom Action Buttons --}}
    <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-between shadow-lg">
        <a href="{{ route('admin.profiles') }}" class="btn-secondary btn-sm">
            <span>Cancel</span>
        </a>
        <button type="submit" class="btn-primary btn-sm flex items-center gap-1.5 px-6 shadow-lg shadow-blue-500/25">
            <x-icon name="check" size="14" />
            <span class="font-bold">{{ isset($clonedProfile) ? 'Clone & Save Queue' : 'Save Print Queue' }}</span>
        </button>
    </div>
</form>

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

function toggleRoutingType(type) {
    const isPool = (type === 'pool');
    document.getElementById('agent_group').classList.toggle('hidden', isPool);
    document.getElementById('pool_group').classList.toggle('hidden', !isPool);

    document.getElementById('print_agent_id').required = !isPool;
    document.getElementById('pool_id').required = isPool;
}

function updatePrinterDropdown(agentId) {
    const container = document.getElementById('printer_input_container');
    const printers = agentPrinters[agentId];
    const currentVal = "{{ isset($clonedProfile) ? $clonedProfile->default_printer : old('default_printer') }}";

    if (!agentId) {
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" placeholder="Select agent first..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200">`;
        return;
    }

    let html = `<select name="default_printer" id="default_printer" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">`;
    if (!printers || printers.length === 0) {
        html += `<option value="">-- No printers reported by Agent --</option>`;
    } else {
        html += `<option value="">-- Select Discovered Printer --</option>`;
        printers.forEach(p => {
            const isSel = (p === currentVal) ? 'selected' : '';
            html += `<option value="${p}" ${isSel}>${p}</option>`;
        });
    }
    html += `</select>`;
    container.innerHTML = html;
}

function toggleCustomPaper(val) {
    const isCustom = (val === 'CUSTOM');
    document.getElementById('custom_paper_container').classList.toggle('hidden', !isCustom);
}

function updateAdvancedOptions(agentId) {
    const caps = agentCapabilities[agentId];
    const summaryEl = document.getElementById('agent-capability-summary');
    if (!summaryEl) return;

    if (!caps || !caps.printers || Object.keys(caps.printers).length === 0) {
        summaryEl.innerHTML = '<span class="text-slate-500">No agent hardware telemetry reported. Using standard defaults.</span>';
        return;
    }
    summaryEl.innerHTML = '<span class="text-emerald-400">✓ Agent telemetry loaded with hardware driver support.</span>';
}

document.addEventListener('DOMContentLoaded', () => {
    const initAgentId = document.getElementById('print_agent_id').value;
    if (initAgentId) {
        updatePrinterDropdown(initAgentId);
        updateAdvancedOptions(initAgentId);
    }
    toggleCustomPaper(document.getElementById('paper_size').value);
    toggleRoutingType(document.getElementById('routing_type').value);
});
</script>
@endsection
