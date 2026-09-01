@extends('admin.layout')
@section('title', 'Printer Configs')

@section('content')
<x-breadcrumb :items="[['label' => 'Printer Configs']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Per-Printer Hardware Configs</h2>
        <p class="text-xs text-slate-400">Device-specific overrides for copies, trays, color modes, and DPI options</p>
    </div>
    <button class="btn-primary btn-sm" onclick="openCreateModal()">
        <x-icon name="plus" size="13" />
        <span>Add Config Override</span>
    </button>
</div>

{{-- Filters Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xs">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <select name="print_agent_id" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" {{ request('print_agent_id') == $agent->id ? 'selected' : '' }}>
                    {{ $agent->name }}
                </option>
            @endforeach
        </select>

        <select name="is_active" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Statuses</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
        </select>

        <div class="relative flex-1 min-w-[200px]">
            <x-icon name="search" size="14" class="text-slate-500 absolute left-3 top-2.5" />
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search printer or agent..."
                class="w-full pl-9 pr-4 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
        </div>

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.printer-configs') }}" class="btn-secondary btn-sm">Reset</a>
    </form>
</div>

{{-- Configs Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Overrides: <span class="text-white font-mono font-bold">{{ $configs->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Agent / Station</th>
                    <th class="px-5 py-3.5">Printer Identifier</th>
                    <th class="px-5 py-3.5">Configured Overrides</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Last Modified</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($configs as $config)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <span class="font-bold text-white">{{ $config->agent->name ?? '—' }}</span>
                        @if($config->agent && $config->agent->branch)
                            <span class="block text-[10px] text-slate-500">{{ $config->agent->branch->name }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $config->printer_name }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($config->config && count($config->config) > 0)
                            <div class="flex flex-wrap gap-1">
                                @foreach($config->config as $key => $value)
                                    <span class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-[11px]">
                                        <span class="text-slate-500">{{ $key }}:</span>
                                        <strong class="text-slate-200">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-slate-500 italic">No specific overrides</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        @if($config->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $config->updated_at->diffForHumans() }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button type="button" class="btn-secondary btn-sm" onclick="openEditModal({{ $config->id }})">Edit</button>
                            <form action="{{ route('admin.printer-configs.destroy', $config) }}" method="POST" onsubmit="return confirm('Delete this printer configuration?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="🖨️" title="No printer overrides configured" description="Add specific per-printer overrides to fine-tune paper sources and color rendering." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($configs->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $configs->appends(request()->query())->links() }}
    </div>
    @endif
</div>

{{-- Create / Edit Modal --}}
<div id="config-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 id="modal-title" class="text-base font-bold text-white">Add Printer Configuration</h3>
            <button onclick="closeConfigModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="config-form" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">

            <div>
                <label for="config_print_agent_id" class="block text-xs font-semibold text-slate-400 mb-1">Print Agent <span class="text-rose-500">*</span></label>
                <select name="print_agent_id" id="config_print_agent_id" required onchange="onAgentChange()"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="">-- Select Agent --</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
                <div id="agent-printers-info" class="text-[11px] text-slate-500 mt-1 hidden">
                    Detected: <span id="agent-printer-list" class="text-blue-400 font-mono"></span>
                </div>
            </div>

            <div>
                <label for="config_printer_name" class="block text-xs font-semibold text-slate-400 mb-1">Printer Target <span class="text-rose-500">*</span></label>
                <div class="flex gap-2">
                    <select name="printer_name" id="config_printer_name" required class="flex-1 px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">-- Select a Printer --</option>
                    </select>
                    <button type="button" class="btn-secondary btn-sm" onclick="toggleCustomPrinter()">Custom</button>
                </div>
                <div id="custom-printer-container" class="mt-2 hidden">
                    <input type="text" name="printer_name_custom" id="config_printer_name_custom" placeholder="e.g. HP-LaserJet-M404"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                <span class="text-xs font-bold text-blue-400 uppercase tracking-wider block">Override Parameters</span>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Copies</label>
                        <input type="number" name="copies" id="config_copies" min="1" max="999" placeholder="Queue Default"
                            class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Duplex</label>
                        <select name="duplex" id="config_duplex" class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            <option value="">Queue Default</option>
                            <option value="none">Simplex (None)</option>
                            <option value="long-edge">Long Edge</option>
                            <option value="short-edge">Short Edge</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Color Mode</label>
                        <select name="color_mode" id="config_color_mode" class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                            <option value="">Queue Default</option>
                            <option value="color">Color</option>
                            <option value="grayscale">Grayscale</option>
                            <option value="monochrome">Monochrome</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Paper Tray</label>
                        <input type="text" name="tray" id="config_tray" placeholder="Tray 1, Bypass"
                            class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-200">
                    </div>
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-300 cursor-pointer">
                        <input type="checkbox" name="collate" id="config_collate" value="1" class="rounded border-slate-700 bg-slate-900 text-blue-600">
                        <span>Collate</span>
                    </label>
                    <label class="inline-flex items-center gap-1.5 text-xs text-slate-300 cursor-pointer">
                        <input type="checkbox" name="fit_to_page" id="config_fit_to_page" value="1" class="rounded border-slate-700 bg-slate-900 text-blue-600">
                        <span>Fit to Page</span>
                    </label>
                </div>
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="config_is_active" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600" checked>
                    <span>Config Active & Enforced</span>
                </label>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="closeConfigModal()">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
const agentsData = {!! json_encode($agents->map(fn($a) => [
    'id' => $a->id,
    'name' => $a->name,
    'printers' => $a->printers ?? [],
])) !!};
const configs = @json($configs->items());
let isCustomPrinter = false;

function onAgentChange() {
    const agentId = parseInt(document.getElementById('config_print_agent_id').value);
    const printerSelect = document.getElementById('config_printer_name');
    const infoDiv = document.getElementById('agent-printers-info');
    const listSpan = document.getElementById('agent-printer-list');

    printerSelect.innerHTML = '<option value="">-- Select a Printer --</option>';

    if (!agentId) {
        infoDiv.classList.add('hidden');
        return;
    }

    const agent = agentsData.find(a => a.id === agentId);
    const printers = agent ? agent.printers : [];

    if (printers.length > 0) {
        printers.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            printerSelect.appendChild(opt);
        });
        listSpan.textContent = printers.join(', ');
        infoDiv.classList.remove('hidden');
    } else {
        listSpan.textContent = '(None reported)';
        infoDiv.classList.remove('hidden');
    }
}

function toggleCustomPrinter() {
    isCustomPrinter = !isCustomPrinter;
    const select = document.getElementById('config_printer_name');
    const customContainer = document.getElementById('custom-printer-container');

    if (isCustomPrinter) {
        select.classList.add('hidden');
        select.removeAttribute('required');
        customContainer.classList.remove('hidden');
        document.getElementById('config_printer_name_custom').setAttribute('required', '');
    } else {
        select.classList.remove('hidden');
        select.setAttribute('required', '');
        customContainer.classList.add('hidden');
        document.getElementById('config_printer_name_custom').removeAttribute('required');
    }
}

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Printer Configuration';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('config-form').action = '{{ route('admin.printer-configs.store') }}';
    document.getElementById('config-form').reset();
    document.getElementById('config_is_active').checked = true;
    isCustomPrinter = false;
    document.getElementById('custom-printer-container').classList.add('hidden');
    document.getElementById('config_printer_name').classList.remove('hidden');
    document.getElementById('config-modal').classList.remove('hidden');
}

function openEditModal(id) {
    const config = configs.find(c => c.id === id);
    if (!config) return;

    document.getElementById('modal-title').textContent = 'Edit Printer Configuration';
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('config-form').action = '/printer-configs/' + id;
    document.getElementById('config_print_agent_id').value = config.print_agent_id;
    onAgentChange();

    setTimeout(() => {
        document.getElementById('config_printer_name').value = config.printer_name;
    }, 50);

    const cfg = config.config || {};
    document.getElementById('config_copies').value = cfg.copies || '';
    document.getElementById('config_duplex').value = cfg.duplex || '';
    document.getElementById('config_color_mode').value = cfg.color_mode || '';
    document.getElementById('config_tray').value = cfg.tray || '';
    document.getElementById('config_collate').checked = cfg.collate === true;
    document.getElementById('config_fit_to_page').checked = cfg.fit_to_page === true;
    document.getElementById('config_is_active').checked = config.is_active;

    document.getElementById('config-modal').classList.remove('hidden');
}

function closeConfigModal() {
    document.getElementById('config-modal').classList.add('hidden');
}
</script>
@endsection
