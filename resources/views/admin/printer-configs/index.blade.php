@extends('admin.layout')
@section('title', 'Printer Configs')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Printer Configs']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Per-Printer Configurations</h1>
        <p>Manage per-printer overrides for each print agent</p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Add Config</button>
</div>

{{-- In-App Help / Documentation --}}
<div class="card" style="padding: 1rem; margin-bottom: 1rem; background: var(--bg-secondary, #f8f9fa); border-left: 4px solid var(--primary, #0d6efd);">
    <details>
        <summary style="cursor: pointer; font-weight: 600; font-size: 0.95rem;">
            📖 How Printer Configs Work
        </summary>
        <div style="margin-top: 0.75rem; font-size: 0.9rem; line-height: 1.6;">
            <p><strong>Printer Configs</strong> let you override print options for <em>specific printers</em> on <em>specific agents</em> — without changing the Print Queue settings.</p>

            <p><strong>Override Priority (highest → lowest):</strong></p>
            <ol>
                <li><strong>Job request options</strong> — passed by the client app at print time (highest)</li>
                <li><strong>Printer Config</strong> — the overrides you define here (medium)</li>
                <li><strong>Print Queue defaults</strong> — the base profile settings (lowest)</li>
            </ol>

            <p><strong>Example Scenario:</strong></p>
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                <thead>
                    <tr style="background: var(--bg-tertiary, #e9ecef);">
                        <th style="padding: 6px 8px; text-align: left; border: 1px solid #dee2e6;">Item</th>
                        <th style="padding: 6px 8px; text-align: left; border: 1px solid #dee2e6;">Copies</th>
                        <th style="padding: 6px 8px; text-align: left; border: 1px solid #dee2e6;">Duplex</th>
                        <th style="padding: 6px 8px; text-align: left; border: 1px solid #dee2e6;">Color Mode</th>
                        <th style="padding: 6px 8px; text-align: left; border: 1px solid #dee2e6;">Tray</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">Queue "General-Receipts"</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">1</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">none</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">grayscale</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">(default)</td>
                    </tr>
                    <tr style="background: rgba(13, 110, 253, 0.05);">
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">➕ Printer Config "Epson-WF-7720"</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">2</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">short-edge</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;"><strong>color</strong></td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">Tray 1</td>
                    </tr>
                    <tr style="background: rgba(25, 135, 84, 0.08);">
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6; font-weight: 600;">✅ Final (job request: copies=3)</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6; font-weight: 600;">3 ✅</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">short-edge</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6; font-weight: 600;">color</td>
                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">Tray 1</td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                Job request options (copies=3) override Printer Config (copies=2), which overrides Queue defaults (copies=1).
                Printer Config sets color_mode and tray, which the Queue didn't specify.
            </p>
        </div>
    </details>
</div>

