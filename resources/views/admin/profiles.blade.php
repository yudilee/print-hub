@extends('admin.layout')
@section('title', 'Print Queues')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Queues']]" />

<div class="page-header">
    <h1>Print Queues</h1>
    <p>Define print profiles with paper size, margins, and advanced printer options — then assign them to physical agents.</p>
</div>

{{-- Create Profile --}}
<div class="card">
    <div class="card-header"><h2>Create New Queue</h2></div>
    <form action="{{ route('admin.profiles.store') }}" method="POST">
        @csrf
        
        {{-- Hidden cloned_from field for audit (Task 2.4) --}}
        @if(isset($clonedFrom))
        <input type="hidden" name="cloned_from" value="{{ $clonedFrom }}">
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
                    <option value="Half Letter" {{ isset($clonedProfile) && $clonedProfile->paper_size == 'Half Letter' ? 'selected' : '' }}>Half Letter (8.5" x 5.5")</option>
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
        
        <div class="expandable" style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 0.8rem; font-weight: bold; color: var(--primary);">
                <span>📐 Document Margins (mm)</span>
                <span class="expandable-arrow" style="color: var(--text-muted);">▸</span>
            </div>
        </div>
        <div class="expandable-content" style="padding: 0 1rem 1rem;">
            <div class="form-row">
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
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="applyDotMatrixDefaults()">Suggest Dot-Matrix Margins (4.23mm)</button>
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
                <select name="print_agent_id" id="print_agent_id" required onchange="updatePrinterDropdown(this.value); updateAdvancedOptions(this.value)">
                    <option value="">-- Select Agent --</option>
                    @foreach($agents as $agent)
                        <option value="{{ $agent->id }}"
                            data-printers='{{ json_encode($agent->printers ?? []) }}'
                            data-capabilities='{{ json_encode($agent->capabilities ?? []) }}'>
                            {{ $agent->name }} {{ $agent->isOnline() ? '●' : '○' }}
                        </option>
                    @endforeach
                </select>
                <div id="agent-capability-summary" style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; min-height: 18px;"></div>
            </div>
            <div class="form-group" style="flex: 3;">
                <label for="default_printer">Target Printer Name <span style="color: var(--danger);">*</span></label>
                <div id="printer_input_container">
                    <input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D">
                </div>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 5px;">Leave blank to use the hub's OS default printer.</p>
            </div>
        </div>

        {{-- Advanced Printer Options --}}
        <div class="expandable" style="background: rgba(59,130,246,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid rgba(59,130,246,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 0.8rem; font-weight: bold; color: var(--primary);">
                <span>⚙️ Advanced Printer Options</span>
                <span class="expandable-arrow" style="color: var(--text-muted);">▸</span>
            </div>
        </div>
        <div class="expandable-content" style="padding: 0 1rem 1rem;">
        <div class="form-row">
            <div class="form-group">
                <label for="tray_source">Tray / Paper Source <span class="help-tip">?<span class="help-tip-popover">Which paper tray to use. Select an agent above to see actual tray names from its printers.</span></span></label>
                <select name="tray_source" id="tray_source">
                    <option value="">Auto (Default)</option>
                    <option value="AutoSelect">Auto Select</option>
                    <option value="Tray1">Tray 1</option>
                    <option value="Tray2">Tray 2</option>
                    <option value="Tray3">Tray 3</option>
                    <option value="ManualFeed">Manual Feed</option>
                    <option value="Bypass Tray">Bypass Tray</option>
                    <option value="Envelope">Envelope Feeder</option>
                </select>
            </div>
            <div class="form-group">
                <label for="color_mode">Color Mode</label>
                <select name="color_mode" id="color_mode">
                    <option value="color">Color</option>
                    <option value="monochrome">Monochrome (B&W)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="print_quality">Print Quality</label>
                <select name="print_quality" id="print_quality">
                    <option value="normal">Normal (600 DPI)</option>
                    <option value="draft">Draft (300 DPI)</option>
                    <option value="high">High (1200 DPI)</option>
                </select>
            </div>
            <div class="form-group">
                <label for="media_type">Media Type</label>
                <select name="media_type" id="media_type">
                    <option value="">Plain Paper</option>
                    <option value="plain">Plain Paper</option>
                    <option value="glossy">Glossy / Photo</option>
                    <option value="envelope">Envelope</option>
                    <option value="label">Label / Sticker</option>
                    <option value="continuous_feed">Continuous Feed</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="scaling_percentage">Scaling (%)</label>
                <input type="number" name="scaling_percentage" id="scaling_percentage" value="100" min="1" max="400" step="1">
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem; gap: 1rem;">
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="collate" value="1" checked style="width: 18px; height: 18px;">
                    Collate Copies
                </label>
                <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="reverse_order" value="1" style="width: 18px; height: 18px;">
                    Reverse Page Order
                </label>
            </div>
        </div>
        </div>

        {{-- Watermark Configuration --}}
        <div class="expandable" style="background: rgba(59,130,246,0.05); padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px solid rgba(59,130,246,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 0.8rem; font-weight: bold; color: var(--primary);">
                <span>💧 Watermark</span>
                <span class="expandable-arrow" style="color: var(--text-muted);">▸</span>
            </div>
        </div>
        <div class="expandable-content" style="padding: 0 1rem 1rem;">
            <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 0.75rem;">Overlay a watermark on printed documents. Leave blank to disable.</p>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="watermark_text">Watermark Text (all copies)</label>
                    <input type="text" name="watermark_text" id="watermark_text" placeholder="e.g. CONFIDENTIAL, DRAFT, COPY">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="watermark_position">Position</label>
                    <select name="watermark_position" id="watermark_position">
                        <option value="center">Center</option>
                        <option value="tile">Tile (Repeating)</option>
                        <option value="top-left">Top Left</option>
                        <option value="top-right">Top Right</option>
                        <option value="bottom-left">Bottom Left</option>
                        <option value="bottom-right">Bottom Right</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="watermark_opacity">Opacity: <span id="opacity-value">0.3</span></label>
                    <input type="range" name="watermark_opacity" id="watermark_opacity" min="0.1" max="1" step="0.05" value="0.3" oninput="document.getElementById(\"opacity-value\").textContent=this.value;">
                </div>
                <div class="form-group">
                    <label for="watermark_rotation">Rotation (°): <span id="rotation-value">-45</span></label>
                    <input type="range" name="watermark_rotation" id="watermark_rotation" min="-90" max="90" step="5" value="-45" oninput="document.getElementById(\"rotation-value\").textContent=this.value;">
                </div>
            </div>

            {{-- Per-Copy Watermark Configs --}}
            <div id="per-copy-watermark-section" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border); display: none;">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">📋 Per-Copy Watermark Configuration</div>
                <p style="color: var(--text-muted); font-size: 0.75rem; margin-bottom: 0.75rem;">
                    When copies > 1, you can configure a <strong>different watermark</strong> for each copy — including text, opacity, rotation, and position.
                    Leave empty to use the single watermark settings above for all copies.
                </p>
                <div id="copy-watermark-configs"></div>
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

