@extends('admin.layout')
@section('title', 'Edit Queue: ' . $profile->name)

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Queues', 'url' => route('admin.profiles')], ['label' => 'Edit']]" />

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
</style>

<div class="premium-header">
    <div>
        <h1 class="premium-title">🖨️ Edit Queue: {{ $profile->name }}</h1>
        <p style="color: var(--text-muted); font-size: 0.8rem; margin: 0.15rem 0 0 0;">Modify settings and routing profiles for this virtual printer queue.</p>
    </div>
    <a href="{{ route('admin.profiles') }}" class="btn btn-secondary btn-sm" style="border-radius: 20px; padding: 0.35rem 0.75rem; font-size: 0.72rem;">
        ← Back to List
    </a>
</div>

{{-- Edit Profile Card --}}
<div class="premium-panel" style="margin-bottom: 1rem;">
    <div style="border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
        <h2 style="font-size: 0.95rem; font-weight: 700; color: var(--text); display: flex; align-items: center; gap: 6px; margin: 0;">
            ⚙️ Edit Queue Settings
        </h2>
        <span class="badge badge-info" style="font-size: 0.65rem; padding: 2px 6px;">Modify Rules & Routing</span>
    </div>

    <form action="{{ route('admin.profiles.update', $profile) }}" method="POST">
        @csrf
        @method('PUT')
        
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
            
            {{-- Left Column: Core Identity, Device Assignment & Eco Mode --}}
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                
                {{-- Block 1: Identity & Branch scoping --}}
                <div class="panel-section">
                    <h3 class="panel-section-title" style="color: var(--primary);">
                        📋 1. Basic Configuration
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-group">
                            <label for="name">Queue Identifier (slug) <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="name" id="name" required placeholder="e.g. invoice_sewa" value="{{ old('name', $profile->name) }}">
                            <span style="font-size: 0.65rem; color: var(--text-muted); margin-top: 2px; display: block;">Lower case, underscores only, no spaces.</span>
                        </div>
                        <div class="compact-row">
                            <div class="compact-group">
                                <label for="description">Display Name</label>
                                <input type="text" name="description" id="description" placeholder="e.g. Rental Invoice A4" value="{{ old('description', $profile->description) }}">
                            </div>
                            <div class="compact-group">
                                <label for="branch_id">Branch Scoping <span style="color: var(--danger);">*</span></label>
                                <select name="branch_id" id="branch_id" required>
                                    <option value="">-- Select Branch --</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id', $profile->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->company->code }} / {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Block 2: Physical Assignment & Routing --}}
                <div class="panel-section" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.02);">
                    <h3 class="panel-section-title" style="color: var(--warning);">
                        🖥️ 2. Physical Assignment & Routing
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-group">
                            <label for="routing_type">Routing Type</label>
                            <select id="routing_type" onchange="toggleRoutingType(this.value)" style="width: 100%;">
                                <option value="single" {{ !old('pool_id', $profile->pool_id) ? 'selected' : '' }}>Single Printer Device</option>
                                <option value="pool" {{ old('pool_id', $profile->pool_id) ? 'selected' : '' }}>Printer Pool</option>
                            </select>
                        </div>
                        
                        <div class="compact-group" id="agent_group">
                            <label for="print_agent_id">Target Workstation / Agent <span class="required-asterisk" style="color: var(--danger);">*</span></label>
                            <select name="print_agent_id" id="print_agent_id" required onchange="updatePrinterDropdown(this.value); updateAdvancedOptions(this.value);">
                                <option value="">-- Select Agent --</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" 
                                        data-printers='{{ json_encode($agent->printers ?? []) }}'
                                        data-capabilities='{{ json_encode($agent->capabilities ?? []) }}'
                                        {{ old('print_agent_id', $profile->print_agent_id) == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }} ({{ $agent->isOnline() ? 'Online' : 'Offline' }})
                                    </option>
                                @endforeach
                            </select>
                            <div id="agent-capability-summary" style="font-size: 0.68rem; color: var(--text-muted); margin-top: 3px; min-height: 12px; line-height: 1.2;"></div>
                        </div>

                        <div class="compact-group" id="printer_group">
                            <label for="default_printer">Target Printer Device <span style="color: var(--danger);">*</span></label>
                            <div id="printer_input_container">
                                @if($profile->agent && $profile->agent->printers)
                                    <select name="default_printer" id="default_printer" required style="width: 100%;">
                                        @foreach($profile->agent->printers as $p)
                                            <option value="{{ $p }}" {{ old('default_printer', $profile->default_printer) == $p ? 'selected' : '' }}>{{ $p }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input type="text" name="default_printer" id="default_printer" required placeholder="e.g. Brother-HL-L2360D" value="{{ old('default_printer', $profile->default_printer) }}">
                                @endif
                            </div>
                        </div>

                        <div class="compact-group" id="pool_group" style="display: none;">
                            <label for="pool_id">Target Printer Pool <span style="color: var(--danger);">*</span></label>
                            <select name="pool_id" id="pool_id" style="width: 100%;">
                                <option value="">-- Select Printer Pool --</option>
                                @foreach($pools as $pool)
                                    <option value="{{ $pool->id }}" {{ old('pool_id', $profile->pool_id) == $pool->id ? 'selected' : '' }}>
                                        {{ $pool->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Block 3: Eco Mode & Sustainability --}}
                <div class="panel-section" style="border-color: rgba(34, 197, 94, 0.4); background: rgba(34, 197, 94, 0.01);">
                    <h3 class="panel-section-title" style="color: #16a34a;">
                        🌿 3. Eco Mode & Sustainability
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0.25rem;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.78rem; font-weight: 700; color: #16a34a; user-select: none;">
                                <input type="checkbox" name="eco_mode" value="1" style="width: 15px; height: 15px;" {{ old('eco_mode', $profile->eco_mode) ? 'checked' : '' }}>
                                Enable Eco Mode 🌿
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.76rem; user-select: none;">
                                <input type="checkbox" name="grayscale_force" value="1" style="width: 14px; height: 14px;" {{ old('grayscale_force', $profile->grayscale_force) ? 'checked' : '' }}>
                                Force Grayscale
                            </label>
                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer; font-size: 0.76rem; user-select: none;">
                                <input type="checkbox" name="remove_images" value="1" style="width: 14px; height: 14px;" {{ old('remove_images', $profile->remove_images) ? 'checked' : '' }}>
                                Remove Images
                            </label>
                        </div>

                        <div class="compact-row">
                            <div class="compact-group">
                                <label for="pages_per_sheet">Pages Per Sheet (N-Up)</label>
                                <select name="pages_per_sheet" id="pages_per_sheet">
                                    <option value="1" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '1' ? 'selected' : '' }}>1-up (Standard)</option>
                                    <option value="2" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '2' ? 'selected' : '' }}>2-up</option>
                                    <option value="4" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '4' ? 'selected' : '' }}>4-up</option>
                                    <option value="6" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '6' ? 'selected' : '' }}>6-up</option>
                                    <option value="8" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '8' ? 'selected' : '' }}>8-up</option>
                                    <option value="9" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '9' ? 'selected' : '' }}>9-up</option>
                                    <option value="16" {{ old('pages_per_sheet', $profile->pages_per_sheet ?? 1) == '16' ? 'selected' : '' }}>16-up</option>
                                </select>
                            </div>
                            <div class="compact-group">
                                <label for="eco_duplex">Suggested Duplex</label>
                                <select name="eco_duplex_stub" disabled style="opacity: 0.7;">
                                    <option>Managed under Advanced</option>
                                </select>
                            </div>
                        </div>

                        <div style="background: rgba(34,197,94,0.05); padding: 0.55rem 0.75rem; border-radius: 6px; border: 1px solid rgba(34, 197, 94, 0.15); margin-top: 0.25rem;">
                            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                                <div>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); display: block;">Pages Saved (Duplex)</span>
                                    <strong style="font-size: 0.95rem; color: #16a34a;">{{ number_format($profile->duplex_saved ?? 0) }}</strong>
                                </div>
                                <div>
                                    <span style="font-size: 0.65rem; color: var(--text-muted); display: block;">CO₂ Reduction</span>
                                    <strong style="font-size: 0.95rem; color: #16a34a;">{{ number_format($profile->carbon_saved ?? 0, 2) }} g</strong>
                                </div>
                                <div style="display: flex; align-items: center; font-size: 0.65rem; color: var(--text-muted); font-style: italic;">
                                    🌿 ~5g CO₂ saved per page
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right Column: Media, Specs, Watermarks, Finishing --}}
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                
                {{-- Block 4: Media Standard & Orientation --}}
                <div class="panel-section">
                    <h3 class="panel-section-title" style="color: var(--primary);">
                        📐 4. Media, Layout & Margins
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.55rem;">
                        <div class="compact-row">
                            <div class="compact-group">
                                <label for="paper_size">Paper Standard</label>
                                <select name="paper_size" id="paper_size" onchange="toggleCustomSize(this.value)">
                                    @foreach(['A4', 'A5', 'Letter', 'Half Letter', 'Legal', 'F4', 'Statement', 'Executive', 'Envelope #10'] as $size)
                                        <option value="{{ $size }}" {{ old('paper_size', $profile->paper_size) == $size ? 'selected' : '' }}>{{ $size }}</option>
                                    @endforeach
                                    <option value="CUSTOM" {{ old('paper_size', $profile->paper_size) == 'CUSTOM' ? 'selected' : '' }}>-- Custom Size --</option>
                                </select>
                            </div>
                            <div class="compact-group">
                                <label for="orientation">Orientation</label>
                                <select name="orientation" id="orientation">
                                    <option value="portrait" {{ old('orientation', $profile->orientation) == 'portrait' ? 'selected' : '' }}>Portrait</option>
                                    <option value="landscape" {{ old('orientation', $profile->orientation) == 'landscape' ? 'selected' : '' }}>Landscape</option>
                                </select>
                            </div>
                        </div>

                        {{-- Custom Dimensions block --}}
                        <div id="custom-dims" style="display: none; gap: 8px; margin-top: 0; align-items: flex-end;">
                            <div class="compact-group" style="flex: 1; margin-bottom: 0;">
                                <label id="width-label" style="font-size: 0.68rem; color: var(--text-muted);">Width (mm)</label>
                                <input type="number" name="custom_width" step="0.001" placeholder="e.g. 210" value="{{ old('custom_width', $profile->custom_width) }}">
                            </div>
                            <div class="compact-group" style="flex: 1; margin-bottom: 0;">
                                <label id="height-label" style="font-size: 0.68rem; color: var(--text-muted);">Height (mm)</label>
                                <input type="number" name="custom_height" step="0.001" placeholder="e.g. 297" value="{{ old('custom_height', $profile->custom_height) }}">
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
                        <div class="expandable-content premium-expandable-content" style="display: none;">
                            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.4rem; margin-bottom: 0.5rem;">
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Top</label>
                                    <input type="number" name="margin_top" step="0.01" value="{{ old('margin_top', $profile->margin_top) ?? '0' }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Bottom</label>
                                    <input type="number" name="margin_bottom" step="0.01" value="{{ old('margin_bottom', $profile->margin_bottom) ?? '0' }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Left</label>
                                    <input type="number" name="margin_left" step="0.01" value="{{ old('margin_left', $profile->margin_left) ?? '0' }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                                <div class="compact-group" style="margin-bottom:0;">
                                    <label style="font-size:0.68rem;">Right</label>
                                    <input type="number" name="margin_right" step="0.01" value="{{ old('margin_right', $profile->margin_right) ?? '0' }}" style="padding: 0.3rem; font-size: 0.76rem;">
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="applyDotMatrixDefaults()" style="font-size: 0.68rem; padding: 2px 6px; width: 100%; justify-content: center;">
                                📝 Set Dot-Matrix Defaults (4.23mm)
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Block 5: Advanced Hardware Options --}}
                <div class="panel-section">
                    <div class="expandable premium-expandable" style="margin-top: 0;">
                        <span>⚙️ 5. Advanced Hardware Options</span>
                        <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                    </div>
                    <div class="expandable-content premium-expandable-content" style="display: none;">
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="tray_source" style="font-size: 0.68rem;">Paper Feed</label>
                                <select name="tray_source" id="tray_source" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="">Auto / Default</option>
                                    <option value="AutoSelect" {{ old('tray_source', $profile->tray_source) == 'AutoSelect' ? 'selected' : '' }}>Auto Select</option>
                                    <option value="Tray1" {{ old('tray_source', $profile->tray_source) == 'Tray1' ? 'selected' : '' }}>Tray 1</option>
                                    <option value="Tray2" {{ old('tray_source', $profile->tray_source) == 'Tray2' ? 'selected' : '' }}>Tray 2</option>
                                    <option value="Tray3" {{ old('tray_source', $profile->tray_source) == 'Tray3' ? 'selected' : '' }}>Tray 3</option>
                                    <option value="ManualFeed" {{ old('tray_source', $profile->tray_source) == 'ManualFeed' ? 'selected' : '' }}>Manual Feed</option>
                                    <option value="Bypass Tray" {{ old('tray_source', $profile->tray_source) == 'Bypass Tray' ? 'selected' : '' }}>Bypass Tray</option>
                                    <option value="Envelope" {{ old('tray_source', $profile->tray_source) == 'Envelope' ? 'selected' : '' }}>Envelope Feeder</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="color_mode" style="font-size: 0.68rem;">Color Mode</label>
                                <select name="color_mode" id="color_mode" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="color" {{ old('color_mode', $profile->color_mode) == 'color' ? 'selected' : '' }}>Color</option>
                                    <option value="monochrome" {{ old('color_mode', $profile->color_mode) == 'monochrome' ? 'selected' : '' }}>Mono (B&W)</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="print_quality" style="font-size: 0.68rem;">Quality</label>
                                <select name="print_quality" id="print_quality" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="normal" {{ old('print_quality', $profile->print_quality) == 'normal' ? 'selected' : '' }}>Normal (600)</option>
                                    <option value="draft" {{ old('print_quality', $profile->print_quality) == 'draft' ? 'selected' : '' }}>Draft (300)</option>
                                    <option value="high" {{ old('print_quality', $profile->print_quality) == 'high' ? 'selected' : '' }}>High (1200)</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="media_type" style="font-size: 0.68rem;">Media Type</label>
                                <select name="media_type" id="media_type" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="" {{ old('media_type', $profile->media_type) == '' ? 'selected' : '' }}>Plain Paper</option>
                                    <option value="plain" {{ old('media_type', $profile->media_type) == 'plain' ? 'selected' : '' }}>Plain Paper</option>
                                    <option value="glossy" {{ old('media_type', $profile->media_type) == 'glossy' ? 'selected' : '' }}>Glossy / Photo</option>
                                    <option value="envelope" {{ old('media_type', $profile->media_type) == 'envelope' ? 'selected' : '' }}>Envelope</option>
                                    <option value="label" {{ old('media_type', $profile->media_type) == 'label' ? 'selected' : '' }}>Label / Sticker</option>
                                    <option value="continuous_feed" {{ old('media_type', $profile->media_type) == 'continuous_feed' ? 'selected' : '' }}>Continuous Feed</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="scaling_percentage" style="font-size: 0.68rem;">Scaling (%)</label>
                                <input type="number" name="scaling_percentage" id="scaling_percentage" min="1" max="400" step="1" value="{{ old('scaling_percentage', $profile->scaling_percentage ?? 100) }}" style="padding: 0.3rem; font-size: 0.76rem;">
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="duplex" style="font-size: 0.68rem;">Duplex Mode</label>
                                <select name="duplex" id="duplex" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="one-sided" {{ old('duplex', $profile->duplex) == 'one-sided' ? 'selected' : '' }}>One-sided</option>
                                    <option value="two-sided-long" {{ old('duplex', $profile->duplex) == 'two-sided-long' ? 'selected' : '' }}>2-Sided Long</option>
                                    <option value="two-sided-short" {{ old('duplex', $profile->duplex) == 'two-sided-short' ? 'selected' : '' }}>2-Sided Short</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group" style="display: flex; align-items: center; gap: 6px;">
                                <label for="copies" style="margin-bottom: 0; white-space: nowrap;">Copies:</label>
                                <input type="number" name="copies" id="copies" value="{{ old('copies', $profile->copies) ?? '1' }}" min="1" max="99" style="width: 65px; padding: 0.3rem; font-size: 0.76rem;">
                            </div>
                        </div>

                        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem; padding-top: 0.4rem; border-top: 1px dashed var(--border);">
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; color: var(--primary); font-size: 0.72rem; user-select: none;">
                                <input type="checkbox" name="fit_to_page" value="1" style="width: 14px; height: 14px;" {{ old('fit_to_page', $profile->extra_options['fit_to_page'] ?? false) ? 'checked' : '' }}>
                                Scale to Fit
                            </label>
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; user-select: none; color: var(--text);">
                                <input type="checkbox" name="collate" value="1" style="width: 14px; height: 14px;" {{ old('collate', $profile->collate ?? true) ? 'checked' : '' }}>
                                Collate
                            </label>
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; user-select: none; color: var(--text);">
                                <input type="checkbox" name="reverse_order" value="1" style="width: 14px; height: 14px;" {{ old('reverse_order', $profile->reverse_order ?? false) ? 'checked' : '' }}>
                                Reverse Order
                            </label>
                        </div>
                    </div>
                </div>

                {{-- Block 6: Document Watermarks --}}
                <div class="panel-section">
                    <div class="expandable premium-expandable" style="margin-top: 0;">
                        <span>💧 6. Document Watermarks</span>
                        <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                    </div>
                    <div class="expandable-content premium-expandable-content" style="display: none;">
                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group">
                                <label for="watermark_text">Overlay Text</label>
                                <input type="text" name="watermark_text" id="watermark_text" placeholder="e.g. COPY, CONFIDENTIAL" value="{{ old('watermark_text', $profile->watermark_text) }}" style="padding: 0.35rem; font-size: 0.76rem;" oninput="updateWatermarkPreview()">
                            </div>
                            <div class="compact-group">
                                <label for="watermark_position">Position</label>
                                <select name="watermark_position" id="watermark_position" style="padding: 0.35rem; font-size: 0.76rem;" onchange="updateWatermarkPreview()">
                                    <option value="center" {{ old('watermark_position', $profile->watermark_position ?? 'center') == 'center' ? 'selected' : '' }}>Center</option>
                                    <option value="tile" {{ old('watermark_position', $profile->watermark_position) == 'tile' ? 'selected' : '' }}>Tile (Repeat)</option>
                                    <option value="top-left" {{ old('watermark_position', $profile->watermark_position) == 'top-left' ? 'selected' : '' }}>Top Left</option>
                                    <option value="top-right" {{ old('watermark_position', $profile->watermark_position) == 'top-right' ? 'selected' : '' }}>Top Right</option>
                                    <option value="bottom-left" {{ old('watermark_position', $profile->watermark_position) == 'bottom-left' ? 'selected' : '' }}>Bottom Left</option>
                                    <option value="bottom-right" {{ old('watermark_position', $profile->watermark_position) == 'bottom-right' ? 'selected' : '' }}>Bottom Right</option>
                                </select>
                            </div>
                        </div>

                        <div class="compact-row" style="margin-bottom: 0.5rem;">
                            <div class="compact-group" style="background: var(--surface); padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border);">
                                <label for="watermark_opacity" style="display: flex; justify-content: space-between; align-items: center; font-size:0.68rem; margin-bottom:2px;">
                                    <span>Opacity</span>
                                    <strong id="opacity-value" style="color: var(--primary);">{{ old('watermark_opacity', $profile->watermark_opacity ?? 0.3) }}</strong>
                                </label>
                                <input type="range" name="watermark_opacity" id="watermark_opacity" min="0.1" max="1" step="0.05" value="{{ old('watermark_opacity', $profile->watermark_opacity ?? 0.3) }}" oninput="document.getElementById('opacity-value').textContent=this.value; updateWatermarkPreview();" style="cursor: pointer; height: 3px; width: 100%;">
                            </div>
                            <div class="compact-group" style="background: var(--surface); padding: 0.4rem; border-radius: 4px; border: 1px solid var(--border);">
                                <label for="watermark_rotation" style="display: flex; justify-content: space-between; align-items: center; font-size:0.68rem; margin-bottom:2px;">
                                    <span>Angle</span>
                                    <strong id="rotation-value" style="color: var(--primary);">{{ old('watermark_rotation', $profile->watermark_rotation ?? -45) }}°</strong>
                                </label>
                                <input type="range" name="watermark_rotation" id="watermark_rotation" min="-90" max="90" step="5" value="{{ old('watermark_rotation', $profile->watermark_rotation ?? -45) }}" oninput="document.getElementById('rotation-value').textContent=this.value + '°'; updateWatermarkPreview();" style="cursor: pointer; height: 3px; width: 100%;">
                            </div>
                        </div>

                        <div id="watermark-preview" style="margin-top: 0.5rem; padding: 0.75rem; background: var(--bg); border-radius: 5px; border: 1px solid var(--border); min-height: 55px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative;">
                            <span style="color: var(--text-muted); font-size: 0.74rem;">Preview will appear here</span>
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

                {{-- Block 7: Finishing Options --}}
                <div class="panel-section">
                    <div class="expandable premium-expandable" style="margin-top: 0;">
                        <span>🔧 7. Finishing Options</span>
                        <span class="expandable-arrow" style="color: var(--text-muted); font-size: 0.65rem;">▸</span>
                    </div>
                    <div class="expandable-content premium-expandable-content" style="display: none;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="finishing_staple" style="font-size: 0.68rem;">Stapling</label>
                                <select name="finishing_staple" id="finishing_staple" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="" {{ old('finishing_staple', $profile->finishing_staple) == '' ? 'selected' : '' }}>None</option>
                                    <option value="single" {{ old('finishing_staple', $profile->finishing_staple) == 'single' ? 'selected' : '' }}>Single Staple</option>
                                    <option value="dual" {{ old('finishing_staple', $profile->finishing_staple) == 'dual' ? 'selected' : '' }}>Dual Staple</option>
                                    <option value="saddle" {{ old('finishing_staple', $profile->finishing_staple) == 'saddle' ? 'selected' : '' }}>Saddle Stitch</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="finishing_punch" style="font-size: 0.68rem;">Hole Punch</label>
                                <select name="finishing_punch" id="finishing_punch" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="" {{ old('finishing_punch', $profile->finishing_punch) == '' ? 'selected' : '' }}>None</option>
                                    <option value="2" {{ old('finishing_punch', $profile->finishing_punch) == '2' ? 'selected' : '' }}>2 Holes</option>
                                    <option value="4" {{ old('finishing_punch', $profile->finishing_punch) == '4' ? 'selected' : '' }}>4 Holes</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.5rem; margin-bottom: 0.5rem;">
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="finishing_fold" style="font-size: 0.68rem;">Folding</label>
                                <select name="finishing_fold" id="finishing_fold" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="" {{ old('finishing_fold', $profile->finishing_fold) == '' ? 'selected' : '' }}>None</option>
                                    <option value="half" {{ old('finishing_fold', $profile->finishing_fold) == 'half' ? 'selected' : '' }}>Half Fold</option>
                                    <option value="tri-fold" {{ old('finishing_fold', $profile->finishing_fold) == 'tri-fold' ? 'selected' : '' }}>Tri-Fold</option>
                                    <option value="z-fold" {{ old('finishing_fold', $profile->finishing_fold) == 'z-fold' ? 'selected' : '' }}>Z-Fold</option>
                                </select>
                            </div>
                            <div class="compact-group" style="margin-bottom:0;">
                                <label for="finishing_bind" style="font-size: 0.68rem;">Binding</label>
                                <select name="finishing_bind" id="finishing_bind" style="padding: 0.3rem; font-size: 0.76rem;">
                                    <option value="" {{ old('finishing_bind', $profile->finishing_bind) == '' ? 'selected' : '' }}>None</option>
                                    <option value="tape" {{ old('finishing_bind', $profile->finishing_bind) == 'tape' ? 'selected' : '' }}>Tape Binding</option>
                                    <option value="comb" {{ old('finishing_bind', $profile->finishing_bind) == 'comb' ? 'selected' : '' }}>Comb Binding</option>
                                    <option value="thermal" {{ old('finishing_bind', $profile->finishing_bind) == 'thermal' ? 'selected' : '' }}>Thermal Binding</option>
                                </select>
                            </div>
                        </div>

                        <div style="padding-top: 0.4rem; border-top: 1px dashed var(--border);">
                            <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; font-size: 0.72rem; user-select: none; color: var(--text);">
                                <input type="checkbox" name="finishing_booklet" value="1" style="width: 14px; height: 14px;" {{ old('finishing_booklet', $profile->finishing_booklet ?? false) ? 'checked' : '' }}>
                                Booklet Mode (2-up, reverse stack)
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 0.5rem; display: flex; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 0.6rem; gap: 8px;">
            <a href="{{ route('admin.profiles') }}" class="btn btn-secondary" style="padding: 0.45rem 1.25rem; font-weight: 700; border-radius: 4px; font-size: 0.8rem;">Cancel</a>
            <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1.25rem; font-weight: 700; border-radius: 4px; font-size: 0.8rem; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.2);">
                💾 Save Changes
            </button>
        </div>
    </form>