{{-- Filters --}}
<div class="card" style="padding: 1rem;">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
            <label>Agent</label>
            <select name="print_agent_id">
                <option value="">All Agents</option>
                @foreach($agents as $agent)
                    <option value="{{ $agent->id }}" {{ request('print_agent_id') == $agent->id ? 'selected' : '' }}>
                        {{ $agent->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 140px;">
            <label>Status</label>
            <select name="is_active">
                <option value="">All</option>
                <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>Active</option>
                <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 180px;">
            <label>Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Printer or agent name...">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.printer-configs') }}" class="btn btn-secondary btn-sm" style="text-decoration: none;">Clear</a>
    </form>
</div>

{{-- Configs Table --}}
<div class="card">
    <div class="card-header">
        <h2>Configurations ({{ $configs->total() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Agent</th>
                <th>Printer</th>
                <th>Configured Options</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($configs as $config)
            <tr>
                <td>
                    <strong>{{ $config->agent->name ?? '—' }}</strong>
                    @if($config->agent && $config->agent->branch)
                        <br><span style="font-size: 0.75rem; color: var(--text-muted);">{{ $config->agent->branch->name }}</span>
                    @endif
                </td>
                <td><code class="mono">{{ $config->printer_name }}</code></td>
                <td style="font-size: 0.8rem; max-width: 300px;">
                    @if($config->config && count($config->config) > 0)
                        @foreach($config->config as $key => $value)
                            <span style="color: var(--text-muted);">{{ $key }}:</span>
                            <strong>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</strong>
                            {{ !$loop->last ? '·' : '' }}
                        @endforeach
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">No options set</span>
                    @endif
                </td>
                <td>
                    @if($config->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted); white-space: nowrap;">
                    {{ $config->updated_at->diffForHumans() }}
                </td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ $config->id }})">Edit</button>
                        <form action="{{ route('admin.printer-configs.destroy', $config) }}" method="POST" onsubmit="return confirm('Delete this printer configuration?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6">
                <x-empty-state icon="🖨️" title="No printer configs yet" description="Add a per-printer configuration to override default options for a specific printer on an agent." />
            </td></tr>
            @endforelse
        </tbody>
    </table>

    @if($configs->hasPages())
        <div class="pagination" style="margin-top: 1rem;">
            {{ $configs->appends(request()->query())->links() }}
        </div>
    @endif
</div>

{{-- Create/Edit Modal --}}
<div id="config-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 720px; padding: 2rem; max-height: 92vh; overflow-y: auto;">
        <div class="card-header">
            <h2 id="modal-title">Add Printer Configuration</h2>
        </div>
        <form id="config-form" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">

            {{-- Agent selection --}}
            <div class="form-group">
                <label for="config_print_agent_id">Print Agent <span style="color: var(--danger);">*</span></label>
                <select name="print_agent_id" id="config_print_agent_id" required onchange="onAgentChange()">
                    <option value="">-- Select Agent --</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
                <div id="agent-printers-info" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem; display: none;">
                    Registered printers on this agent: <span id="agent-printer-list"></span>
                </div>
            </div>

            {{-- Printer selection --}}
            <div class="form-group">
                <label for="config_printer_name">Printer <span style="color: var(--danger);">*</span></label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <select name="printer_name" id="config_printer_name" style="flex: 1;" required>
                        <option value="">-- Select a Printer --</option>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleCustomPrinter()" title="Type a custom printer name" style="white-space: nowrap;">✏️ Custom</button>
                </div>
                <div id="custom-printer-container" style="display: none; margin-top: 0.5rem;">
                    <input type="text" name="printer_name_custom" id="config_printer_name_custom" placeholder="e.g. HP-LaserJet-M404" style="width: 100%;">
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.2rem;">
                        Enter the exact printer name as reported by the agent.
                    </div>
                </div>
            </div>

            {{-- Structured Config Fields --}}
            <fieldset style="border: 1px solid var(--border-color, #dee2e6); border-radius: 6px; padding: 1rem; margin-top: 1rem;">
                <legend style="font-weight: 600; font-size: 0.9rem; padding: 0 6px;">Override Options</legend>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_copies">Copies</label>
                        <input type="number" name="copies" id="config_copies" min="1" max="999" placeholder="(use queue default)">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_duplex">Duplex</label>
                        <select name="duplex" id="config_duplex">
                            <option value="">(use queue default)</option>
                            <option value="none">None (Simplex)</option>
                            <option value="long-edge">Long Edge (Flip on long)</option>
                            <option value="short-edge">Short Edge (Flip on short)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_paper_size">Paper Size</label>
                        <select name="paper_size" id="config_paper_size">
                            <option value="">(use queue default)</option>
                            <option value="A3">A3</option>
                            <option value="A4">A4</option>
                            <option value="A5">A5</option>
                            <option value="Letter">Letter</option>
                            <option value="Legal">Legal</option>
                            <option value="Tabloid">Tabloid</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_tray">Tray / Paper Source</label>
                        <input type="text" name="tray" id="config_tray" placeholder="e.g. Tray 2, Manual Feed">
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_color_mode">Color Mode</label>
                        <select name="color_mode" id="config_color_mode">
                            <option value="">(use queue default)</option>
                            <option value="color">Color</option>
                            <option value="grayscale">Grayscale</option>
                            <option value="monochrome">Monochrome</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_print_quality">Print Quality</label>
                        <select name="print_quality" id="config_print_quality">
                            <option value="">(use queue default)</option>
                            <option value="draft">Draft</option>
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_orientation">Orientation</label>
                        <select name="orientation" id="config_orientation">
                            <option value="">(use queue default)</option>
                            <option value="portrait">Portrait</option>
                            <option value="landscape">Landscape</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="config_media_type">Media Type</label>
                        <select name="media_type" id="config_media_type">
                            <option value="">(use queue default)</option>
                            <option value="plain">Plain</option>
                            <option value="glossy">Glossy</option>
                            <option value="labels">Labels</option>
                            <option value="envelope">Envelope</option>
                            <option value="cardstock">Cardstock</option>
                            <option value="photo">Photo</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 0; display: flex; align-items: center; gap: 12px; padding-top: 22px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="collate" id="config_collate" value="1" style="width: 18px; height: 18px;">
                            Collate
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="checkbox" name="fit_to_page" id="config_fit_to_page" value="1" style="width: 18px; height: 18px;">
                            Fit to Page
                        </label>
                    </div>
                </div>
            </fieldset>

            {{-- Advanced / Custom JSON --}}
            <details style="margin-top: 0.75rem;">
                <summary style="cursor: pointer; font-size: 0.85rem; color: var(--text-muted);">
                    ⚙️ Advanced: Custom Options (JSON)
                </summary>
                <div class="form-group" style="margin-top: 0.5rem;">
                    <textarea name="advanced_config" id="config_advanced" rows="4" placeholder='{
    "scaling_percentage": 90,
    "carbon_saved": 0.5
}' style="font-family: 'Fira Code', monospace; font-size: 0.8rem;"></textarea>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                        Extra key/value pairs not covered by the fields above. Merged on top of the structured fields.
                    </div>
                </div>
            </details>

            {{-- Active toggle --}}
            <div class="form-group" style="margin-top: 0.75rem;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="config_is_active" value="1" style="width: 18px; height: 18px;" checked>
                    Active
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeConfigModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Data ─────────────────────────────────────────────────────────
const agentsData = @json($agents->map(fn($a) => [
    'id' => $a->id,
    'name' => $a->name,
    'printers' => $a->printers ?? [],
]));
const configs = @json($configs->items());
const editConfigId = @json(session('edit_config_id'));
let isCustomPrinter = false;

// ── Auto-open edit modal if redirected from /edit route ──────────
if (editConfigId) {
    document.addEventListener('DOMContentLoaded', function() {
        openEditModal(editConfigId);
    });
}

// ── Agent change → populate printer dropdown ─────────────────────
function onAgentChange() {
    const agentId = parseInt(document.getElementById('config_print_agent_id').value);
    const printerSelect = document.getElementById('config_printer_name');
    const infoDiv = document.getElementById('agent-printers-info');
    const listSpan = document.getElementById('agent-printer-list');

    // Clear printer dropdown
    printerSelect.innerHTML = '<option value="">-- Select a Printer --</option>';

    if (!agentId) {
        infoDiv.style.display = 'none';
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
        infoDiv.style.display = 'block';
    } else {
        listSpan.textContent = '(none reported — use Custom button to type manually)';
        infoDiv.style.display = 'block';
    }
}

// ── Custom printer toggle ────────────────────────────────────────
function toggleCustomPrinter() {
    isCustomPrinter = !isCustomPrinter;
    const select = document.getElementById('config_printer_name');
    const customContainer = document.getElementById('custom-printer-container');

    if (isCustomPrinter) {
        select.style.display = 'none';
        select.removeAttribute('required');
        customContainer.style.display = 'block';
        document.getElementById('config_printer_name_custom').setAttribute('required', '');
    } else {
        select.style.display = 'block';
        select.setAttribute('required', '');
        customContainer.style.display = 'none';
        document.getElementById('config_printer_name_custom').removeAttribute('required');
    }
}

// ── Get selected printer name (from dropdown or custom input) ────
function getPrinterName() {
    if (isCustomPrinter) {
        return document.getElementById('config_printer_name_custom').value.trim();
    }
    return document.getElementById('config_printer_name').value;
}

// ── Populate structured fields from config JSON ──────────────────
function populateFieldsFromConfig(config) {
    if (!config) return;

    const fieldMap = {
        'copies': 'config_copies',
        'duplex': 'config_duplex',
        'paper_size': 'config_paper_size',
        'tray': 'config_tray',
        'color_mode': 'config_color_mode',
        'print_quality': 'config_print_quality',
        'orientation': 'config_orientation',
        'media_type': 'config_media_type',
    };

    // Set known fields
    for (const [key, elId] of Object.entries(fieldMap)) {
        const el = document.getElementById(elId);
        if (el && config[key] !== undefined && config[key] !== null) {
            el.value = config[key];
        }
    }

    // Set booleans
    document.getElementById('config_collate').checked = config.collate === true;
    document.getElementById('config_fit_to_page').checked = config.fit_to_page === true;

    // Collect remaining keys as advanced JSON
    const knownKeys = new Set([...Object.keys(fieldMap), 'collate', 'fit_to_page']);
    const advanced = {};
    for (const [key, value] of Object.entries(config)) {
        if (!knownKeys.has(key)) {
            advanced[key] = value;
        }
    }
    document.getElementById('config_advanced').value = Object.keys(advanced).length > 0
        ? JSON.stringify(advanced, null, 2)
        : '';
}

// ── Modal open: Create ───────────────────────────────────────────
function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Printer Configuration';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('config-form').action = '{{ route('admin.printer-configs.store') }}';

    // Reset form
    document.getElementById('config-form').reset();
    document.getElementById('config_print_agent_id').value = '';
    document.getElementById('config_advanced').value = '';
    document.getElementById('config_is_active').checked = true;
    document.getElementById('custom-printer-container').style.display = 'none';
    document.getElementById('config_printer_name').style.display = 'block';
    document.getElementById('config_printer_name').setAttribute('required', '');
    document.getElementById('agent-printers-info').style.display = 'none';
    isCustomPrinter = false;
    onAgentChange();

    document.getElementById('config-modal').style.display = 'flex';
}

