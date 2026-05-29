@extends('admin.layout')
@section('title', 'Print Queues')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Queues']]" />

<style>
    /* Premium Modern Styles for Print Queues Page */
    .premium-header {
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    .premium-title {
        font-size: 1.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, var(--primary), #a855f7);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
    }

    /* Cohesive Two-Column Form Layout */
    .premium-form-grid {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 0.85rem;
        align-items: start;
        margin-bottom: 1.25rem;
    }

    @media (max-width: 1024px) {
        .premium-form-grid {
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }
    }

    /* Sleek compact form panels */
    .premium-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        padding: 0.85rem;
        position: relative;
        overflow: hidden;
    }

    .premium-panel::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(to right, var(--primary), #a855f7);
    }

    .panel-section {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 0.75rem;
        margin-bottom: 0.75rem;
        transition: border-color 0.25s;
    }

    .panel-section:last-of-type {
        margin-bottom: 0;
    }

    .panel-section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 0.6rem 0;
        display: flex;
        align-items: center;
        gap: 6px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 0.4rem;
    }

    /* High contrast compact input styling */
    .compact-group {
        margin-bottom: 0.65rem;
    }
    
    .compact-group:last-child {
        margin-bottom: 0;
    }

    .compact-group label {
        display: block;
        font-size: 0.76rem;
        font-weight: 700;
        color: var(--text) !important;
        margin-bottom: 0.25rem;
    }

    .compact-group input[type="text"],
    .compact-group input[type="number"],
    .compact-group select {
        width: 100%;
        background: var(--surface);
        color: var(--text);
        border: 1px solid var(--border);
        padding: 0.4rem 0.55rem;
        font-size: 0.8rem;
        font-weight: 500;
        border-radius: 4px;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
    }

    .compact-group input[type="text"]:focus,
    .compact-group input[type="number"]:focus,
    .compact-group select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
        outline: none;
    }

    .compact-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.6rem;
    }

    /* Custom scrollbars and styling for sliders */
    input[type="range"] {
        accent-color: var(--primary);
    }

    /* Expandable blocks style */
    .premium-expandable {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 5px;
        padding: 0.45rem 0.6rem;
        margin-top: 0.5rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--text);
        user-select: none;
        transition: background 0.15s;
    }
    
    .premium-expandable:hover {
        background: var(--surface-hover);
    }

    .premium-expandable-content {
        padding: 0.6rem;
        border: 1px solid var(--border);
        border-top: none;
        border-radius: 0 0 5px 5px;
        background: rgba(0, 0, 0, 0.08);
    }

    /* Table tweaks */
    .premium-table th {
        font-weight: 700 !important;
        color: var(--text) !important;
        font-size: 0.74rem !important;
    }

    .premium-table td {
        font-size: 0.8rem;
        padding: 0.65rem 0.85rem !important;
    }
</style>

<div class="premium-header">
    <div>
        <h1 class="premium-title">🖨️ Print Queues</h1>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.15rem 0 0 0;">Define print profiles and route them to physical agents across branches.</p>
    </div>
    <a href="#active-queues-list" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 0.35rem 0.75rem; font-size: 0.72rem;">
        📋 View Queues ({{ $profiles->count() }})
    </a>
</div>