</div>

<script>
window._savedWatermarkCopies = {!! json_encode(old('watermark_copies', $profile->watermark_copies ?? [])) !!};
</script>
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
        container.innerHTML = `<input type="text" name="default_printer" id="default_printer" placeholder="e.g. Brother-HL-L2360D" style="background: var(--surface); color: var(--text); border: 1px solid var(--border); padding: 0.45rem 0.6rem; font-size: 0.82rem; border-radius: 4px; width: 100%;">`;
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
        'A2': 'A2 (420×594mm)', 'A1': 'A1 (594×841mm)', 'A0': 'A0 (841×1189mm)',
        'B4': 'B4 (250×353mm)', 'B5': 'B5 (176×250mm)',
    };
    const sizeOptions = [{ value: '', label: 'Default (Printer Setting)' }];
    if (allPaperSizes.size > 0) {
        Array.from(allPaperSizes).sort().forEach(s => {
            if (s && s !== '') sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s });
        });
    } else {
        ['A4', 'A5', 'Letter', 'Half Letter', 'Legal', 'F4', 'Statement', 'Executive', 'Envelope #10'].forEach(s =>
            sizeOptions.push({ value: s, label: PAPER_LABELS[s] || s }));
    }
    sizeOptions.push({ value: 'CUSTOM', label: 'Custom Size...' });
    resetSelectOptions('paper_size', sizeOptions);

    // ── Duplex ──
    const DUPLEX_MAP = {
        'None': { value: 'one-sided', label: 'One-sided' },
        'TwoSidedLong': { value: 'two-sided-long', label: '2-Sided Long' },
        'TwoSidedShort': { value: 'two-sided-short', label: '2-Sided Short' },
    };
    const duplexOptions = [{ value: '', label: 'Default' }];
    if (allDuplexModes.size > 0) {
        allDuplexModes.forEach(d => { if (DUPLEX_MAP[d]) duplexOptions.push(DUPLEX_MAP[d]); });
    }
    if (duplexOptions.length <= 1) {
        duplexOptions.push(
            { value: 'one-sided', label: 'One-sided' },
            { value: 'two-sided-long', label: '2-Sided Long' },
            { value: 'two-sided-short', label: '2-Sided Short' }
        );
    }
    resetSelectOptions('duplex', duplexOptions);

    // ── Tray Source ──
    const trayOptions = [{ value: '', label: 'Auto (Default)' }];
    allTrays.forEach(trayName => { if (trayName && trayName !== '') trayOptions.push({ value: trayName, label: trayName }); });
    ['AutoSelect', 'Tray1', 'Tray2', 'Tray3', 'ManualFeed', 'Bypass Tray', 'Envelope'].forEach(t => {
        if (!allTrays.has(t) && !trayOptions.some(o => o.value === t)) trayOptions.push({ value: t, label: t });
    });
    resetSelectOptions('tray_source', trayOptions);

    // ── Color Mode ──
    const colorOptions = [];
    if (allColorModes.has('color')) colorOptions.push({ value: 'color', label: 'Color' });
    if (allColorModes.has('gray') || allColorModes.has('monochrome'))
        colorOptions.push({ value: 'monochrome', label: 'Mono (B&W)' });
    if (colorOptions.length === 0) colorOptions.push({ value: 'color', label: 'Color' }, { value: 'monochrome', label: 'Mono (B&W)' });
    resetSelectOptions('color_mode', colorOptions);

    // ── Print Quality (Resolutions) ──
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
        qualityOptions.push({ value: 'normal', label: 'Normal (600)' }, { value: 'draft', label: 'Draft (300)' }, { value: 'high', label: 'High (1200)' });
    }
    resetSelectOptions('print_quality', qualityOptions);

    // ── Media Type ──
    const mediaOptions = [{ value: '', label: 'Plain Paper' }];
    const MEDIA_LABELS = { plain: 'Plain Paper', glossy: 'Glossy / Photo', envelope: 'Envelope', label: 'Label / Sticker', continuous_feed: 'Continuous Feed' };
    if (allMediaTypes.size > 0) {
        allMediaTypes.forEach(m => mediaOptions.push({ value: m, label: MEDIA_LABELS[m.toLowerCase().replace(/[^a-z]/g, '')] || m }));
    } else {
        ['plain', 'glossy', 'envelope', 'label', 'continuous_feed'].forEach(m => mediaOptions.push({ value: m, label: MEDIA_LABELS[m] || m }));
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
    if (dims) dims.style.display = (val === 'CUSTOM') ? 'flex' : 'none';
}

