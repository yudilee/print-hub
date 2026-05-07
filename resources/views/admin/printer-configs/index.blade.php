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
    <div class="card" style="width: 600px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
        <div class="card-header">
            <h2 id="modal-title">Add Printer Configuration</h2>
        </div>
        <form id="config-form" method="POST">
            @csrf
            <input type="hidden" name="_method" id="form-method" value="POST">

            <div class="form-group">
                <label for="config_print_agent_id">Print Agent <span style="color: var(--danger);">*</span></label>
                <select name="print_agent_id" id="config_print_agent_id" required>
                    <option value="">-- Select Agent --</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="config_printer_name">Printer Name <span style="color: var(--danger);">*</span></label>
                <input type="text" name="printer_name" id="config_printer_name" required placeholder="e.g. HP LaserJet 400">
            </div>

            <div class="form-group">
                <label for="config_json">Configuration (JSON) <span style="color: var(--danger);">*</span></label>
                <textarea name="config" id="config_json" rows="8" required placeholder='{
    "copies": 2,
    "duplex": "long-edge",
    "paper_size": "A4",
    "tray": "Tray 2",
    "color_mode": "grayscale",
    "print_quality": "high"
}' style="font-family: 'Fira Code', monospace; font-size: 0.8rem;"></textarea>
                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.3rem;">
                    Valid JSON object with printer option keys and values.
                </div>
            </div>

            <div class="form-group">
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
let configs = @json($configs->items());

function openCreateModal() {
    document.getElementById('modal-title').textContent = 'Add Printer Configuration';
    document.getElementById('form-method').value = 'POST';
    document.getElementById('config-form').action = '{{ route('admin.printer-configs.store') }}';
    document.getElementById('config_print_agent_id').value = '';
    document.getElementById('config_printer_name').value = '';
    document.getElementById('config_json').value = '';
    document.getElementById('config_is_active').checked = true;
    document.getElementById('config-modal').style.display = 'flex';
}

function openEditModal(id) {
    const config = configs.find(c => c.id === id);
    if (!config) return;

    document.getElementById('modal-title').textContent = 'Edit Printer Configuration';
    document.getElementById('form-method').value = 'PUT';
    document.getElementById('config-form').action = '/printer-configs/' + id;
    document.getElementById('config_print_agent_id').value = config.print_agent_id;
    document.getElementById('config_printer_name').value = config.printer_name;
    document.getElementById('config_json').value = JSON.stringify(config.config, null, 2);
    document.getElementById('config_is_active').checked = config.is_active;
    document.getElementById('config-modal').style.display = 'flex';
}

function closeConfigModal() {
    document.getElementById('config-modal').style.display = 'none';
}

document.getElementById('config-modal').addEventListener('click', function(e) {
    if (e.target === this) closeConfigModal();
});
</script>
@endsection