// ── Modal open: Edit ─────────────────────────────────────────────
function openEditModal(id) {
    const config = configs.find(c => c.id === id);
    if (!config) return;

    document.getElementById('modal-title').textContent = 'Edit Printer Configuration';
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('config-form').action = '/printer-configs/' + id;

    // Reset form first
    document.getElementById('config-form').reset();
    document.getElementById('config_advanced').value = '';
    isCustomPrinter = false;
    document.getElementById('custom-printer-container').style.display = 'none';
    document.getElementById('config_printer_name').style.display = 'block';
    document.getElementById('config_printer_name').setAttribute('required', '');

    // Set agent → triggers printer dropdown population
    document.getElementById('config_print_agent_id').value = config.print_agent_id;
    onAgentChange();

    // Check if printer name is in the dropdown; if not, switch to custom
    const printerSelect = document.getElementById('config_printer_name');
    const printerOption = Array.from(printerSelect.options).find(o => o.value === config.printer_name);
    if (printerOption) {
        printerSelect.value = config.printer_name;
    } else {
        // Printer not registered — use custom input
        isCustomPrinter = true;
        printerSelect.style.display = 'none';
        printerSelect.removeAttribute('required');
        document.getElementById('custom-printer-container').style.display = 'block';
        document.getElementById('config_printer_name_custom').setAttribute('required', '');
        document.getElementById('config_printer_name_custom').value = config.printer_name;
    }

    // Populate structured fields
    populateFieldsFromConfig(config.config);

    document.getElementById('config_is_active').checked = config.is_active;
    document.getElementById('config-modal').style.display = 'flex';
}

// ── Modal close ──────────────────────────────────────────────────
function closeConfigModal() {
    document.getElementById('config-modal').style.display = 'none';
}

document.getElementById('config-modal').addEventListener('click', function(e) {
    if (e.target === this) closeConfigModal();
});

// ── Before submit: merge printer name ────────────────────────────
document.getElementById('config-form').addEventListener('submit', function(e) {
    const printerName = getPrinterName();
    if (!printerName) {
        e.preventDefault();
        alert('Please select or enter a printer name.');
        return;
    }

    // If using custom input, set the main field's value
    if (isCustomPrinter) {
        document.getElementById('config_printer_name').value = printerName;
        document.getElementById('config_printer_name').setAttribute('required', '');
        document.getElementById('config_printer_name_custom').removeAttribute('required');
    }
});
</script>
@endsection