const agentCapabilities = {
    @foreach($agents as $agent)
    "{{ $agent->id }}": {!! json_encode($agent->capabilities ?? []) !!},
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

function updateAdvancedOptions(agentId) {
    const caps = agentCapabilities[agentId];
    const summaryEl = document.getElementById('agent-capability-summary');

    if (!caps || !caps.printers || Object.keys(caps.printers).length === 0) {
        summaryEl.innerHTML = '<span style="color: var(--text-muted); font-size:0.8rem;">No capabilities data available. Showing all common options.</span>';
        return;
    }

    // Collect all supported values across all printers in this agent
    const allTrays = new Set();
    const allColorModes = new Set();
    const allResolutions = new Set();
    const allMediaTypes = new Set();
    const allPaperSizes = new Set();
    const allDuplexModes = new Set();
    let printerCount = 0;

    Object.values(caps.printers).forEach(p => {
        printerCount++;
        (p.trays || []).forEach(t => allTrays.add(t));
        (p.color_modes || []).forEach(c => allColorModes.add(c));
        (p.resolutions || []).forEach(r => allResolutions.add(r));
        (p.media_types || []).forEach(m => allMediaTypes.add(m));
        (p.media_sizes || []).forEach(s => allPaperSizes.add(s));
        if (p.duplex) {
            if (Array.isArray(p.duplex)) {
                p.duplex.forEach(d => allDuplexModes.add(d));
            } else {
                allDuplexModes.add('TwoSidedLong');
                allDuplexModes.add('TwoSidedShort');
            }
        }
    });

    // Build rich summary
    let summary = `<span style="color: var(--success);">✓ ${printerCount} printer(s)</span>`;
    if (allDuplexModes.size > 0) summary += ` · 🔁 Duplex`;
    if (allColorModes.has('color') && (allColorModes.has('gray') || allColorModes.has('monochrome'))) summary += ` · 🎨 Color + B&W`;
    else if (allColorModes.has('gray') || allColorModes.has('monochrome')) summary += ` · ⚫ B&W only`;
    else if (allColorModes.has('color')) summary += ` · 🎨 Color only`;
    if (allTrays.size > 0) summary += ` · 📦 ${allTrays.size} tray(s)`;
    if (allPaperSizes.size > 0) summary += ` · 📄 ${allPaperSizes.size} paper size(s)`;
    if (allResolutions.size > 0) summary += ` · ⚡ ${allResolutions.size} resolution(s)`;
    summaryEl.innerHTML = summary;

    // ── Paper Size ─────────────────────────────────────────
    const PAPER_LABELS = {
        'A4': 'A4 (210×297mm)', 'A3': 'A3 (297×420mm)', 'A5': 'A5 (148×210mm)',
        'A6': 'A6 (105×148mm)', 'Letter': 'Letter (216×279mm)', 'Legal': 'Legal (216×356mm)',
        'Tabloid': 'Tabloid (279×432mm)', 'Executive': 'Executive (184×267mm)',
        'A2': 'A2 (420×594mm)', 'A1': 'A1 (594×841mm)', 'A0': 'A0 (841×1189mm)',
        'B4': 'B4 (250×353mm)', 'B5': 'B5 (176×250mm)',
    };
    const sizeOptions = [{ value: '', label: 'Default (Printer Setting)' }];
    // Use actual discovered sizes
    if (allPaperSizes.size > 0) {
        const sortedSizes = Array.from(allPaperSizes).sort();
        sortedSizes.forEach(s => {
            if (s && s !== '') {
                sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s });
            }
        });
    } else {
        // Fallback: common sizes
        const commonSizes = ['A4', 'A3', 'A5', 'Letter', 'Legal', 'Tabloid', 'Executive'];
        commonSizes.forEach(s => sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s }));
    }
    sizeOptions.push({ value: 'CUSTOM', label: 'Custom Size...' });
    resetSelectOptions('paper_size', sizeOptions);

    // ── Duplex ────────────────────────────────────────────
    const DUPLEX_MAP = {
        'None': { value: 'none', label: 'No Duplex (One-sided)' },
        'TwoSidedLong': { value: 'short_edge', label: 'Flip on Long Edge (Standard)' },
        'TwoSidedShort': { value: 'long_edge', label: 'Flip on Short Edge (Flip)' },
    };
    const duplexOptions = [{ value: '', label: 'Default' }];
    if (allDuplexModes.size > 0) {
        allDuplexModes.forEach(d => {
            if (DUPLEX_MAP[d]) duplexOptions.push(DUPLEX_MAP[d]);
        });
    }
    if (duplexOptions.length <= 1) {
        duplexOptions.push(
            { value: 'none', label: 'No Duplex (One-sided)' },
            { value: 'short_edge', label: 'Flip on Long Edge (Standard)' },
            { value: 'long_edge', label: 'Flip on Short Edge (Flip)' }
        );
    }
    resetSelectOptions('duplex', duplexOptions);

    // ── Tray Source ───────────────────────────────────────
    const trayOptions = [
        { value: '', label: 'Auto (Default)' },
    ];
    allTrays.forEach(trayName => {
        if (trayName && trayName !== '') {
            trayOptions.push({ value: trayName, label: trayName });
        }
    });
    const commonTrays = ['AutoSelect', 'Tray1', 'Tray2', 'Tray3', 'ManualFeed', 'Bypass Tray', 'Envelope'];
    commonTrays.forEach(t => {
        if (!allTrays.has(t) && !trayOptions.some(o => o.value === t)) {
            trayOptions.push({ value: t, label: t });
        }
    });
    resetSelectOptions('tray_source', trayOptions);

    // ── Color Mode ────────────────────────────────────────
    const colorOptions = [];
    if (allColorModes.has('color')) colorOptions.push({ value: 'color', label: 'Color' });
    if (allColorModes.has('gray') || allColorModes.has('monochrome'))
        colorOptions.push({ value: 'monochrome', label: 'Monochrome (B&W)' });
    if (colorOptions.length === 0) {
        colorOptions.push({ value: 'color', label: 'Color' }, { value: 'monochrome', label: 'Monochrome (B&W)' });
    }
    resetSelectOptions('color_mode', colorOptions);

    // ── Print Quality (Resolutions) ───────────────────────
    const qualityOptions = [];
    if (allResolutions.size > 0) {
        allResolutions.forEach(r => {
            const numeric = parseInt(r.replace(/[^\d]/g, ''));
            if (numeric <= 300 && !qualityOptions.some(o => o.value === 'draft'))
                qualityOptions.push({ value: 'draft', label: `Draft (${r.replace('dpi', '').trim() || numeric + 'dpi'})` });
            else if (numeric <= 600 && !qualityOptions.some(o => o.value === 'normal'))
                qualityOptions.push({ value: 'normal', label: `Normal (${r.replace('dpi', '').trim() || numeric + 'dpi'})` });
            else if (numeric > 600 && !qualityOptions.some(o => o.value === 'high'))
                qualityOptions.push({ value: 'high', label: `High (${r.replace('dpi', '').trim() || numeric + 'dpi'})` });
        });
    }
    if (qualityOptions.length === 0) {
        qualityOptions.push(
            { value: 'draft', label: 'Draft (300 DPI)' },
            { value: 'normal', label: 'Normal (600 DPI)' },
            { value: 'high', label: 'High (1200 DPI)' }
        );
    }
    resetSelectOptions('print_quality', qualityOptions);

    // ── Media Type ────────────────────────────────────────
    const mediaOptions = [
        { value: '', label: 'Plain Paper' },
    ];
    const MEDIA_LABELS = { plain: 'Plain Paper', glossy: 'Glossy / Photo', envelope: 'Envelope', label: 'Label / Sticker', continuous_feed: 'Continuous Feed' };
    if (allMediaTypes.size > 0) {
        allMediaTypes.forEach(m => {
            const key = m.toLowerCase().replace(/[^a-z]/g, '');
            mediaOptions.push({ value: m, label: MEDIA_LABELS[key] || m });
        });
    } else {
        ['plain', 'glossy', 'envelope', 'label', 'continuous_feed'].forEach(m => {
            mediaOptions.push({ value: m, label: MEDIA_LABELS[m] || m });
        });
    }
    resetSelectOptions('media_type', mediaOptions);
}

