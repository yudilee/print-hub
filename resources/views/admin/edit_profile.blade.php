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

        {{-- Watermark Configuration --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                💧 Watermark
            </legend>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">Overlay a watermark on printed documents. Leave blank to disable.</p>

            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label for="watermark_text">Watermark Text</label>
                    <input type="text" name="watermark_text" id="watermark_text" value="{{ old('watermark_text', $profile->watermark_text) }}" placeholder="e.g. CONFIDENTIAL, DRAFT, COPY" oninput="updateWatermarkPreview()">
                </div>
                <div class="form-group" style="flex: 1;">
                    <label for="watermark_position">Position</label>
                    <select name="watermark_position" id="watermark_position" onchange="updateWatermarkPreview()">
                        <option value="center" {{ old('watermark_position', $profile->watermark_position ?? 'center') == 'center' ? 'selected' : '' }}>Center</option>
                        <option value="tile" {{ old('watermark_position', $profile->watermark_position) == 'tile' ? 'selected' : '' }}>Tile (Repeating)</option>
                        <option value="top-left" {{ old('watermark_position', $profile->watermark_position) == 'top-left' ? 'selected' : '' }}>Top Left</option>
                        <option value="top-right" {{ old('watermark_position', $profile->watermark_position) == 'top-right' ? 'selected' : '' }}>Top Right</option>
                        <option value="bottom-left" {{ old('watermark_position', $profile->watermark_position) == 'bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                        <option value="bottom-right" {{ old('watermark_position', $profile->watermark_position) == 'bottom-right' ? 'selected' : '' }}>Bottom Right</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="watermark_opacity">Opacity: <span id="opacity-value">{{ old('watermark_opacity', $profile->watermark_opacity ?? 0.3) }}</span></label>
                    <input type="range" name="watermark_opacity" id="watermark_opacity" min="0.1" max="1" step="0.05" value="{{ old('watermark_opacity', $profile->watermark_opacity ?? 0.3) }}" oninput="document.getElementById('opacity-value').textContent=this.value; updateWatermarkPreview();">
                </div>
                <div class="form-group">
                    <label for="watermark_rotation">Rotation (°): <span id="rotation-value">{{ old('watermark_rotation', $profile->watermark_rotation ?? -45) }}</span></label>
                    <input type="range" name="watermark_rotation" id="watermark_rotation" min="-90" max="90" step="5" value="{{ old('watermark_rotation', $profile->watermark_rotation ?? -45) }}" oninput="document.getElementById('rotation-value').textContent=this.value; updateWatermarkPreview();">
                </div>
            </div>

            <div id="watermark-preview" style="margin-top: 0.75rem; padding: 1rem; background: var(--bg); border-radius: 6px; border: 1px solid var(--border); min-height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                <span style="color: var(--text-muted); font-size: 0.8rem;">Preview will appear here</span>
            </div>
        </fieldset>

        {{-- Finishing Options --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: var(--primary); padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                🔧 Finishing Options
            </legend>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">Printer finishing features — these are passed to the print driver and may not be supported by all printers.</p>

            <div class="form-row">
                <div class="form-group">
                    <label for="finishing_staple">Stapling</label>
                    <select name="finishing_staple" id="finishing_staple">
                        <option value="" {{ old('finishing_staple', $profile->finishing_staple) == '' ? 'selected' : '' }}>None</option>
                        <option value="single" {{ old('finishing_staple', $profile->finishing_staple) == 'single' ? 'selected' : '' }}>Single Staple</option>
                        <option value="dual" {{ old('finishing_staple', $profile->finishing_staple) == 'dual' ? 'selected' : '' }}>Dual Staple</option>
                        <option value="saddle" {{ old('finishing_staple', $profile->finishing_staple) == 'saddle' ? 'selected' : '' }}>Saddle Stitch</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="finishing_punch">Hole Punch</label>
                    <select name="finishing_punch" id="finishing_punch">
                        <option value="" {{ old('finishing_punch', $profile->finishing_punch) == '' ? 'selected' : '' }}>None</option>
                        <option value="2" {{ old('finishing_punch', $profile->finishing_punch) == '2' ? 'selected' : '' }}>2 Holes</option>
                        <option value="4" {{ old('finishing_punch', $profile->finishing_punch) == '4' ? 'selected' : '' }}>4 Holes</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="finishing_fold">Folding</label>
                    <select name="finishing_fold" id="finishing_fold">
                        <option value="" {{ old('finishing_fold', $profile->finishing_fold) == '' ? 'selected' : '' }}>None</option>
                        <option value="half" {{ old('finishing_fold', $profile->finishing_fold) == 'half' ? 'selected' : '' }}>Half Fold</option>
                        <option value="tri-fold" {{ old('finishing_fold', $profile->finishing_fold) == 'tri-fold' ? 'selected' : '' }}>Tri-Fold</option>
                        <option value="z-fold" {{ old('finishing_fold', $profile->finishing_fold) == 'z-fold' ? 'selected' : '' }}>Z-Fold</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="finishing_bind">Binding</label>
                    <select name="finishing_bind" id="finishing_bind">
                        <option value="" {{ old('finishing_bind', $profile->finishing_bind) == '' ? 'selected' : '' }}>None</option>
                        <option value="tape" {{ old('finishing_bind', $profile->finishing_bind) == 'tape' ? 'selected' : '' }}>Tape Binding</option>
                        <option value="comb" {{ old('finishing_bind', $profile->finishing_bind) == 'comb' ? 'selected' : '' }}>Comb Binding</option>
                        <option value="thermal" {{ old('finishing_bind', $profile->finishing_bind) == 'thermal' ? 'selected' : '' }}>Thermal Binding</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="finishing_booklet" value="1" {{ old('finishing_booklet', $profile->finishing_booklet ?? false) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Booklet Mode (2-up, reverse stack)
                    </label>
                </div>
            </div>
        </fieldset>

        {{-- Eco Mode / Sustainability --}}
        <fieldset style="border: 1px solid var(--border); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; border-color: #22c55e;">
            <legend style="font-size: 0.85rem; font-weight: 700; color: #16a34a; padding: 0 0.5rem; display: flex; align-items: center; gap: 0.4rem;">
                🌱 Eco Mode — Green Printing
            </legend>
            <p style="color: var(--text-muted); font-size: 0.8rem; margin-bottom: 1rem;">Reduce paper, toner, and energy consumption with sustainable print settings.</p>

            <div class="form-row">
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.95rem; font-weight: 600;">
                        <input type="checkbox" name="eco_mode" value="1" {{ old('eco_mode', $profile->eco_mode ?? false) ? 'checked' : '' }} style="width: 20px; height: 20px;">
                        Enable Eco Mode 🌿
                    </label>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="grayscale_force" value="1" {{ old('grayscale_force', $profile->grayscale_force ?? false) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Force Grayscale (B&W)
                    </label>
                </div>
                <div class="form-group" style="display: flex; align-items: flex-end; padding-bottom: 0.5rem;">
                    <label class="checkbox-container" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="remove_images" value="1" {{ old('remove_images', $profile->remove_images ?? false) ? 'checked' : '' }} style="width: 18px; height: 18px;">
                        Remove Images
                    </label>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="pages_per_sheet">Pages per Sheet (N-up)</label>
                    <select name="pages_per_sheet" id="pages_per_sheet">
                        <option value="1" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '1' ? 'selected' : '' }}>1-up (Standard)</option>
                        <option value="2" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '2' ? 'selected' : '' }}>2-up (2 pages per sheet)</option>
                        <option value="4" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '4' ? 'selected' : '' }}>4-up (4 pages per sheet)</option>
                        <option value="6" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '6' ? 'selected' : '' }}>6-up (6 pages per sheet)</option>
                        <option value="8" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '8' ? 'selected' : '' }}>8-up (8 pages per sheet)</option>
                        <option value="9" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '9' ? 'selected' : '' }}>9-up (9 pages per sheet)</option>
                        <option value="16" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '16' ? 'selected' : '' }}>16-up (16 pages per sheet)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duplex">Suggested Duplex (when Eco Mode is on)</label>
                    <select name="duplex" id="duplex" {{ old('eco_mode', $profile->eco_mode ?? false) ? '' : '' }}>
                        <option value="one-sided" {{ old('duplex', $profile->duplex) == 'one-sided' ? 'selected' : '' }}>One-sided</option>
                        <option value="two-sided-long" {{ old('duplex', $profile->duplex) == 'two-sided-long' ? 'selected' : '' }}>Two-sided (Long edge)</option>
                        <option value="two-sided-short" {{ old('duplex', $profile->duplex) == 'two-sided-short' ? 'selected' : '' }}>Two-sided (Short edge)</option>
                    </select>
                </div>
            </div>

            <div class="form-row" style="background: rgba(34,197,94,0.05); padding: 0.75rem 1rem; border-radius: 8px;">
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <div>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">Pages Saved (Duplex)</span>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #16a34a;">{{ number_format($profile->duplex_saved ?? 0) }}</div>
                    </div>
                    <div>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">CO₂ Saved</span>
                        <div style="font-weight: 700; font-size: 1.1rem; color: #16a34a;">{{ number_format($profile->carbon_saved ?? 0, 2) }} g</div>
                    </div>
                    <div style="display: flex; align-items: center;">
                        <span style="font-size: 0.75rem; color: var(--text-muted);">🌿 Every page saved = ~5g CO₂ reduction</span>
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