{{-- Create Profile Card --}}
<div class="premium-panel" style="margin-bottom: 1rem;">
    <div style="border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; margin: 0;">
            ✨ Create New Queue
        </h2>
        <span class="badge badge-info" style="font-size: 0.65rem; padding: 2px 6px;">Configure Rules & Routing</span>
    </div>

    <form action="{{ route('admin.profiles.store') }}" method="POST">
        @csrf
        
        {{-- Hidden cloned_from field for audit --}}
        @if(isset($clonedFrom))
            <input type="hidden" name="cloned_from" value="{{ $clonedFrom }}">
        @endif
        
        @if($errors->any())
            <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--danger); padding: 0.6rem; border-radius: 6px; margin-bottom: 0.75rem; font-size: 0.78rem;">
                <strong style="display: block; margin-bottom: 0.2rem;">⚠️ Please correct the errors:</strong>
                <ul style="margin: 0; padding-left: 1rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="premium-form-grid">
            
            {{-- Left Column: Core Identity & Device Assignment --}}
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                
                {{-- Block 1: Identity & Branch scoping --}}
                <div class="panel-section">
                    <h3 class="panel-section-title" style="color: var(--primary);">
                        📋 1. Basic Configuration
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-group">
                            <label for="name">Queue Identifier (slug) <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="name" id="name" required placeholder="e.g. invoice_sewa" value="{{ isset($clonedProfile) ? $clonedProfile->name . '_copy' : old('name') }}">
                            <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; display: block;">Lower case, underscores only, no spaces.</span>
                        </div>
                        <div class="compact-row">
                            <div class="compact-group">
                                <label for="description">Display Name</label>
                                <input type="text" name="description" id="description" placeholder="e.g. Rental Invoice A4" value="{{ isset($clonedProfile) ? $clonedProfile->description : old('description') }}">
                            </div>
                            <div class="compact-group">
                                <label for="branch_id">Branch Scoping <span style="color: var(--danger);">*</span></label>
                                <select name="branch_id" id="branch_id" required>
                                    <option value="">-- Select Branch --</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (isset($clonedProfile) && $clonedProfile->branch_id == $branch->id) || old('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->company->code }} / {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Block 2: Physical Device Assignment --}}
                <div class="panel-section" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.02);">
                    <h3 class="panel-section-title" style="color: var(--warning);">
                        🖥️ 2. Physical Assignment & Routing
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-group">
                            <label for="print_agent_id">Target Workstation / Agent <span style="color: var(--danger);">*</span></label>
                            <select name="print_agent_id" id="print_agent_id" required onchange="updatePrinterDropdown(this.value); updateAdvancedOptions(this.value);">
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
                            <div id="agent-capability-summary" style="font-size: 0.68rem; color: var(--text-muted); margin-top: 3px; min-height: 12px; line-height: 1.2;"></div>
                        </div>
                        <div class="compact-group">
                            <label for="default_printer">Target Printer Device <span style="color: var(--danger);">*</span></label>
                            <div id="printer_input_container">
                                <input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D" value="{{ isset($clonedProfile) ? $clonedProfile->default_printer : old('default_printer') }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Media, Specs, Watermarks --}}
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                
                {{-- Block 3: Media Standard & Orientation --}}
                <div class="panel-section">
                    <h3 class="panel-section-title" style="color: var(--primary);">
                        📐 3. Media, Layout & Margins
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-row">
                            <div class="compact-group" style="grid-column: span 1;">
                                <label for="paper_size">Paper Standard</label>
                                <select name="paper_size" id="paper_size" onchange="toggleCustomSize(this.value)">
                                    <option value="A4" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'A4') || old('paper_size') == 'A4' ? 'selected' : '' }}>A4 (210×297mm)</option>
                                    <option value="A5" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'A5') || old('paper_size') == 'A5' ? 'selected' : '' }}>A5 (148×210mm)</option>
                                    <option value="Letter" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Letter') || old('paper_size') == 'Letter' ? 'selected' : '' }}>Letter (8.5" x 11")</option>
                                    <option value="Half Letter" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Half Letter') || old('paper_size') == 'Half Letter' ? 'selected' : '' }}>Half Letter (8.5" x 5.5")</option>
                                    <option value="Legal" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'Legal') || old('paper_size') == 'Legal' ? 'selected' : '' }}>Legal (8.5" x 14")</option>
                                    <option value="F4" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'F4') || old('paper_size') == 'F4' ? 'selected' : '' }}>F4 / Folio (215×330mm)</option>
                                    <option value="CUSTOM" {{ (isset($clonedProfile) && $clonedProfile->paper_size == 'CUSTOM') || old('paper_size') == 'CUSTOM' ? 'selected' : '' }}>-- Custom Size --</option>
                                </select>
                            </div>
                            <div class="compact-group">
                                <label for="orientation">Orientation</label>
                                <select name="orientation" id="orientation">
                                    <option value="portrait" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'portrait') || old('orientation') == 'portrait' ? 'selected' : '' }}>Portrait</option>
                                    <option value="landscape" {{ (isset($clonedProfile) && $clonedProfile->orientation == 'landscape') || old('orientation') == 'landscape' ? 'selected' : '' }}>Landscape</option>
                                </select>
                            </div>
                        </div>

                        {{-- Custom Dimensions block --}}
                        <div id="custom-dims" style="display: none; gap: 8px; margin-top: 0; align-items: flex-end;">
                            <div class="compact-group" style="flex: 1; margin-bottom: 0;">
                                <label id="width-label" style="font-size: 0.68rem; color: var(--text-muted);">Width (mm)</label>
                                <input type="number" name="custom_width" step="0.001" placeholder="e.g. 210" value="{{ isset($clonedProfile) ? $clonedProfile->custom_width : old('custom_width') }}">
                            </div>
                            <div class="compact-group" style="flex: 1; margin-bottom: 0;">
                                <label id="height-label" style="font-size: 0.68rem; color: var(--text-muted);">Height (mm)</label>
                                <input type="number" name="custom_height" step="0.001" placeholder="e.g. 297" value="{{ isset($clonedProfile) ? $clonedProfile->custom_height : old('custom_height') }}">
                            </div>
                            <div class="compact-group" style="padding-bottom: 0.35rem; margin-bottom: 0;">
                                <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; color: var(--text-muted); user-select: none; margin-bottom: 0;">
                                    <input type="checkbox" name="use_inches" id="use_inches" value="1" onchange="toggleUnit(this.checked)" {{ old('use_inches') ? 'checked' : '' }}> Inches
                                </label>
                            </div>
                        </div>

                        {{-- Compact Margins Collapse Header --}}
                        <div class="expandable premium-expandable">
                            <span>📐 Margins & Offsets (mm)</span>
                            <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                        </div>
                        <div class="expandable-content premium-expandable-content">
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.4rem; margin-bottom: 0.5rem;">
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Top</label>
                                    <input type="number" name="margin_top" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_top : (old('margin_top') ?? '0') }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Bottom</label>
                                    <input type="number" name="margin_bottom" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_bottom : (old('margin_bottom') ?? '0') }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Left</label>
                                    <input type="number" name="margin_left" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_left : (old('margin_left') ?? '0') }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Right</label>
                                    <input type="number" name="margin_right" step="0.01" value="{{ isset($clonedProfile) ? $clonedProfile->margin_right : (old('margin_right') ?? '0') }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="applyDotMatrixDefaults()" style="font-size: 0.68rem; padding: 2px 6px; width: 100%; justify-content: center;">
                                📝 Set Dot-Matrix Defaults (4.23mm)
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Block 4: Finishing & Advanced --}}
                <div class="panel-section">
                    <div class="expandable premium-expandable" style="margin-top: 0;">
                        <span>⚙️ 4. Advanced Hardware Options</span>
                        <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                    </div>
                    <div class="expandable-content premium-expandable-content">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="tray_source" style="font-size: 0.68rem;">Paper Feed</label>
                                <select name="tray_source" id="tray_source" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="">Auto / Default</option>
                                    <option value="AutoSelect" {{ isset($clonedProfile) && $clonedProfile->tray_source == 'AutoSelect' ? 'selected' : '' }}>Auto Select</option>
                                    <option value="Tray1" {{ isset($clonedProfile) && $clonedProfile->tray_source == 'Tray1' ? 'selected' : '' }}>Tray 1</option>
                                    <option value="Tray2" {{ isset($clonedProfile) && $clonedProfile->tray_source == 'Tray2' ? 'selected' : '' }}>Tray 2</option>
                                    <option value="Tray3" {{ isset($clonedProfile) && $clonedProfile->tray_source == 'Tray3' ? 'selected' : '' }}>Tray 3</option>
                                    <option value="ManualFeed" {{ isset($clonedProfile) && $clonedProfile->tray_source == 'ManualFeed' ? 'selected' : '' }}>Manual Feed</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="color_mode" style="font-size: 0.68rem;">Color Mode</label>
                                <select name="color_mode" id="color_mode" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="color" {{ isset($clonedProfile) && $clonedProfile->color_mode == 'color' ? 'selected' : '' }}>Color</option>
                                    <option value="monochrome" {{ isset($clonedProfile) && $clonedProfile->color_mode == 'monochrome' ? 'selected' : '' }}>Mono (B&W)</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="print_quality" style="font-size: 0.68rem;">Quality</label>
                                <select name="print_quality" id="print_quality" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="normal" {{ isset($clonedProfile) && $clonedProfile->print_quality == 'normal' ? 'selected' : '' }}>Normal (600)</option>
                                    <option value="draft" {{ isset($clonedProfile) && $clonedProfile->print_quality == 'draft' ? 'selected' : '' }}>Draft (300)</option>
                                    <option value="high" {{ isset($clonedProfile) && $clonedProfile->print_quality == 'high' ? 'selected' : '' }}>High (1200)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group" style="display: flex; align-items: center; gap: 6px;">
                                <label for="copies" style="margin-bottom: 0; white-space: nowrap;">Copies:</label>
                                <input type="number" name="copies" id="copies" value="{{ isset($clonedProfile) ? $clonedProfile->copies : (old('copies') ?? '1') }}" min="1" max="99" style="width: 65px; padding: 0.3rem; font-size: 0.76rem;">
                            </div>
                            <div class="compact-group" style="display: flex; align-items: center; gap: 6px;">
                                <label for="duplex" style="margin-bottom: 0; white-space: nowrap;">Duplex:</label>
                                <select name="duplex" id="duplex" style="padding: 0.3rem; font-size: 0.76rem; flex: 1;">
                                    <option value="one-sided" {{ isset($clonedProfile) && $clonedProfile->duplex == 'one-sided' ? 'selected' : '' }}>One-sided</option>
                                    <option value="two-sided-long" {{ isset($clonedProfile) && $clonedProfile->duplex == 'two-sided-long' ? 'selected' : '' }}>2-Sided Long</option>
                                    <option value="two-sided-short" {{ isset($clonedProfile) && $clonedProfile->duplex == 'two-sided-short' ? 'selected' : '' }}>2-Sided Short</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; padding-top: 0.4rem; border-top: 1px dashed var(--border);">
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; color: var(--primary); font-size: 0.72rem; user-select: none;">
                                <input type="checkbox" name="fit_to_page" value="1" style="width: 14px; height: 14px;" {{ (isset($clonedProfile) && ($clonedProfile->extra_options['fit_to_page'] ?? false)) || old('fit_to_page') ? 'checked' : '' }}>
                                Scale to Fit
                            </label>
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; user-select: none; color: var(--text);">
                                <input type="checkbox" name="collate" value="1" style="width: 14px; height: 14px;" {{ !isset($clonedProfile) || ($clonedProfile->collate ?? true) ? 'checked' : '' }}>
                                Collate
                            </label>
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; user-select: none; color: var(--text);">
                                <input type="checkbox" name="reverse_order" value="1" style="width: 14px; height: 14px;" {{ isset($clonedProfile) && $clonedProfile->reverse_order ? 'checked' : '' }}>
                                Reverse Order
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Block 5: Watermarks --}}
                <div class="panel-section">
                    <div class="expandable premium-expandable" style="margin-top: 0;">
                        <span>💧 5. Document Watermarks</span>
                        <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                    </div>
                    <div class="expandable-content premium-expandable-content">
                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group">
                                <label for="watermark_text">Overlay Text</label>
                                <input type="text" name="watermark_text" id="watermark_text" placeholder="e.g. COPY, CONFIDENTIAL" value="{{ isset($clonedProfile) ? $clonedProfile->watermark_text : old('watermark_text') }}" style="padding: 0.35rem; font-size: 0.76rem;">
                            </div>
                            <div class="compact-group">
                                <label for="watermark_position">Position</label>
                                <select name="watermark_position" id="watermark_position" style="padding: 0.35rem; font-size: 0.76rem;">
                                    <option value="center" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'center' ? 'selected' : '' }}>Center</option>
                                    <option value="tile" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'tile' ? 'selected' : '' }}>Tile (Repeat)</option>
                                    <option value="top-left" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'top-left' ? 'selected' : '' }}>Top Left</option>
                                    <option value="top-right" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'top-right' ? 'selected' : '' }}>Top Right</option>
                                    <option value="bottom-left" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                                    <option value="bottom-right" {{ isset($clonedProfile) && $clonedProfile->watermark_position == 'bottom-right' ? 'selected' : '' }}>Bottom Right</option>
                                </select>
                            </div>
                        </div>

                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group" style="background: var(--surface); padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border);">
                                <label for="watermark_opacity" style="display: flex; justify-content: space-between; align-items: center; font-size:0.68rem; margin-bottom:2px;">
                                    <span>Opacity</span>
                                    <strong id="opacity-value" style="color: var(--primary);">{{ isset($clonedProfile) ? $clonedProfile->watermark_opacity : '0.3' }}</strong>
                                </label>
                                <input type="range" name="watermark_opacity" id="watermark_opacity" min="0.1" max="1" step="0.05" value="{{ isset($clonedProfile) ? $clonedProfile->watermark_opacity : '0.3' }}" oninput="document.getElementById('opacity-value').textContent=this.value;" style="cursor: pointer; height: 3px; width: 100%;">
                            </div>
                            <div class="compact-group" style="background: var(--surface); padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border);">
                                <label for="watermark_rotation" style="display: flex; justify-content: space-between; align-items: center; font-size:0.68rem; margin-bottom:2px;">
                                    <span>Angle</span>
                                    <strong id="rotation-value" style="color: var(--primary);">{{ isset($clonedProfile) ? $clonedProfile->watermark_rotation : '-45' }}°</strong>
                                </label>
                                <input type="range" name="watermark_rotation" id="watermark_rotation" min="-90" max="90" step="5" value="{{ isset($clonedProfile) ? $clonedProfile->watermark_rotation : '-45' }}" oninput="document.getElementById('rotation-value').textContent=this.value + '°';" style="cursor: pointer; height: 3px; width: 100%;">
                            </div>
                        </div>

                        {{-- Per-Copy Watermark Configs --}}
                        <div id="per-copy-watermark-section" style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); display: none;">
                            <div style="font-size: 0.74rem; font-weight: 700; color: var(--primary); margin-bottom: 0.35rem;">
                                📋 Custom Watermark Per Copy
                            </div>
                            <div id="copy-watermark-configs" style="display: flex; flex-direction: column; gap: 0.4rem;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 0.5rem; display: flex; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 0.6rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1.25rem; font-weight: 700; border-radius: 4px; font-size: 0.8rem; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2);">
                💾 Create Print Queue
            </button>
        </div>
    </form>
