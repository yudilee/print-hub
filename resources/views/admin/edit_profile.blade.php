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

        {{-- Paper Settings --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                📐 Paper Settings
            </legend>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="name">Queue Identifier <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $profile->name) }}" required placeholder="e.g. invoice_sewa_a4">
                </div>
                <div class="form-group" style="flex: 3;">
                    <label for="description">Description</label>
                    <input type="text" name="description" id="description" value="{{ old('description', $profile->description) }}" placeholder="e.g. Invoice Sewa A4 Portrait — describes the document type and layout">
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

            <div class="form-row" style="background: rgba(99,102,241,0.04); padding: 1rem; border-radius: 8px;">
                <div style="width: 100%; margin-bottom: 0.5rem; font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">Document Margins (mm)</div>
                <div class="form-group">
                    <label>Top</label>
                    <input type="number" name="margin_top" step="0.01" value="{{ old('margin_top', $profile->margin_top) }}" placeholder="e.g. 10">
                </div>
                <div class="form-group">
                    <label>Bottom</label>
                    <input type="number" name="margin_bottom" step="0.01" value="{{ old('margin_bottom', $profile->margin_bottom) }}" placeholder="e.g. 10">
                </div>
                <div class="form-group">
                    <label>Left</label>
                    <input type="number" name="margin_left" step="0.01" value="{{ old('margin_left', $profile->margin_left) }}" placeholder="e.g. 15">
                </div>
                <div class="form-group">
                    <label>Right</label>
                    <input type="number" name="margin_right" step="0.01" value="{{ old('margin_right', $profile->margin_right) }}" placeholder="e.g. 15">
                </div>
            </div>
        </fieldset>

        {{-- Printer Control --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                ⚙️ Printer Control
            </legend>

            <div class="form-row">
                <div class="form-group">
                    <label for="copies">Number of Copies</label>
                    <input type="number" name="copies" id="copies" value="{{ old('copies', $profile->copies) }}" min="1" max="99" placeholder="1">
                </div>
                <div class="form-group">
                    <label for="duplex">Duplex Mode</label>
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

            <div class="form-row">
                <div class="form-group">
                    <label for="tray_source">Tray / Paper Source <span class="help-tip">?<span class="help-tip-popover">Which paper tray to use. 'Auto' lets the printer decide.</span></span></label>
                    <select name="tray_source" id="tray_source">
                        <option value="">Auto (Default)</option>
                        <option value="auto" {{ old('tray_source', $profile->tray_source) == 'auto' ? 'selected' : '' }}>Auto Select</option>
                        <option value="tray1" {{ old('tray_source', $profile->tray_source) == 'tray1' ? 'selected' : '' }}>Tray 1</option>
                        <option value="tray2" {{ old('tray_source', $profile->tray_source) == 'tray2' ? 'selected' : '' }}>Tray 2</option>
                        <option value="tray3" {{ old('tray_source', $profile->tray_source) == 'tray3' ? 'selected' : '' }}>Tray 3</option>
                        <option value="manual" {{ old('tray_source', $profile->tray_source) == 'manual' ? 'selected' : '' }}>Manual Feed</option>
                        <option value="envelope" {{ old('tray_source', $profile->tray_source) == 'envelope' ? 'selected' : '' }}>Envelope Feeder</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="color_mode">Color Mode</label>
                    <select name="color_mode" id="color_mode">
                        <option value="color" {{ old('color_mode', $profile->color_mode) == 'color' ? 'selected' : '' }}>Color</option>
                        <option value="monochrome" {{ old('color_mode', $profile->color_mode) == 'monochrome' ? 'selected' : '' }}>Monochrome (B&W)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="print_quality">Print Quality</label>
                    <select name="print_quality" id="print_quality">
                        <option value="normal" {{ old('print_quality', $profile->print_quality) == 'normal' ? 'selected' : '' }}>Normal (600 DPI)</option>
                        <option value="draft" {{ old('print_quality', $profile->print_quality) == 'draft' ? 'selected' : '' }}>Draft (300 DPI)</option>
                        <option value="high" {{ old('print_quality', $profile->print_quality) == 'high' ? 'selected' : '' }}>High (1200 DPI)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="media_type">Media Type</label>
                    <select name="media_type" id="media_type">
                        <option value="" {{ old('media_type', $profile->media_type) == '' ? 'selected' : '' }}>Plain Paper</option>
                        <option value="plain" {{ old('media_type', $profile->media_type) == 'plain' ? 'selected' : '' }}>Plain Paper</option>
                        <option value="glossy" {{ old('media_type', $profile->media_type) == 'glossy' ? 'selected' : '' }}>Glossy / Photo</option>
                        <option value="envelope" {{ old('media_type', $profile->media_type) == 'envelope' ? 'selected' : '' }}>Envelope</option>
                        <option value="label" {{ old('media_type', $profile->media_type) == 'label' ? 'selected' : '' }}>Label / Sticker</option>
                        <option value="continuous_feed" {{ old('media_type', $profile->media_type) == 'continuous_feed' ? 'selected' : '' }}>Continuous Feed</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="scaling_percentage">Scaling (%)</label>
                    <input type="number" name="scaling_percentage" id="scaling_percentage" value="{{ old('scaling_percentage', $profile->scaling_percentage ?? 100) }}" min="1" max="400" step="1" placeholder="100">
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem; gap: 1rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="collate" value="1" {{ old('collate', $profile->collate ?? true) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Collate Copies
                    </label>
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="reverse_order" value="1" {{ old('reverse_order', $profile->reverse_order ?? false) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Reverse Page Order
                    </label>
                </div>
            </div>
        </fieldset>

        {{-- Physical Assignment --}}
        <fieldset style="border: 1px dashed var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--warning); padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                🔗 Physical Assignment
            </legend>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">Assign this queue to a physical print agent and select the target printer.</p>
            <div class="form-row">
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
                            <input type="text" name="default_printer" id="default_printer" value="{{ old('default_printer', $profile->default_printer) }}" required placeholder="e.g. Brother-HL-L2360D">
                        @endif
                    </div>
                </div>
            </div>
        </fieldset>

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