function toggleUnit(isInch) {
    document.getElementById('width-label').innerText = isInch ? 'Width (Inch)' : 'Width (mm)';
    document.getElementById('height-label').innerText = isInch ? 'Height (Inch)' : 'Height (mm)';
}

function applyDotMatrixDefaults() {
    // 1/6th inch standard pitch = 4.233mm
    const inputs = document.querySelectorAll('[name="margin_top"], [name="margin_bottom"], [name="margin_left"], [name="margin_right"]');
    inputs.forEach(input => input.value = "4.23");
}

function updateWatermarkPreview() {
    const text = document.getElementById('watermark_text').value;
    const position = document.getElementById('watermark_position').value;
    const opacity = document.getElementById('watermark_opacity').value;
    const rotation = document.getElementById('watermark_rotation').value;
    const preview = document.getElementById('watermark-preview');

    if (!text) {
        preview.innerHTML = '<span style="color: var(--text-muted); font-size: 0.74rem;">Preview will appear here</span>';
        return;
    }

    const alpha = Math.min(1, Math.max(0.1, parseFloat(opacity) || 0.3));
    const rot = parseInt(rotation) || -45;
    const fontSize = position === 'tile' ? 10 : 20;

    let html = '';
    if (position === 'tile') {
        const rows = 3;
        const cols = 4;
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                html += `<span style="display: inline-block; padding: 2px 4px; font-size: ${fontSize}px; font-weight: bold; color: rgba(120,120,120,${alpha}); transform: rotate(${rot}deg); white-space: nowrap;">${escapeHtml(text)}</span>`;
            }
            html += '<br>';
        }
    } else {
        html = `<span style="font-size: ${fontSize}px; font-weight: bold; color: rgba(120,120,120,${alpha}); transform: rotate(${rot}deg); display: inline-block;">${escapeHtml(text)}</span>`;
    }

    preview.innerHTML = html;
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── Per-Copy Watermark UI ──
function initPerCopyWatermark() {
    const copiesInput = document.getElementById('copies');
    if (!copiesInput) return;

    copiesInput.addEventListener('input', updateCopyWatermarkConfigs);
    copiesInput.addEventListener('change', updateCopyWatermarkConfigs);
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

    // Get saved values from PHP (for initial load)
    const savedCopies = window._savedWatermarkCopies || [];

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
        // Merge: existing DOM > saved PHP data > defaults
        const saved = savedCopies[i] || {};
        const cfg = existingConfigs[i] || {};
        const textVal = cfg.text || (typeof saved === 'string' ? saved : (saved.text || ''));
        const opacityVal = cfg.opacity || (saved.opacity || '0.3');
        const rotationVal = cfg.rotation || (saved.rotation || '-45');
        const positionVal = cfg.position || (saved.position || 'center');

        html += '<div style="border: 1px solid var(--border); border-radius: 6px; padding: 0.55rem; margin-bottom: 0.55rem; background: rgba(255,255,255,0.01);">';
        html += '<div style="font-size: 0.74rem; font-weight: 700; color: var(--primary); margin-bottom: 0.35rem;">📄 Copy ' + (i + 1) + '</div>';

        // Text + Position row
        html += '<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 8px; margin-bottom: 0.4rem;">';
        html += '<div class="compact-group" style="margin-bottom:0;">';
        html += '<label style="font-size: 0.68rem;">Watermark Text</label>';
        html += '<input type="text" name="watermark_copies[' + i + '][text]" value="' + textVal.replace(/"/g, '&quot;') + '" placeholder="e.g. Customer Copy" style="font-size: 0.76rem; padding: 0.35rem;">';
        html += '</div>';
        html += '<div class="compact-group" style="margin-bottom:0;">';
        html += '<label style="font-size: 0.68rem;">Position</label>';
        html += '<select name="watermark_copies[' + i + '][position]" style="font-size: 0.76rem; padding: 0.35rem;">';
        positionOptions.forEach(po => {
            const sel = po.value === positionVal ? ' selected' : '';
            html += '<option value="' + po.value + '"' + sel + '>' + po.label + '</option>';
        });
        html += '</select>';
        html += '</div>';
        html += '</div>';

        // Opacity + Rotation row
        html += '<div class="compact-row">';
        html += '<div class="compact-group" style="background: var(--surface); padding: 0.35rem; border-radius: 4px; border: 1px solid var(--border); margin-bottom:0;">';
        html += '<label style="font-size: 0.66rem; display:flex; justify-content:space-between;"><span>Opacity</span><strong id="copy-opacity-' + i + '" style="color:var(--primary);">' + opacityVal + '</strong></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][opacity]" min="0.1" max="1" step="0.05" value="' + opacityVal + '" oninput="document.getElementById(\'copy-opacity-' + i + '\').textContent=this.value;" style="width:100%; height:3px; cursor:pointer;">';
        html += '</div>';
        html += '<div class="compact-group" style="background: var(--surface); padding: 0.35rem; border-radius: 4px; border: 1px solid var(--border); margin-bottom:0;">';
        html += '<label style="font-size: 0.66rem; display:flex; justify-content:space-between;"><span>Angle</span><strong id="copy-rotation-' + i + '" style="color:var(--primary);">' + rotationVal + '°</strong></label>';
        html += '<input type="range" name="watermark_copies[' + i + '][rotation]" min="-90" max="90" step="5" value="' + rotationVal + '" oninput="document.getElementById(\'copy-rotation-' + i + '\').textContent=this.value + \'°\';" style="width:100%; height:3px; cursor:pointer;">';
        html += '</div>';
        html += '</div>';

        html += '</div>';
    }
    container.innerHTML = html;
}