</div>

{{-- Profile List Card --}}
<div class="card" id="active-queues-list" style="border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.05); background: var(--surface); overflow: hidden; padding: 0;">
    <div style="padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02);">
        <h2 style="font-size: 0.9rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; margin: 0;">
            📋 Configured Print Queues ({{ $profiles->count() }})
        </h2>
        <span style="font-size: 0.72rem; color: var(--text-muted);">Active routing rules</span>
    </div>
    
    <div style="overflow-x: auto;">
        <table role="table" class="premium-table" style="min-width: 900px; width: 100%;">
            <caption class="sr-only">Active print queues</caption>
            <thead>
                <tr style="background: rgba(0,0,0,0.05);">
                    <th scope="col" style="padding: 0.6rem 0.85rem; width: 220px;">Queue Name</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem; width: 180px;">Branch Scoping</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem;">Workstation / Agent</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem;">Printer / Device</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem; text-align: center; width: 100px;">Paper Size</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem; text-align: center; width: 100px;">Orient.</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem; text-align: center; width: 100px;">Scaling</th>
                    <th scope="col" style="padding: 0.6rem 0.85rem; text-align: right; width: 220px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($profiles as $profile)
                <tr style="transition: all 0.2s;">
                    <td style="vertical-align: middle;">
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <strong class="mono" style="color: var(--primary-hover); font-size: 0.8rem; font-weight: 700;">{{ $profile->name }}</strong>
                            @if($profile->description)
                                <span style="font-size: 0.72rem; color: var(--text-muted);">{{ $profile->description }}</span>
                            @endif
                        </div>
                    </td>
                    <td style="vertical-align: middle;">
                        @if($profile->branch)
                            <div style="display: flex; align-items: center; gap: 4px;">
                                <span class="badge badge-info" style="font-size: 0.6rem; padding: 1px 4px;">{{ $profile->branch->company->code ?? '' }}</span>
                                <span style="font-size: 0.76rem; font-weight: 600;">{{ $profile->branch->name }}</span>
                            </div>
                        @else
                            <span class="badge badge-warning" style="font-size: 0.6rem; padding: 1px 4px;">Unassigned</span>
                        @endif
                    </td>
                    <td style="vertical-align: middle;">
                        @if($profile->agent)
                            <div style="display: inline-flex; align-items: center; gap: 6px; background: var(--bg); padding: 2px 8px; border-radius: 12px; border: 1px solid var(--border);">
                                <span class="dot {{ $profile->agent->isOnline() ? 'dot-green' : 'dot-red' }}" style="margin-right: 0; width: 6px; height: 6px;"></span>
                                <span style="font-size: 0.76rem; font-weight: 600; color: var(--text);">{{ $profile->agent->name }}</span>
                            </div>
                        @else
                            <span style="color: var(--text-muted); font-style: italic; font-size: 0.74rem;">Generic Pool</span>
                        @endif
                    </td>
                    <td style="vertical-align: middle;">
                        @if($profile->default_printer)
                            <code class="mono" style="font-size: 0.72rem; background: var(--bg); border: 1px solid var(--border); padding: 1px 4px; border-radius: 3px; color: var(--text);">{{ $profile->default_printer }}</code>
                        @else
                            <span style="color: var(--text-muted); font-style: italic; font-size: 0.74rem;">OS Default</span>
                        @endif
                    </td>
                    <td style="vertical-align: middle; text-align: center;">
                        <span class="badge badge-info" style="font-size: 0.65rem; padding: 1px 6px; font-weight: 600;">{{ $profile->paper_size }}</span>
                    </td>
                    <td style="vertical-align: middle; text-align: center; font-size: 0.76rem; color: var(--text-muted);">
                        {{ ucfirst($profile->orientation) }}
                    </td>
                    <td style="vertical-align: middle; text-align: center; font-size: 0.76rem;">
                        @if($profile->extra_options['fit_to_page'] ?? false)
                            <span style="color: var(--success); font-weight: 600;">Fit</span>
                        @else
                            <span style="color: var(--text-muted);">100%</span>
                        @endif
                    </td>
                    <td style="vertical-align: middle; text-align: right;">
                        <div style="display: inline-flex; gap: 4px;">
                            <a href="{{ route('admin.profiles.edit', $profile) }}" class="btn btn-secondary btn-sm" style="text-decoration: none; padding: 3px 6px; font-size: 0.7rem; border-radius: 3px;" title="Edit config">
                                Edit
                            </a>
                            <a href="{{ route('admin.profiles.clone', $profile) }}" class="btn btn-secondary btn-sm" style="text-decoration: none; padding: 3px 6px; font-size: 0.7rem; border-radius: 3px;" title="Clone this queue">
                                Clone
                            </a>
                            <button class="btn btn-secondary btn-sm" onclick="openTestModal('{{ $profile->id }}', '{{ $profile->name }}', '{{ $profile->agent->name ?? 'Any Online Agent' }}', '{{ $profile->default_printer ?: 'Default' }}')" style="padding: 3px 6px; font-size: 0.7rem; border-radius: 3px;" title="Test print job">
                                Test
                            </button>
                            <form action="{{ route('admin.profiles.destroy', $profile) }}" method="POST" onsubmit="return confirm('Delete this queue?')" style="display: inline;">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" style="padding: 3px 6px; font-size: 0.7rem; border-radius: 3px;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="padding: 2rem 1.25rem; text-align: center;">
                        <x-empty-state icon="📄" title="No profiles created yet" description="Create your first print queue above." actionText="+ Create Queue" :actionUrl="'#'" />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Test Print Modal --}}