function resetSelectOptions(selectId, options) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const currentVal = select.value;
    select.innerHTML = options.map(o => `<option value="${o.value}">${o.label}</option>`).join('');
    // Re-select if the current value is still available
    if (options.some(o => o.value === currentVal)) {
        select.value = currentVal;
    }
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

// ── Per-Copy Watermark UI ─────────────────────────────────────
function initPerCopyWatermark() {
    const copiesInput = document.getElementById('copies');
    if (!copiesInput) return;

    copiesInput.addEventListener('input', updateCopyWatermarkConfigs);
    copiesInput.addEventListener('change', updateCopyWatermarkConfigs);
    updateCopyWatermarkConfigs();
}

function getCopyConfigValue(index, field) {
    // Try to read from existing DOM elements first
    const el = document.querySelector(`[name="watermark_copies[${index}][${field}]"]`);
    if (el) return el.value;
    // Fall back to saved data
    if (window._savedWatermarkCopies && window._savedWatermarkCopies[index]) {
        return window._savedWatermarkCopies[index][field] || '';
    }
    return '';
}

function updateCopyWatermarkConfigs() {
    const copies = parseInt(document.getElementById('copies')?.value || 1);
    const section = document.getElementById('per-copy-watermark-section');
    const container = document.getElementById('copy-watermark-configs');

    if (!section || !container) return;

    if (copies <= 1) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';

    // Preserve existing values from current DOM
    const existingConfigs = [];
    for (let i = 0; i < 99; i++) {
        const textEl = document.querySelector(`[name="watermark_copies[${i}][text]"]`);
        if (!textEl) break;
        existingConfigs[i] = {
            text: textEl.value,
            opacity: document.querySelector(`[name="watermark_copies[${i}][opacity]"]`)?.value || '0.3',
            rotation: document.querySelector(`[name="watermark_copies[${i}][rotation]"]`)?.value || '-45',
            position: document.querySelector(`[name="watermark_copies[${i}][position]"]`)?.value || 'center',
        };
    }

    const positionOptions = [
        { value: 'center', label: 'Center' },
        { value: 'tile', label: 'Tile (Repeating)' },
        { value: 'top-left', label: 'Top Left' },
        { value: 'top-right', label: 'Top Right' },
        { value: 'bottom-left', label: 'Bottom Left' },
        { value: 'bottom-right', label: 'Bottom Right' },
    ];

    let html = '';
    for (let i = 0; i < copies; i++) {
        const cfg = existingConfigs[i] || {};
        const textVal = cfg.text || '';
        const opacityVal = cfg.opacity || '0.3';
        const rotationVal = cfg.rotation || '-45';
        const positionVal = cfg.position || 'center';

        html += '<div style="border: 1px solid var(--border); border-radius: 6px; padding: 0.75rem; margin-bottom: 0.75rem; background: rgba(255,255,255,0.02);">';
        html += '<div style="font-size: 0.8rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">📄 Copy ' + (i + 1) + '</div>';

        // Text
        html += '<div class="form-row" style="gap: 8px; margin-bottom: 0.5rem;">';
        html += '<div class="form-group" style="flex: 2;">';
        html += '<label style="font-size: 0.7rem;">Watermark Text</label>';
        html += '<input type="text" name="watermark_copies[' + i + '][text]" value="' + textVal.replace(/"/g, '"') + '" placeholder="e.g. Customer Copy" style="font-size: 0.8rem;">';
        html += '</div>';
        html += '<div class="form-group" style="flex: 1;">';
        html += '<label style="font-size: 0.7rem;">Position</label>';
        html += '<select name="watermark_copies[' + i + '][position]" style="font-size: 0.8rem;">';
        positionOptions.forEach(function(po) {
            const sel = po.value === positionVal ? ' selected' : '';
            html += '<option value="' + po.value + '"' + sel + '>' + po.label + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '</div>';

        // Opacity + Rotation
        html += '<div class="form-row" style="gap: 8px;">';
        html += '<div class="form-group" style="flex: 1;">';
        html += '<label style="font-size: 0.7rem;">Opacity: <span id="copy-opacity-' + i + '">' + opacityVal + '</span></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][opacity]" min="0.1" max="1" step="0.05" value="' + opacityVal + '" oninput="document.getElementById(\'copy-opacity-' + i + '\').textContent=this.value;" style="font-size: 0.8rem;">';
        html += '</div>';
        html += '<div class="form-group" style="flex: 1;">';
        html += '<label style="font-size: 0.7rem;">Rotation (°): <span id="copy-rotation-' + i + '">' + rotationVal + '</span></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][rotation]" min="-90" max="90" step="5" value="' + rotationVal + '" oninput="document.getElementById(\'copy-rotation-' + i + '\').textContent=this.value;" style="font-size: 0.8rem;">';
        html += '</div>';
        html += '</div>';

        html += '</div>';
    }
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', initPerCopyWatermark);
</script>

{{-- Profile List --}}
<div class="card">
    <div class="card-header"><h2>Active Queues ({{ $profiles->count() }})</h2></div>
    <table role="table">
        <caption class="sr-only">Active print queues</caption>
        <thead>
            <tr>
                <th scope="col">Queue Name</th>
                <th scope="col">Branch</th>
                <th scope="col">Description</th>
                <th scope="col">Connected Agent</th>
                <th scope="col">Printer Name</th>
                <th scope="col">Paper</th>
                <th scope="col">Orient.</th>
                <th scope="col">Scaling</th>
                <th scope="col">Actions</th>
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
                        <a href="{{ route('admin.profiles.clone', $profile) }}" class="btn btn-secondary btn-sm" style="text-decoration: none;" title="Clone this queue">
                            Clone
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
            <tr><td colspan="9">
                <x-empty-state icon="📄" title="No profiles created yet" description="Create your first print queue above to define paper sizes and printer options." actionText="+ Create Queue" :actionUrl="'#'" />
            </td></tr>
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
