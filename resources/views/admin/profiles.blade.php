@extends('admin.layout')
@section('title', 'Profiles')

@section('content')
<div class="page-header">
    <h1>Virtual Queues</h1>
    <p>Define virtual printer queues (profiles) and assign them to physical hubs and printers.</p>
</div>

{{-- Create Profile --}}
<div class="card">
    <div class="card-header"><h2>Create New Queue</h2></div>
    <form action="{{ route('admin.profiles.store') }}" method="POST">
        @csrf
        
        @if($errors->any())
            <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr;">
            <div class="form-group">
                <label for="name">Queue identifier (e.g. invoice_sewa)</label>
                <input type="text" name="name" id="name" required placeholder="unique_queue_name">
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" placeholder="e.g. Invoice Sewa A4 Portrait">
            </div>
            <div class="form-group">
                <label for="branch_id">Branch <span style="color: var(--danger);">*</span></label>
                <select name="branch_id" id="branch_id" required>
                    <option value="">-- Select Branch --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->company->code }} / {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="paper_size">Paper Size</label>
                <select name="paper_size" id="paper_size" onchange="toggleCustomSize(this.value)">
                    <option value="A4">A4</option>
                    <option value="A5">A5</option>
                    <option value="Letter">Letter</option>
                    <option value="Half Letter" selected>Half Letter (8.5" x 5.5")</option>
                    <option value="Legal">Legal</option>
                    <option value="F4">F4 / Folio</option>
                    <option value="Statement">Statement</option>
                    <option value="Executive">Executive</option>
                    <option value="Envelope #10">Envelope #10</option>
                    <option value="CUSTOM">-- Custom Size --</option>
                </select>
            </div>
            <div id="custom-dims" class="form-row" style="flex: 4; display: none; gap: 10px; margin-top: 0;">
                <div class="form-group" style="flex: 1;">
                    <label id="width-label">Width (mm)</label>
                    <input type="number" name="custom_width" step="0.001" placeholder="e.g. 210">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label id="height-label">Height (mm)</label>
                    <input type="number" name="custom_height" step="0.001" placeholder="e.g. 297">
                </div>
                <div class="form-group" style="flex: 2; display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.8rem; color: var(--text-muted);">
                        <input type="checkbox" name="use_inches" id="use_inches" value="1" onchange="toggleUnit(this.checked)"> Use Inches
                    </label>
                </div>
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="orientation">Orientation</label>
                <select name="orientation" id="orientation">
                    <option value="portrait" selected>Portrait</option>
                    <option value="landscape">Landscape</option>
                </select>
            </div>
        </div>
        
        <div class="form-row" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="width: 100%; margin-bottom: 0.5rem; font-size: 0.8rem; font-weight: bold; color: var(--primary);">Document Margins (mm)</div>
            <div class="form-group">
                <label>Top</label>
                <input type="number" name="margin_top" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label>Bottom</label>
                <input type="number" name="margin_bottom" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label>Left</label>
                <input type="number" name="margin_left" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label>Right</label>
                <input type="number" name="margin_right" step="0.01" value="0">
            </div>
            <div style="width: 100%; margin-top: 0.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="applyDotMatrixDefaults()">Suggest Dot-Matrix Margins (4.23mm)</button>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="copies">Copies</label>
                <input type="number" name="copies" id="copies" value="1" min="1" max="99">
            </div>
            <div class="form-group">
                <label for="duplex">Duplex</label>
                <select name="duplex" id="duplex">
                    <option value="one-sided" selected>One-sided</option>
                    <option value="two-sided-long">Two-sided (Long edge)</option>
                    <option value="two-sided-short">Two-sided (Short edge)</option>
                </select>
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--primary);">
                    <input type="checkbox" name="fit_to_page" value="1" style="width: 18px; height: 18px;">
                    Scale to Fit (Fit to Paper)
                </label>
            </div>
        </div>
        <div class="form-row" style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px dashed rgba(255,255,255,0.1);">
            <div style="width: 100%; margin-bottom: 0.5rem; font-size: 0.8rem; font-weight: bold; color: var(--warning);">Physical Assignment (Required)</div>
            <div class="form-group" style="flex: 2;">
                <label for="print_agent_id">Connected Agent <span style="color: var(--danger);">*</span></label>
                <select name="print_agent_id" id="print_agent_id" required onchange="updatePrinterDropdown(this.value)">
                    <option value="">-- Select Agent --</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}" data-printers='{{ json_encode($agent->printers ?? []) }}'>
                            {{ $agent->name }} {{ $agent->isOnline() ? '●' : '○' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="flex: 3;">
                <label for="default_printer">Target Printer Name <span style="color: var(--danger);">*</span></label>
                <div id="printer_input_container">
                    <input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D">
                </div>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Leave blank to use the hub's OS default printer.</p>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">+ Create Queue</button>
    </form>
</div>

<script>
const agentPrinters = {
    @foreach($agents as $agent)
    "{{ $agent->id }}": {!! json_encode($agent->printers ?? []) !!},
    @endforeach
};

function updatePrinterDropdown(agentId) {
    const container = document.getElementById('printer_input_container');
    const printers = agentPrinters[agentId];

    if (!agentId) {
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" placeholder="e.g. Brother-HL-L2360D">`;
        return;
    }

    let html = `<select name="default_printer" id="default_printer">`;
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

function toggleCustomSize(val) {
    const dims = document.getElementById('custom-dims');
    dims.style.display = (val === 'CUSTOM') ? 'flex' : 'none';
}
function toggleUnit(isInch) {
    document.getElementById('width-label').innerText = isInch ? 'Width (Inch)' : 'Width (mm)';
    document.getElementById('height-label').innerText = isInch ? 'Height (Inch)' : 'Height (mm)';
}
function applyDotMatrixDefaults() {
    document.getElementsByName('margin_top')[0].value = 4.23;
    document.getElementsByName('margin_bottom')[0].value = 4.23;
    document.getElementsByName('margin_left')[0].value = 4.23;
    document.getElementsByName('margin_right')[0].value = 4.23;
}
</script>

{{-- Profile List --}}
<div class="card">
    <div class="card-header"><h2>Active Queues ({{ $profiles->count() }})</h2></div>
    <table>
        <thead>
            <tr>
                <th>Queue Name</th>
                <th>Branch</th>
                <th>Description</th>
                <th>Connected Agent</th>
                <th>Printer Name</th>
                <th>Paper</th>
                <th>Orient.</th>
                <th>Scaling</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($profiles as $profile)
            <tr>
                <td><strong class="mono" style="color: var(--primary);">{{ $profile->name }}</strong></td>
                <td>
                    @if($profile->branch)
                        <span class="badge badge-info">{{ $profile->branch->company->code ?? '' }}</span>
                        <span style="font-size: 0.8rem;">{{ $profile->branch->name }}</span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                    @endif
                </td>
                <td style="color: var(--text-muted); font-size: 0.85rem;">{{ $profile->description ?? '—' }}</td>
                <td>
                    @if($profile->agent)
                        <span style="display: flex; align-items: center; gap: 5px;">
                            <span style="color: {{ $profile->agent->isOnline() ? 'var(--success)' : 'var(--danger)' }}; font-size: 1.2rem;">●</span>
                            {{ $profile->agent->name }}
                        </span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">Generic Pool</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">
                    @if($profile->default_printer)
                        <code>{{ $profile->default_printer }}</code>
                    @else
                        <span style="font-style: italic;">OS Default</span>
                    @endif
                </td>
                <td><span class="badge badge-info">{{ $profile->paper_size }}</span></td>
                <td>{{ ucfirst($profile->orientation) }}</td>
                <td>
                    @if($profile->extra_options['fit_to_page'] ?? false)
                        <span style="color: var(--success); font-size: 0.8rem;">Fit to Page</span>
                    @else
                        <span style="color: var(--text-muted); font-size: 0.8rem;">Actual Size</span>
                    @endif
                </td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <a href="{{ route('admin.profiles.edit', $profile) }}" class="btn btn-secondary btn-sm" style="text-decoration: none;">
                            Edit
                        </a>
                        <button class="btn btn-secondary btn-sm" onclick="openTestModal('{{ $profile->id }}', '{{ $profile->name }}', '{{ $profile->agent->name ?? 'Any Online Agent' }}', '{{ $profile->default_printer ?: 'Default' }}')">
                            Test
                        </button>
                        <form action="{{ route('admin.profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Delete this queue?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9" style="color: var(--text-muted);">No profiles created yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Test Print Modal --}}
<div id="test-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 400px; padding: 2rem;">
        <div class="card-header"><h2 id="modal-title">Test Queue</h2></div>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem;">
            Upload a PDF to test this queue. It will be sent to: <br>
            <strong id="modal-target-info" style="color: var(--primary);">Agent: ?, Printer: ?</strong>
        </p>
        <form id="test-print-form" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label>Select PDF File</label>
                <input type="file" name="file" accept="application/pdf" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="closeTestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Send to Agent</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTestModal(id, name, agent, printer) {
    const modal = document.getElementById('test-modal');
    const form = document.getElementById('test-print-form');
    const title = document.getElementById('modal-title');
    const info = document.getElementById('modal-target-info');

    title.innerText = `Test Queue: ${name}`;
    info.innerText = `Agent: ${agent}, Printer: ${printer}`;
    form.action = `/profiles/${id}/test-print`;
    
    modal.style.display = 'flex';
}

function closeTestModal() {
    document.getElementById('test-modal').style.display = 'none';
}
</script>
@endsection