<div id="test-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 1000; align-items: center; justify-content: center; transition: all 0.2s ease;">
    <div class="card" style="width: 400px; padding: 1.25rem; border-radius: 8px; border: 1px solid var(--border); box-shadow: 0 10px 25px rgba(0,0,0,0.3); background: var(--surface); position: relative;">
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--primary);"></div>
        <div class="card-header" style="margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
            <h2 id="modal-title" style="font-size: 0.95rem; font-weight: 700; color: var(--text); margin:0;">Test Queue</h2>
            <button type="button" onclick="closeTestModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.15rem; padding: 0;">&times;</button>
        </div>
        <p style="font-size: 0.76rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.45;">
            Transmit a PDF test document directly to this queue: <br>
            <strong id="modal-target-info" style="color: var(--primary-hover); display: block; margin-top: 4px;">Agent: ?, Printer: ?</strong>
        </p>
        <form id="test-print-form" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="form-group" style="background: rgba(0,0,0,0.1); padding: 0.85rem; border-radius: 6px; border: 1px dashed var(--border); margin-bottom: 1rem;">
                <label style="font-weight: 700; margin-bottom: 0.35rem; display: block; font-size: 0.72rem; color: var(--text);">Select Test PDF File</label>
                <input type="file" name="file" accept="application/pdf" required style="cursor: pointer; font-size: 0.74rem; padding: 0.15rem 0; color: var(--text);">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 6px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeTestModal()">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 700;">🚀 Transmit Test</button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic data injected from PHP backend
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
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" placeholder="e.g. Brother-HL-L2360D" style="background: var(--surface); color: var(--text); border: 1px solid var(--border); padding: 0.45rem 0.6rem; font-size: 0.82rem; border-radius: 4px;">`;
        return;
    }

    let html = `<select name="default_printer" id="default_printer" style="background: var(--surface); color: var(--text); border: 1px solid var(--border); padding: 0.45rem 0.6rem; font-size: 0.82rem; border-radius: 4px; cursor: pointer; width: 100%;">`;
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
        summaryEl.innerHTML = '<span style="color: var(--text-muted); font-size:0.68rem;">No capabilities discovered. Displaying default options.</span>';
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

    // Build rich capability summary tag
    let summary = `<span style="color: var(--success); font-weight:700;">✓ Discovered ${printerCount} physical device(s)</span>`;
    if (allDuplexModes.size > 0) summary += ` · 🔁 Duplex`;
    if (allColorModes.has('color') && (allColorModes.has('gray') || allColorModes.has('monochrome'))) summary += ` · 🎨 Color + B&W`;
    else if (allColorModes.has('gray') || allColorModes.has('monochrome')) summary += ` · ⚫ B&W Only`;
    else if (allColorModes.has('color')) summary += ` · 🎨 Color Only`;
    if (allTrays.size > 0) summary += ` · 📦 ${allTrays.size} Trays`;
    summaryEl.innerHTML = summary;

    // ── Paper Size ──
    const PAPER_LABELS = {
        'A4': 'A4 (210×297mm)', 'A3': 'A3 (297×420mm)', 'A5': 'A5 (148×210mm)',
        'A6': 'A6 (105×148mm)', 'Letter': 'Letter (216×279mm)', 'Legal': 'Legal (216×356mm)',
        'Tabloid': 'Tabloid (279×432mm)', 'Executive': 'Executive (184×267mm)',
    };
    const sizeOptions = [{ value: '', label: 'Default (Printer Setting)' }];
    if (allPaperSizes.size > 0) {
        const sortedSizes = Array.from(allPaperSizes).sort();
        sortedSizes.forEach(s => {
            if (s && s !== '') {
                sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s });
            }
        });
    } else {
        const commonSizes = ['A4', 'A3', 'A5', 'Letter', 'Legal', 'Executive'];
        commonSizes.forEach(s => sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s }));
    }
    sizeOptions.push({ value: 'CUSTOM', label: 'Custom Size...' });
    resetSelectOptions('paper_size', sizeOptions);

    // ── Duplex ──
    const DUPLEX_MAP = {
        'None': { value: 'none', label: 'No Duplex (One-sided)' },
        'TwoSidedLong': { value: 'two-sided-long', label: 'Two-sided (Long Edge)' },
        'TwoSidedShort': { value: 'two-sided-short', label: 'Two-sided (Short Edge)' },
    };
    const duplexOptions = [{ value: '', label: 'Default' }];
    if (allDuplexModes.size > 0) {
        allDuplexModes.forEach(d => {
            if (DUPLEX_MAP[d]) duplexOptions.push(DUPLEX_MAP[d]);
        });
    }
    if (duplexOptions.length <= 1) {
        duplexOptions.push(
            { value: 'one-sided', label: 'One-sided' },
            { value: 'two-sided-long', label: 'Two-sided (Long Edge)' },
            { value: 'two-sided-short', label: 'Two-sided (Short Edge)' }
        );
    }
    resetSelectOptions('duplex', duplexOptions);

    // ── Tray Source ──
    const trayOptions = [{ value: '', label: 'Auto (Default)' }];
    allTrays.forEach(trayName => {
        if (trayName && trayName !== '') {
            trayOptions.push({ value: trayName, label: trayName });
        }
    });
    const commonTrays = ['AutoSelect', 'Tray1', 'Tray2', 'ManualFeed', 'Bypass Tray'];
    commonTrays.forEach(t => {
        if (!allTrays.has(t) && !trayOptions.some(o => o.value === t)) {
            trayOptions.push({ value: t, label: t });
        }
    });
    resetSelectOptions('tray_source', trayOptions);

    // ── Color Mode ──
    const colorOptions = [];
    if (allColorModes.has('color')) colorOptions.push({ value: 'color', label: 'Color' });
    if (allColorModes.has('gray') || allColorModes.has('monochrome'))
        colorOptions.push({ value: 'monochrome', label: 'Monochrome (B&W)' });
    if (colorOptions.length === 0) {
        colorOptions.push({ value: 'color', label: 'Color' }, { value: 'monochrome', label: 'Monochrome (B&W)' });
    }
    resetSelectOptions('color_mode', colorOptions);

    // ── Media Type ──
    const mediaOptions = [{ value: '', label: 'Plain Paper' }];
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
    if (options.some(o => o.value === currentVal)) {
        select.value = currentVal;
    }
}