function updateWatermarkPreview() {
    const text = document.getElementById('watermark_text').value;
    const position = document.getElementById('watermark_position').value;
    const opacity = document.getElementById('watermark_opacity').value;
    const rotation = document.getElementById('watermark_rotation').value;
    const preview = document.getElementById('watermark-preview');

    if (!text) {
        preview.innerHTML = '<span style="color: var(--text-muted); font-size: 0.8rem;">Preview will appear here</span>';
        return;
    }

    const alpha = Math.min(1, Math.max(0.1, parseFloat(opacity) || 0.3));
    const rot = parseInt(rotation) || -45;
    const fontSize = position === 'tile' ? 10 : 24;

    let html = '';
    if (position === 'tile') {
        // Create a tiled grid preview
        const rows = 4;
        const cols = 5;
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                html += `<span style="display: inline-block; padding: 4px 8px; font-size: ${fontSize}px; font-weight: bold; color: rgba(0,0,0,${alpha}); transform: rotate(${rot}deg); white-space: nowrap;">${escapeHtml(text)}</span>`;
            }
            html += '<br>';
        }
    } else {
        html = `<span style="font-size: ${fontSize}px; font-weight: bold; color: rgba(0,0,0,${alpha}); transform: rotate(${rot}deg); display: inline-block;">${escapeHtml(text)}</span>`;
    }

    preview.innerHTML = html;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
</script>
@endsection
