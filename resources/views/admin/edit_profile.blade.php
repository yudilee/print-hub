@extends('admin.layout')
@section('title', 'Edit profile: ' . $profile->name)

@section('content')
<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Edit Queue: {{ $profile->name }}</h1>
            <p>Modify settings for this virtual printer queue.</p>
        </div>
        <a href="{{ route('admin.profiles') }}" class="btn btn-secondary">← Back to List</a>
    </div>
</div>

<div class="card">
    <form action="{{ route('admin.profiles.update', $profile) }}" method="POST">
        @csrf
        @method('PUT')
        
        @if($errors->any())
            <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-row">
            <div class="form-group">
                <label for="name">Queue identifier (unique)</label>
                <input type="text" name="name" id="name" value="{{ old('name', $profile->name) }}" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <input type="text" name="description" id="description" value="{{ old('description', $profile->description) }}" placeholder="e.g. Invoice Sewa A4 Portrait">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 2;">
                <label for="paper_size">Paper Size</label>
                <select name="paper_size" id="paper_size" onchange="toggleCustomSize(this.value)">
                    @foreach(['A4', 'A5', 'Letter', 'Half Letter', 'Legal', 'F4', 'Statement', 'Executive', 'Envelope #10'] as $size)
                        <option value="{{ $size }}" {{ old('paper_size', $profile->paper_size) == $size ? 'selected' : '' }}>{{ $size }}</option>
                    @endforeach
                    <option value="CUSTOM" {{ old('paper_size', $profile->paper_size) == 'CUSTOM' ? 'selected' : '' }}>-- Custom Size --</option>
                </select>
            </div>
            <div id="custom-dims" class="form-row" style="flex: 4; display: {{ old('paper_size', $profile->paper_size) == 'CUSTOM' ? 'flex' : 'none' }}; gap: 10px; margin-top: 0;">
                <div class="form-group" style="flex: 1;">
                    <label id="width-label">Width (mm)</label>
                    <input type="number" name="custom_width" step="0.001" value="{{ old('custom_width', $profile->custom_width) }}" placeholder="e.g. 210">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label id="height-label">Height (mm)</label>
                    <input type="number" name="custom_height" step="0.001" value="{{ old('custom_height', $profile->custom_height) }}" placeholder="e.g. 297">
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
                    <option value="portrait" {{ old('orientation', $profile->orientation) == 'portrait' ? 'selected' : '' }}>Portrait</option>
                    <option value="landscape" {{ old('orientation', $profile->orientation) == 'landscape' ? 'selected' : '' }}>Landscape</option>
                </select>
            </div>
        </div>

        <div class="form-row" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
            <div style="width: 100%; margin-bottom: 0.5rem; font-size: 0.8rem; font-weight: bold; color: var(--primary);">Document Margins (mm)</div>
            <div class="form-group">
                <label>Top</label>
                <input type="number" name="margin_top" step="0.01" value="{{ old('margin_top', $profile->margin_top) }}">
            </div>
            <div class="form-group">
                <label>Bottom</label>
                <input type="number" name="margin_bottom" step="0.01" value="{{ old('margin_bottom', $profile->margin_bottom) }}">
            </div>
            <div class="form-group">
                <label>Left</label>
                <input type="number" name="margin_left" step="0.01" value="{{ old('margin_left', $profile->margin_left) }}">
            </div>
            <div class="form-group">
                <label>Right</label>
                <input type="number" name="margin_right" step="0.01" value="{{ old('margin_right', $profile->margin_right) }}">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="copies">Copies</label>
                <input type="number" name="copies" id="copies" value="{{ old('copies', $profile->copies) }}" min="1" max="99">
            </div>
            <div class="form-group">
                <label for="duplex">Duplex</label>
                <select name="duplex" id="duplex">
                    <option value="one-sided" {{ old('duplex', $profile->duplex) == 'one-sided' ? 'selected' : '' }}>One-sided</option>
                    <option value="two-sided-long" {{ old('duplex', $profile->duplex) == 'two-sided-long' ? 'selected' : '' }}>Two-sided (Long edge)</option>
                    <option value="two-sided-short" {{ old('duplex', $profile->duplex) == 'two-sided-short' ? 'selected' : '' }}>Two-sided (Short edge)</option>
                </select>
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer; color: var(--primary);">
                    <input type="checkbox" name="fit_to_page" value="1" {{ old('fit_to_page', $profile->extra_options['fit_to_page'] ?? false) ? 'checked' : '' }} style="width: 18px; height: 18px;">
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
                        <option value="{{ $agent->id }}" {{ old('print_agent_id', $profile->print_agent_id) == $agent->id ? 'selected' : '' }} data-printers='{{ json_encode($agent->printers ?? []) }}'>
                            {{ $agent->name }} {{ $agent->isOnline() ? '●' : '○' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="flex: 3;">
                <label for="default_printer">Target Printer Name <span style="color: var(--danger);">*</span></label>
                <div id="printer_input_container">
                    @if($profile->agent && $profile->agent->printers)
                        <select name="default_printer" id="default_printer" required>
                            @foreach($profile->agent->printers as $p)
                                <option value="{{ $p }}" {{ old('default_printer', $profile->default_printer) == $p ? 'selected' : '' }}>{{ $p }}</option>
                            @endforeach
                        </select>
                    @else
                        <input type="text" name="default_printer" id="default_printer" value="{{ old('default_printer', $profile->default_printer) }}" required>
                    @endif
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="{{ route('admin.profiles') }}" class="btn btn-secondary">Cancel</a>
        </div>
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
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D">`;
        return;
    }

    let html = `<select name="default_printer" id="default_printer" required>`;
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
</script>
@endsection