function toggleCustomSize(val) {
    const dims = document.getElementById('custom-dims');
    if(dims) dims.style.display = (val === 'CUSTOM') ? 'flex' : 'none';
}

function toggleUnit(isInch) {
    const wLabel = document.getElementById('width-label');
    const hLabel = document.getElementById('height-label');
    if (wLabel) wLabel.innerText = isInch ? 'Width (Inch)' : 'Width (mm)';
    if (hLabel) hLabel.innerText = isInch ? 'Height (Inch)' : 'Height (mm)';
}

function applyDotMatrixDefaults() {
    const top = document.getElementsByName('margin_top')[0];
    const bottom = document.getElementsByName('margin_bottom')[0];
    const left = document.getElementsByName('margin_left')[0];
    const right = document.getElementsByName('margin_right')[0];
    if (top) top.value = 4.23;
    if (bottom) bottom.value = 4.23;
    if (left) left.value = 4.23;
    if (right) right.value = 4.23;
}

// ── Per-Copy Watermark Dynamic Form ──
function initPerCopyWatermark() {
    const copiesInput = document.getElementById('copies');
    if (!copiesInput) return;

    copiesInput.addEventListener('input', updateCopyWatermarkConfigs);
    copiesInput.addEventListener('change', updateCopyWatermarkConfigs);
    
    // Initial check for cloned copy details or edit details
    @if(isset($clonedProfile) && !empty($clonedProfile->watermark_copies))
        window._savedWatermarkCopies = {!! json_encode($clonedProfile->watermark_copies) !!};
    @endif
    
    updateCopyWatermarkConfigs();
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

    // Retrieve existing inputs in DOM to keep state
    const existingConfigs = [];
    for (let i = 0; i < 99; i++) {
        const textEl = document.querySelector(`[name="watermark_copies[${i}][text]"]`);
        if (!textEl) {
            if (window._savedWatermarkCopies && window._savedWatermarkCopies[i]) {
                existingConfigs[i] = window._savedWatermarkCopies[i];
            } else {
                break;
            }
        } else {
            existingConfigs[i] = {
                text: textEl.value,
                opacity: document.querySelector(`[name="watermark_copies[${i}][opacity]"]`)?.value || '0.3',
                rotation: document.querySelector(`[name="watermark_copies[${i}][rotation]"]`)?.value || '-45',
                position: document.querySelector(`[name="watermark_copies[${i}][position]"]`)?.value || 'center',
            };
        }
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

        html += '<div style="border: 1px solid var(--border); border-radius: 4px; padding: 0.5rem; background: var(--surface); display:flex; flex-direction:column; gap:0.35rem;">';
        html += '<div style="font-size: 0.72rem; font-weight: 700; color: var(--primary-hover); display:flex; justify-content:space-between; border-bottom:1px solid var(--border); padding-bottom:2px; margin-bottom:2px;">';
        html += '<span>📄 Copy ' + (i + 1) + ' Replica</span>';
        html += '</div>';

        html += '<div class="compact-row" style="grid-template-columns: 2fr 1fr; gap: 4px;">';
        html += '<div class="compact-group" style="margin-bottom:0;">';
        html += '<input type="text" name="watermark_copies[' + i + '][text]" value="' + textVal.replace(/"/g, '&quot;') + '" placeholder="e.g. Accounting Copy" style="font-size: 0.76rem; padding: 3px 6px; background:var(--bg); color:var(--text); border:1px solid var(--border); border-radius:3px; width:100%;">';
        html += '</div>';
        html += '<div class="compact-group" style="margin-bottom:0;">';
        html += '<select name="watermark_copies[' + i + '][position]" style="font-size: 0.76rem; padding: 3px 6px; background:var(--bg); color:var(--text); border:1px solid var(--border); border-radius:3px; cursor:pointer; width:100%;">';
        positionOptions.forEach(function(po) {
            const sel = po.value === positionVal ? ' selected' : '';
            html += '<option value="' + po.value + '"' + sel + '>' + po.label + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '</div>';

        html += '<div class="compact-row" style="grid-template-columns: 1fr 1fr; gap: 4px;">';
        html += '<div class="compact-group" style="margin-bottom:0; display:flex; align-items:center; gap:6px;">';
        html += '<label style="font-size: 0.65rem; color:var(--text-muted); margin-bottom:0; white-space:nowrap;">Op: <strong id="copy-opacity-' + i + '">' + opacityVal + '</strong></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][opacity]" min="0.1" max="1" step="0.05" value="' + opacityVal + '" oninput="document.getElementById(\'copy-opacity-\' + ' + i + ').textContent=this.value;" style="cursor:pointer; flex:1; height:3px;">';
        html += '</div>';
        html += '<div class="compact-group" style="margin-bottom:0; display:flex; align-items:center; gap:6px;">';
        html += '<label style="font-size: 0.65rem; color:var(--text-muted); margin-bottom:0; white-space:nowrap;">Rot: <strong id="copy-rotation-' + i + '">' + rotationVal + '°</strong></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][rotation]" min="-90" max="90" step="5" value="' + rotationVal + '" oninput="document.getElementById(\'copy-rotation-\' + ' + i + ').textContent=this.value+\'°\';" style="cursor:pointer; flex:1; height:3px;">';
        html += '</div>';
        html += '</div>';

        html += '</div>';
    }
    container.innerHTML = html;
}

// ── Test print modals ──
function openTestModal(id, name, agent, printer) {
    const modal = document.getElementById('test-modal');
    const form = document.getElementById('test-print-form');
    const title = document.getElementById('modal-title');
    const info = document.getElementById('modal-target-info');

    if (title) title.innerText = `Test Queue: ${name}`;
    if (info) info.innerHTML = `Workstation: <code>${agent}</code> | Device: <code>${printer}</code>`;
    if (form) form.action = `/profiles/${id}/test-print`;
    
    if (modal) {
        modal.style.display = 'flex';
        setTimeout(() => modal.style.opacity = '1', 50);
    }
}

function closeTestModal() {
    const modal = document.getElementById('test-modal');
    if (modal) {
        modal.style.opacity = '0';
        setTimeout(() => modal.style.display = 'none', 200);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const agentSelect = document.getElementById('print_agent_id');
    if (agentSelect && agentSelect.value) {
        updatePrinterDropdown(agentSelect.value);
        updateAdvancedOptions(agentSelect.value);
        
        @if(isset($clonedProfile))
            setTimeout(() => {
                const printerSelect = document.getElementById('default_printer');
                if (printerSelect && printerSelect.tagName === 'SELECT') {
                    printerSelect.value = "{{ $clonedProfile->default_printer }}";
                }
            }, 100);
        @endif
    }
    
    const paperSize = document.getElementById('paper_size');
    if (paperSize) toggleCustomSize(paperSize.value);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeTestModal();
    });
    
    initPerCopyWatermark();
});
</script>
@endsection