function toggleRoutingType(type) {
    const agentGroup = document.getElementById('agent_group');
    const printerGroup = document.getElementById('printer_group');
    const poolGroup = document.getElementById('pool_group');
    const agentSelect = document.getElementById('print_agent_id');
    const printerInput = document.getElementById('default_printer');
    const poolSelect = document.getElementById('pool_id');
    const asterisk = agentGroup ? agentGroup.querySelector('.required-asterisk') : null;

    if (type === 'single') {
        if (agentGroup) agentGroup.style.display = 'block';
        if (printerGroup) printerGroup.style.display = 'block';
        if (poolGroup) poolGroup.style.display = 'none';

        if (agentSelect) agentSelect.required = true;
        if (printerInput) printerInput.required = true;
        if (poolSelect) {
            poolSelect.required = false;
            poolSelect.value = '';
        }
        if (asterisk) asterisk.style.display = 'inline';
    } else {
        if (agentGroup) agentGroup.style.display = 'block';
        if (printerGroup) printerGroup.style.display = 'none';
        if (poolGroup) poolGroup.style.display = 'block';

        if (agentSelect) {
            agentSelect.required = false;
        }
        if (printerInput) {
            printerInput.required = false;
            printerInput.value = '';
        }
        if (poolSelect) poolSelect.required = true;
        if (asterisk) asterisk.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Accordion setup
    document.querySelectorAll('.premium-expandable').forEach(el => {
        el.addEventListener('click', () => {
            const content = el.nextElementSibling;
            const arrow = el.querySelector('.expandable-arrow');
            if (content.style.display === 'none' || !content.style.display) {
                content.style.display = 'block';
                if (arrow) arrow.textContent = '▾';
            } else {
                content.style.display = 'none';
                if (arrow) arrow.textContent = '▸';
            }
        });
    });

    const agentSelect = document.getElementById('print_agent_id');
    if (agentSelect && agentSelect.value) {
        const currentPrinter = "{{ old('default_printer', $profile->default_printer) }}";
        
        updatePrinterDropdown(agentSelect.value);
        const printerSelect = document.getElementById('default_printer');
        if (printerSelect && printerSelect.tagName === 'SELECT' && currentPrinter) {
            printerSelect.value = currentPrinter;
        }
        
        updateAdvancedOptions(agentSelect.value);
        
        // Re-set the saved advanced values after dynamic population
        const traySourceSelect = document.getElementById('tray_source');
        if (traySourceSelect) traySourceSelect.value = "{{ old('tray_source', $profile->tray_source) }}";
        
        const colorModeSelect = document.getElementById('color_mode');
        if (colorModeSelect) colorModeSelect.value = "{{ old('color_mode', $profile->color_mode) }}";
        
        const printQualitySelect = document.getElementById('print_quality');
        if (printQualitySelect) printQualitySelect.value = "{{ old('print_quality', $profile->print_quality) }}";
        
        const duplexSelect = document.getElementById('duplex');
        if (duplexSelect) duplexSelect.value = "{{ old('duplex', $profile->duplex) }}";
        
        const mediaTypeSelect = document.getElementById('media_type');
        if (mediaTypeSelect) mediaTypeSelect.value = "{{ old('media_type', $profile->media_type) }}";
        
        const scalingSelect = document.getElementById('scaling_percentage');
        if (scalingSelect) scalingSelect.value = "{{ old('scaling_percentage', $profile->scaling_percentage ?? 100) }}";
    }
    
    const routingType = document.getElementById('routing_type');
    if (routingType) {
        toggleRoutingType(routingType.value);
    }
    
    const paperSize = document.getElementById('paper_size');
    if (paperSize) toggleCustomSize(paperSize.value);
    
    initPerCopyWatermark();
    updateWatermarkPreview();
});
</script>
@endsection
