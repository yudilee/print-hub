@extends('admin.layout')

@section('head')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>
@endsection

@section('content')
<style>
    :root {
        --primary: #3b82f6; --primary-hover: #2563eb; --bg: #0f172a; --surface: #1e293b;
        --surface-hover: #334155; --border: #334155; --text: #f1f5f9; --text-muted: #94a3b8;
        --danger: #ef4444; --success: #22c55e;
    }
    .designer-container { display: flex; flex-direction: column; height: calc(100vh - 100px); margin: -2rem; }
    .designer-top-bar {
        height: 56px; background: var(--surface); border-bottom: 1px solid var(--border);
        display: flex; align-items: center; padding: 0 1rem; gap: 1rem; z-index: 100;
    }
    .designer-main { display: flex; flex: 1; overflow: hidden; position: relative; }
    .designer-left-toolbar {
        width: 64px; background: var(--surface); border-right: 1px solid var(--border);
        display: flex; flex-direction: column; align-items: center; padding: 1rem 0; gap: 1rem;
    }
    .designer-workspace {
        flex: 1; background: var(--bg); overflow: auto; position: relative;
        display: flex; align-items: flex-start; justify-content: flex-start;
        padding: 40px;
    }
    .designer-right-props {
        width: 320px; background: var(--surface); border-left: 1px solid var(--border);
        display: flex; flex-direction: column;
    }
    @media (max-width: 1100px) {
        .designer-right-props { width: 260px; }
    }
    @media (max-width: 900px) {
        .designer-right-props { width: 220px; }
    }
    .designer-main-wrapper { overflow-x: auto; flex: 1; display: flex; }
    
    .designer-tabs { display: flex; border-bottom: 1px solid var(--border); background: rgba(0,0,0,0.1); }
    .tab-item { 
        flex: 1; padding: 10px; text-align: center; font-size: 0.75rem; font-weight: 600; 
        color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent;
    }
    .tab-item:hover { color: var(--text); }
    .tab-item.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(59,130,246,0.05); }
    .tab-panel { display: none; flex: 1; flex-direction: column; overflow-y: auto; }
    .tab-panel.active { display: flex; }

    .props-header {
        padding: 12px 1rem; border-bottom: 1px solid var(--border);
        background: rgba(0,0,0,0.05); font-weight: 600; font-size: 0.8rem;
        text-transform: uppercase; color: var(--text-muted);
    }
    .props-section { border-bottom: 1px solid var(--border); }
    .props-label {
        padding: 10px 1rem; background: var(--surface-hover); cursor: pointer;
        display: flex; justify-content: space-between; align-items: center;
        font-size: 0.75rem; font-weight: 600; color: var(--text-muted);
    }
    
    .prop-table { display: flex; flex-direction: column; border-bottom: 1px solid var(--border); }
    .prop-item { display: flex; border-top: 1px solid var(--border); min-height: 28px; }
    .prop-item.active { background: rgba(59,130,246,0.1); }
    .prop-key { 
        width: 40%; padding: 4px 8px; border-right: 1px solid var(--border); 
        font-size: 11px; color: var(--text-muted); display: flex; align-items: center;
        background: rgba(0,0,0,0.05);
    }
    .prop-val { 
        width: 60%; padding: 0; font-size: 11px; display: flex; align-items: center;
    }
    .prop-val input, .prop-val select {
        width: 100%; height: 28px; border: none; background: transparent; 
        color: var(--text); padding: 0 8px; font-size: 11px; outline: none;
    }
    .prop-val input:focus { background: rgba(255,255,255,0.05); color: var(--primary); }
    
    .badge-delphi {
        background: #334155; color: #fbbf24; padding: 2px 6px; border-radius: 4px;
        font-size: 10px; font-weight: bold; border: 1px solid #475569;
    }

    .ruler { position: absolute; background: var(--surface); color: var(--text-muted); font-size: 9px; z-index: 50; }
    .ruler-top { top: 0; left: 40px; right: 0; height: 25px; border-bottom: 1px solid var(--border); }
    .ruler-left { top: 40px; left: 0; bottom: 0; width: 25px; border-right: 1px solid var(--border); }

    #canvas-wrapper { position: relative; background: var(--surface); box-shadow: 0 0 50px rgba(0,0,0,0.5); transform-origin: top left; }
    #canvas { position: relative; background: white; overflow: hidden; }
    #canvas-bg-img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: contain; opacity: 0.4; pointer-events: none; }
    
    .design-element {
        position: absolute; border: 1px dashed transparent; cursor: move;
        display: flex; align-items: flex-start; justify-content: flex-start;
        user-select: none; box-sizing: border-box; overflow: visible !important;
    }
    .design-element:hover { border-color: rgba(59,130,246,0.5); background: rgba(59,130,246,0.05); }
    .design-element.active { outline: 2px solid var(--primary); outline-offset: 2px; background: rgba(59,130,246,0.1); z-index: 100; border-color: transparent !important; }
    
    .handle { position: absolute; width: 10px; height: 10px; background: white; border: 1px solid var(--primary); z-index: 999; pointer-events: auto; box-shadow: 0 0 4px rgba(0,0,0,0.3); }
    .res-nw { top: -5px; left: -5px; cursor: nw-resize; }
    .res-n { top: -5px; left: calc(50% - 5px); cursor: n-resize; }
    .res-ne { top: -5px; right: -5px; cursor: ne-resize; }
    .res-e { top: calc(50% - 5px); right: -5px; cursor: e-resize; }
    .res-se { bottom: -5px; right: -5px; cursor: se-resize; }
    .res-s { bottom: -5px; left: calc(50% - 5px); cursor: s-resize; }
    .res-sw { bottom: -5px; left: -5px; cursor: sw-resize; }
    .res-w { top: calc(50% - 5px); left: -5px; cursor: w-resize; }

    .tool-btn {
        width: 40px; height: 40px; border-radius: 8px; border: 1px solid var(--border);
        background: var(--surface); color: var(--text); display: flex; align-items: center;
        justify-content: center; cursor: pointer; transition: all 0.2s; font-size: 14px;
    }
    .tool-btn:hover { background: var(--surface-hover); border-color: var(--primary); color: var(--primary); }
    .tool-btn.active-tool { background: rgba(59,130,246,0.15); border-color: var(--primary); color: var(--primary); }
    .action-btn {
        background: var(--surface-hover); border: 1px solid var(--border); color: var(--text);
        padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;
    }
    .action-btn:hover { background: var(--border); border-color: var(--primary); }
    .action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .action-group { display: flex; gap: 0.5rem; align-items: center; }

    /* Context Menu */
    #ctx-menu { position:fixed; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:4px 0; z-index:9999; min-width:160px; box-shadow:0 8px 24px rgba(0,0,0,0.4); display:none; }
    .ctx-item { padding:7px 14px; font-size:12px; cursor:pointer; display:flex; align-items:center; gap:8px; color:var(--text); }
    .ctx-item:hover { background:var(--surface-hover); color:var(--primary); }
    .ctx-item.danger { color:var(--danger); }
    .ctx-separator { border-top:1px solid var(--border); margin:4px 0; }

    /* Coordinate tooltip */
    #coord-tip { position:fixed; background:var(--surface); color:var(--text-muted); font-size:10px; padding:3px 7px; border-radius:4px; pointer-events:none; display:none; z-index:8000; font-family:monospace; box-shadow:0 2px 8px rgba(0,0,0,0.3); }

    /* Snap grid overlay */
    #snap-grid { position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; display:none; }

    /* Minimap */
    #minimap { position:absolute; bottom:10px; right:10px; background:var(--surface); border:1px solid var(--border); border-radius:6px; overflow:hidden; cursor:pointer; box-shadow:0 2px 12px rgba(0,0,0,0.3); }
    #minimap-canvas { display:block; }

    /* Layer row controls */
    .layer-row { display:flex; align-items:center; padding:6px 8px; border-bottom:1px solid var(--border); cursor:pointer; transition:background 0.15s; }
    .layer-row:hover { background:var(--surface-hover); }
    .layer-row.active { background:rgba(59,130,246,0.12); }
    .layer-row .lbl { flex:1; font-size:11px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .layer-icon { width:22px; height:22px; display:flex; align-items:center; justify-content:center; border-radius:4px; font-size:12px; cursor:pointer; opacity:0.5; transition:opacity 0.15s; }
    .layer-icon:hover { opacity:1; background:rgba(255,255,255,0.08); }
    .layer-icon.on { opacity:1; }

    /* Smart alignment guides */
    .smart-guide { position:absolute; pointer-events:none; z-index:500; }
    .smart-guide-h { height:1px; background:rgba(239,68,68,0.7); left:0; right:0; border-top:1px dashed #ef4444; }
    .smart-guide-v { width:1px; background:rgba(239,68,68,0.7); top:0; bottom:0; border-left:1px dashed #ef4444; }

    /* Live data mode indicator */
    .live-data-btn { transition: all 0.2s; }
    .live-data-btn.active { background: rgba(34,197,94,0.2) !important; border-color: var(--success) !important; color: var(--success) !important; }
    .field-resolved { border-left: 2px solid var(--success) !important; }
    .field-unresolved { border-left: 2px solid #f59e0b !important; }

    /* Schema version badge */
    .schema-outdated-banner { background:rgba(239,68,68,0.12); border:1px solid rgba(239,68,68,0.3); border-radius:6px; padding:8px 12px; margin:8px; font-size:11px; color:#fca5a5; display:flex; align-items:center; gap:6px; }
    .schema-version-badge { display:inline-block; background:rgba(59,130,246,0.15); color:var(--primary); padding:1px 6px; border-radius:3px; font-size:9px; font-weight:bold; }
    
    /* Field type badges */
    .field-type-tag { display:inline-block; padding:1px 4px; border-radius:3px; font-size:8px; font-weight:600; margin-left:4px; }
    .field-type-tag.string { background:rgba(148,163,184,0.2); color:#94a3b8; }
    .field-type-tag.number { background:rgba(59,130,246,0.2); color:#60a5fa; }
    .field-type-tag.date { background:rgba(168,85,247,0.2); color:#c084fc; }
    .field-type-tag.currency { background:rgba(34,197,94,0.2); color:#4ade80; }
    .field-type-tag.boolean { background:rgba(251,191,36,0.2); color:#fbbf24; }
</style>

<div class="designer-container">
    <div class="designer-top-bar">
        <div class="action-group">
            <button onclick="saveTemplate()" id="save-btn" class="btn btn-primary btn-sm" title="Save (Ctrl+S)">💾 Save</button>
            <button onclick="openPreview()" class="btn btn-success btn-sm">👁 Preview</button>
            <button onclick="showTestPrint()" class="btn btn-warning btn-sm">🖨 Print Test</button>
            <button onclick="toggleSampleDataPanel()" class="btn btn-secondary btn-sm" title="Sample Data">📋 Sample Data</button>
            <button onclick="exportTemplate()" class="btn btn-secondary btn-sm" title="Export JSON">↓ Export</button>
            <button onclick="importTemplate()" class="btn btn-secondary btn-sm" title="Import JSON">↑ Import</button>
            <input type="file" id="import-file" accept=".json" style="display:none">
            <button onclick="window.location.href='{{ route('admin.templates') }}'" class="btn btn-secondary btn-sm">Discard</button>
            @if($template->id)
            <a href="/admin/templates/{{ $template->id }}/versions" class="btn btn-secondary btn-sm" style="text-decoration: none;">
                📜 Versions
            </a>
            @endif
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <button id="undo-btn" onclick="undo()" class="action-btn" title="Undo (Ctrl+Z)" disabled>↩</button>
            <button id="redo-btn" onclick="redo()" class="action-btn" title="Redo (Ctrl+Y)" disabled>↪</button>
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <button onclick="changeZoom(-0.1)" class="action-btn">−</button>
            <span id="zoom-val" style="font-size: 0.8rem; font-weight: 500; min-width: 40px; text-align: center;">100%</span>
            <button onclick="changeZoom(0.1)" class="action-btn">+</button>
            <button onclick="changeZoom(0, true)" class="action-btn" title="Reset Zoom">↺</button>
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <button id="snap-btn" onclick="toggleSnap()" class="action-btn" title="Toggle Snap to Grid">⊞ Snap</button>
            <button id="live-data-btn" onclick="toggleLiveData()" class="action-btn live-data-btn" title="Toggle Live Data Preview">◉ Live</button>
            <button id="guides-btn" onclick="toggleSmartGuides()" class="action-btn" title="Toggle Smart Guides">⊹ Guides</button>
        </div>
        <div class="action-group" id="align-tools" style="display:none;">
            <div style="border-left: 1px solid var(--border); height: 20px; margin: 0 2px;"></div>
            <button onclick="alignElements('left')" class="action-btn" title="Align Left">⇤</button>
            <button onclick="alignElements('right')" class="action-btn" title="Align Right">⇥</button>
            <button onclick="alignElements('top')" class="action-btn" title="Align Top">⤒</button>
            <button onclick="alignElements('bottom')" class="action-btn" title="Align Bottom">⤓</button>
            <button onclick="distributeH()" class="action-btn" title="Distribute Horizontally">⇔</button>
            <button onclick="distributeV()" class="action-btn" title="Distribute Vertically">⇕</button>
            <div style="border-left: 1px solid var(--border); height: 20px; margin: 0 2px;"></div>
            <button onclick="groupElements()" class="action-btn" title="Group (Ctrl+G)">📦</button>
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <span style="font-size: 0.75rem; color: var(--text-muted);">Name:</span>
            <input type="text" id="tpl-name" value="{{ $template->name }}" style="padding: 2px 8px; font-size:0.8rem; width:150px; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px;">
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <select id="paper-preset" onchange="applyPaperPreset()" style="background:var(--bg);border:1px solid var(--border);color:var(--text);padding:2px 6px;font-size:11px;border-radius:4px;">
                <option value="">Paper Preset</option>
                <option value="241.3,139.7">Continuous 9.5×5.5"</option>
                <option value="241.3,279.4">Continuous 9.5×11"</option>
                <option value="215.9,139.7">Half Letter</option>
                <option value="215.9,279.4">Letter</option>
                <option value="210,297">A4</option>
            </select>
        </div>
    </div>

    <div class="designer-main">
        <div class="designer-main-wrapper">
        <div class="designer-left-toolbar">
            <button onclick="addElement('field')" class="tool-btn" title="Add Data Field (T)">T</button>
            <button onclick="addElement('label')" class="tool-btn" title="Add Static Label (L)">Aa</button>
            <button onclick="addElement('table')" class="tool-btn" title="Add Data Table">▦</button>
            <button onclick="addElement('line')" class="tool-btn" title="Add Separator Line">—</button>
            <button onclick="addElement('image')" class="tool-btn" title="Add Image/Logo">🖼</button>
            <button onclick="addElement('barcode')" class="tool-btn" title="Add Barcode">▌▐</button>
            <button onclick="addElement('qrcode')" class="tool-btn" title="Add QR Code">◈</button>
            <button onclick="addElement('running_total')" class="tool-btn" title="Add Running Total">Σ</button>
            <label class="tool-btn" title="Upload Background Trace" style="cursor:pointer">
                🖼️<input type="file" id="bg-upload" style="display:none" onchange="uploadBg()">
            </label>
        </div>

        <div class="designer-workspace" id="designer-workspace">
            <div id="ruler-top" class="ruler ruler-top"></div>
            <div id="ruler-left" class="ruler ruler-left"></div>
            
            <div id="canvas-wrapper">
                <div id="canvas">
                    <img id="canvas-bg-img" src="{{ $template->background_image_path ? asset($template->background_image_path) : '' }}" 
                         style="{{ $template->background_image_path ? '' : 'display:none' }}; opacity: {{ ($template->background_config['opacity'] ?? 40) / 100 }}">
                    <canvas id="snap-grid"></canvas>
                    <div id="rubber-band" style="position:absolute; border:1px dashed var(--primary); background:rgba(59,130,246,0.08); display:none; pointer-events:none;"></div>
                </div>
            </div>
            <div id="minimap" style="position:absolute; bottom:14px; right:14px;">
                <canvas id="minimap-canvas" width="120" height="80"></canvas>
            </div>
        </div>

        <div class="designer-right-props">
            <div class="designer-tabs">
                <div class="tab-item active" onclick="switchTab('props')">Properties</div>
                <div class="tab-item" onclick="switchTab('sections')">Sections</div>
                <div class="tab-item" onclick="switchTab('layers')">Layers</div>
                <div class="tab-item" onclick="switchTab('data')">Data</div>
            </div>
            
            <div id="tab-props" class="tab-panel active">
                <div id="inspector-content">
                    <div style="text-align:center; padding:3rem 1rem; color:var(--text-muted); font-size:0.8rem;">Select an object</div>
                </div>

                <div style="margin-top:auto; padding:1rem; border-top:1px solid var(--border);">
                    <div class="props-section">
                        <div class="props-label">Paper Settings</div>
                        <div class="props-content" style="padding:1rem;">
                            <div class="prop-row" style="display:flex; gap:0.5rem;">
                                <div class="form-group"><label style="font-size:10px; color:var(--text-muted)">W (mm)</label><input type="number" id="paper-w" value="{{ $template->paper_width_mm ?? 215.9 }}" onchange="updateCanvasSize()" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:4px;"></div>
                                <div class="form-group"><label style="font-size:10px; color:var(--text-muted)">H (mm)</label><input type="number" id="paper-h" value="{{ $template->paper_height_mm ?? 139.7 }}" onchange="updateCanvasSize()" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:4px;"></div>
                            </div>
                            <input type="hidden" id="bg-path" value="{{ $template->background_image_path }}">
                            
                            <div class="props-label" style="padding: 10px 0 5px 0; background:none;">Background Config</div>
                            <div class="prop-table">
                                <div class="prop-item">
                                    <div class="prop-key">Is Printed</div>
                                    <div class="prop-val" style="padding-left:10px;">
                                        <input type="checkbox" id="bg-is-printed" {{ ($template->background_config['is_printed'] ?? false) ? 'checked' : '' }} onchange="updateBgConfig()">
                                    </div>
                                </div>
                                <div class="prop-item">
                                    <div class="prop-key">Opacity</div>
                                    <div class="prop-val">
                                        <input type="number" id="bg-opacity" value="{{ $template->background_config['opacity'] ?? 40 }}" min="0" max="100" oninput="updateBgConfig()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-sections" class="tab-panel">
                <div class="props-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Sections</span>
                    <div style="display:flex; gap:4px;">
                        <button onclick="addSection()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Add Section">+</button>
                        <button onclick="resetSectionDefaults()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Reset to Defaults">↺</button>
                    </div>
                </div>
                <div id="sections-list" style="padding:8px;"></div>
            </div>

            <div id="tab-layers" class="tab-panel">
                <div class="props-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Layers</span>
                    <div style="display:flex; gap:4px;">
                        <button onclick="moveLayerUp()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Move Up">▲</button>
                        <button onclick="moveLayerDown()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Move Down">▼</button>
                        <button onclick="bringToFront()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Bring to Front">⤒</button>
                        <button onclick="sendToBack()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Send to Back">⤓</button>
                    </div>
                </div>
                <div id="layers-list"></div>
            </div>

            <div id="tab-data" class="tab-panel">
                <div class="props-header">Global Styles</div>
                <div id="styles-list" style="padding:1rem; border-bottom:1px solid var(--border);">
                    <button onclick="addStyle()" class="btn btn-secondary btn-sm" style="width:100%">+ New Style</button>
                    <div id="styles-container" style="margin-top:0.5rem;"></div>
                </div>

                <div class="props-header">Data Schema Integration</div>
                <div id="schema-outdated-banner" class="schema-outdated-banner" style="display:none;">⚠️ <span id="schema-outdated-msg"></span></div>
                <div style="padding:1rem; border-bottom:1px solid var(--border);">
                    <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:5px;">Assigned Schema</label>
                    <select id="data-schema-select" class="form-control" onchange="loadSelectedSchema()">
                        <option value="">-- No Schema --</option>
                        @foreach($schemas ?? [] as $s)
                            <option value="{{ $s->id }}" {{ ($template->data_schema_id ?? '') == $s->id ? 'selected' : '' }}>
                                {{ $s->label ?: $s->schema_name }} (v{{ $s->version }})
                            </option>
                        @endforeach
                    </select>
                    <div id="schema-fields-container" style="margin-top:10px; font-size:11px; max-height:200px; overflow-y:auto; padding-right:5px;"></div>
                    <button id="load-history-btn" onclick="openJobHistoryModal()" class="btn btn-secondary btn-sm" style="width:100%; margin-top:10px; display:none;">📦 Load from Job History</button>
                </div>

                <div class="props-header">Sample JSON Explorer</div>
                <div style="padding:1rem;">
                    <textarea id="json-input" placeholder="Paste Sample JSON here..." style="width:100%; height:80px; background:var(--bg); border:1px solid var(--border); color:var(--text); font-family:monospace; font-size:10px; padding:8px; border-radius:4px;"></textarea>
                    <button onclick="parseJSON()" class="btn btn-secondary btn-sm" style="width:100%; margin-top:0.5rem;">Parse JSON</button>
                    <div id="json-tree" style="margin-top:1rem; font-size:0.75rem; font-family:monospace;"></div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Multi-Page Preview Overlay -->
<div id="previewOverlay" class="fixed inset-0" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:3000;">
    <div style="position:absolute; inset:10px; background:var(--surface); border-radius:12px; display:flex; flex-direction:column; overflow:hidden; border:1px solid var(--border);">
        <!-- Preview Toolbar -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 16px; border-bottom:1px solid var(--border); background:var(--bg);">
            <div style="display:flex; align-items:center; gap:8px;">
                <button id="prevPage" onclick="previewPrevPage()" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); cursor:pointer; font-size:14px;">◀</button>
                <input id="pageInput" type="number" min="1" value="1" style="width:60px; text-align:center; border:1px solid var(--border); border-radius:4px; padding:6px; background:var(--bg); color:var(--text); font-size:14px;">
                <span id="pageCount" style="font-size:13px; color:var(--text-muted);">of 1</span>
                <button id="nextPage" onclick="previewNextPage()" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); cursor:pointer; font-size:14px;">▶</button>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <select id="zoomSelect" onchange="previewChangeZoom(this.value)" style="border:1px solid var(--border); border-radius:4px; padding:6px; background:var(--bg); color:var(--text); font-size:12px;">
                    <option value="0.5">50%</option>
                    <option value="0.75">75%</option>
                    <option value="1" selected>100%</option>
                    <option value="1.5">150%</option>
                    <option value="2">200%</option>
                    <option value="fit">Fit Width</option>
                </select>
                <button onclick="previewDownloadPdf()" style="padding:6px 14px; background:var(--primary); color:white; border:none; border-radius:6px; cursor:pointer; font-size:12px; font-weight:600;">⬇ Download PDF</button>
                <button onclick="closePreviewOverlay()" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); cursor:pointer; font-size:14px;">✕</button>
            </div>
        </div>
        <!-- Preview Canvas Container -->
        <div id="previewCanvasContainer" style="flex:1; overflow:auto; background:#1a1a2e; display:flex; justify-content:center; padding:20px;">
            <div style="position:relative; display:inline-block;">
                <canvas id="previewCanvas" style="box-shadow:0 4px 30px rgba(0,0,0,0.5); background:white;"></canvas>
                <div id="previewLoading" style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.8); display:flex; align-items:center; justify-content:center; border-radius:4px;">
                    <div style="text-align:center; color:#64748b;">
                        <div style="font-size:24px; margin-bottom:8px;">⏳</div>
                        <div style="font-size:13px; font-weight:500;">Rendering page...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Print Modal -->
<div id="test-print-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:var(--surface); width:400px; border-radius:12px; padding:1.5rem; border:1px solid var(--border);">
        <h3 style="margin:0 0 1rem 0; font-size:1.1rem;">Test Print</h3>
        <div style="display:flex; flex-direction:column; gap:1.2rem;">
            <div>
                <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.4rem; font-weight:500;">Target Agent</label>
                <select id="test-agent-id" onchange="updatePrinterDropdown(this.value)" style="width:100%; background:var(--bg); color:var(--text); border:1px solid var(--border); padding:10px; border-radius:6px; font-size:0.9rem; outline:none; transition:border-color 0.2s;">
                    <option value="">Select Agent</option>
                    @foreach(\App\Models\PrintAgent::all() as $agent)
                        <option value="{{ $agent->id }}">{{ $agent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:0.4rem; font-weight:500;">Printer Name</label>
                <select id="test-printer-name" style="width:100%; background:var(--bg); color:var(--text); border:1px solid var(--border); padding:10px; border-radius:6px; font-size:0.9rem; outline:none; transition:border-color 0.2s;">
                    <option value="">Select Printer</option>
                </select>
            </div>
            <div style="margin-top:0.5rem; display:flex; gap:0.75rem;">
                <button onclick="doTestPrint()" class="btn btn-primary" style="flex:2; padding:10px; font-weight:600;">🚀 Send Print Job</button>
                <button onclick="closeTestPrint()" class="btn btn-secondary" style="flex:1; padding:10px;">Cancel</button>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Sample Data Panel -->
<div id="sampleDataPanel" style="display:none; position:fixed; top:0; right:0; width:480px; height:100vh; background:var(--surface); z-index:2500; border-left:1px solid var(--border); box-shadow:-4px 0 20px rgba(0,0,0,0.3); flex-direction:column; overflow:hidden;">
    <div style="padding:14px 16px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; background:var(--bg);">
        <h3 style="margin:0; font-size:14px; font-weight:600; color:var(--text);">📋 Sample Data Editor</h3>
        <button onclick="toggleSampleDataPanel()" style="padding:4px 10px; border:1px solid var(--border); border-radius:4px; background:var(--surface); color:var(--text); cursor:pointer; font-size:12px;">✕</button>
    </div>
    <div style="flex:1; overflow-y:auto; padding:12px 16px;">
        <!-- Toolbar -->
        <div style="display:flex; gap:6px; margin-bottom:12px; flex-wrap:wrap;">
            <button onclick="sampleDataAddRow()" class="btn btn-primary btn-sm" style="font-size:11px;">+ Add Row</button>
            <button onclick="sampleDataImportCsv()" class="btn btn-secondary btn-sm" style="font-size:11px;">📄 Import CSV</button>
            <input type="file" id="csvFileInput" accept=".csv" style="display:none" onchange="sampleDataParseCsv(this)">
            <button onclick="sampleDataLoadFromJobHistory()" class="btn btn-secondary btn-sm" style="font-size:11px;">📦 From Job</button>
            <button onclick="sampleDataSaveToServer()" class="btn btn-success btn-sm" style="font-size:11px;">💾 Save as Default</button>
            <button onclick="sampleDataReset()" class="btn btn-danger btn-sm" style="font-size:11px;">↺ Reset</button>
        </div>
        <!-- Action buttons: Apply / Close -->
        <div style="display:flex; gap:6px; margin-bottom:12px;">
            <button onclick="sampleDataApply()" class="btn btn-primary btn-sm" style="flex:1; font-size:12px;">✅ Apply Data</button>
        </div>
        <!-- Table editor -->
        <div style="overflow-x:auto; border:1px solid var(--border); border-radius:6px;">
            <table id="sampleDataTable" style="width:100%; border-collapse:collapse; font-size:11px;">
                <thead id="sampleDataThead">
                    <tr style="background:var(--bg);">
                        <th style="padding:6px 8px; border-bottom:2px solid var(--border); color:var(--text-muted); font-weight:600; text-align:left; min-width:30px;">#</th>
                        <th style="padding:6px 8px; border-bottom:2px solid var(--border); color:var(--text-muted); font-weight:600; text-align:left;">Field</th>
                        <th style="padding:6px 8px; border-bottom:2px solid var(--border); color:var(--text-muted); font-weight:600; text-align:left;">Value</th>
                        <th style="padding:6px 8px; border-bottom:2px solid var(--border); color:var(--text-muted); font-weight:600; text-align:center; width:30px;">×</th>
                    </tr>
                </thead>
                <tbody id="sampleDataTbody">
                </tbody>
            </table>
        </div>
        <div style="margin-top:8px; font-size:10px; color:var(--text-muted);">
            <span>💡 Use dot notation for nested fields (e.g., <code>customer.name</code>)</span>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div id="ctx-menu">
    <div class="ctx-item" onclick="ctxDuplicate()">⧉ Duplicate</div>
    <div class="ctx-item" onclick="ctxBringFront()">⤒ Bring to Front</div>
    <div class="ctx-item" onclick="ctxSendBack()">⤓ Send to Back</div>
    <div class="ctx-separator"></div>
    <div class="ctx-item" onclick="ctxLock()">🔒 Lock/Unlock</div>
    <div class="ctx-item" onclick="ctxToggleVisible()">👁 Toggle Visibility</div>
    <div class="ctx-separator"></div>
    <div class="ctx-item" onclick="ctxGroup()">📦 Group</div>
    <div class="ctx-separator"></div>
    <div class="ctx-item danger" onclick="ctxDelete()">🗑 Delete</div>
</div>
<div id="coord-tip"></div>

<script>
    const availableSchemas = @json($schemas ?? []);
    const templateId = "{{ $template->id ?? '' }}";

    // ── Conditional Formatting ────────────────────────────────
    const CONDITIONAL_OPERATORS = [
        { value: 'equals', label: '=' },
        { value: 'not_equals', label: '≠' },
        { value: 'greater_than', label: '>' },
        { value: 'less_than', label: '<' },
        { value: 'greater_equal', label: '≥' },
        { value: 'less_equal', label: '≤' },
        { value: 'between', label: 'Between' },
        { value: 'contains', label: 'Contains' },
        { value: 'starts_with', label: 'Starts with' },
        { value: 'ends_with', label: 'Ends with' },
        { value: 'is_null', label: 'Is null' },
        { value: 'is_not_null', label: 'Is not null' },
    ];

    function getSchemaFieldKeys() {
        const activeSchemaId = document.getElementById('data-schema-select')?.value;
        const activeSchema = availableSchemas.find(s => s.id == activeSchemaId);
        if (activeSchema && activeSchema.fields) {
            return Object.keys(activeSchema.fields);
        }
        // Fallback: try to extract fields from sample data
        try {
            const sampleData = JSON.parse(document.getElementById('json-input').value || '{}');
            return Object.keys(sampleData).filter(k => typeof sampleData[k] !== 'object' || sampleData[k] === null);
        } catch(e) {}
        return [];
    }
    const BASE_SCALE = 4;
    let zoomLevel = 1.0;
    let elements = @json($template->elements ?? []);
    let globalStyles = @json($template->styles ?? []);

    // ── Sections ─────────────────────────────────────────────
    const SECTION_ORDER = ['pageHeader', 'reportHeader', 'detail', 'reportFooter', 'pageFooter'];
    const SECTION_COLORS = {
        pageHeader: '#f3f4f6',
        reportHeader: '#dbeafe',
        detail: '#ffffff',
        reportFooter: '#dbeafe',
        pageFooter: '#f3f4f6',
    };
    const SECTION_LABELS = {
        pageHeader: 'Page Header',
        reportHeader: 'Report Header',
        detail: 'Detail',
        reportFooter: 'Report Footer',
        pageFooter: 'Page Footer',
    };
    const SECTION_DEFAULTS = {
        pageHeader: { enabled: true, height: 15, elements: [], suppressIfBlank: false, keepWithBody: false },
        reportHeader: { enabled: false, height: 20, elements: [], suppressIfBlank: true, keepWithBody: false },
        detail: { enabled: true, height: 10, elements: [], keepTogether: false },
        reportFooter: { enabled: false, height: 15, elements: [], suppressIfBlank: true, keepWithBody: false },
        pageFooter: { enabled: true, height: 10, elements: [], suppressIfBlank: false, keepWithBody: false },
    };

    let sections = null;
    let sectionResizing = null;

    function initSections(rawElements) {
        if (!rawElements || typeof rawElements !== 'object') {
            sections = JSON.parse(JSON.stringify(SECTION_DEFAULTS));
            sections.detail.elements = [];
            return sections;
        }
        if (rawElements.sections) {
            sections = JSON.parse(JSON.stringify(rawElements.sections));
            SECTION_ORDER.forEach(key => {
                if (!sections[key]) {
                    sections[key] = JSON.parse(JSON.stringify(SECTION_DEFAULTS[key]));
                }
            });
            return sections;
        }
        // Legacy flat format — put all in detail
        sections = JSON.parse(JSON.stringify(SECTION_DEFAULTS));
        if (Array.isArray(rawElements)) {
            sections.detail.elements = JSON.parse(JSON.stringify(rawElements));
        }
        return sections;
    }

    function flattenSections() {
        const all = [];
        SECTION_ORDER.forEach(key => {
            const sec = sections[key];
            if (sec && sec.elements) {
                sec.elements.forEach(el => all.push(el));
            }
        });
        return all;
    }

    function getSectionAtY(y_mm) {
        let cumulativeY = 0;
        for (const key of SECTION_ORDER) {
            const section = sections[key];
            if (!section || !section.enabled) continue;
            const h = section.height;
            if (y_mm >= cumulativeY && y_mm < cumulativeY + h) return key;
            cumulativeY += h + 2;
        }
        return 'detail';
    }

    function getSectionOffset(key) {
        let offset = 0;
        for (const k of SECTION_ORDER) {
            if (k === key) return offset;
            const sec = sections[k];
            if (sec && sec.enabled) offset += sec.height + 2;
        }
        return offset;
    }

    function getTotalSectionsHeight() {
        let total = 0;
        SECTION_ORDER.forEach(key => {
            const sec = sections[key];
            if (sec && sec.enabled) total += sec.height + 2;
        });
        return Math.max(total - 2, 10);
    }

    // ── Sections Panel ────────────────────────────────────────
    let selectedSection = null;

    function findElementSection(elId) {
        for (const key of SECTION_ORDER) {
            const sec = sections[key];
            if (sec && sec.elements) {
                const found = sec.elements.find(e => e.id === elId);
                if (found) return key;
            }
        }
        return null;
    }

    function removeElementFromAllSections(elId) {
        for (const key of SECTION_ORDER) {
            const sec = sections[key];
            if (sec && sec.elements) {
                const idx = sec.elements.findIndex(e => e.id === elId);
                if (idx !== -1) {
                    sec.elements.splice(idx, 1);
                    return;
                }
            }
        }
    }

    function updateSectionsList() {
        const container = document.getElementById('sections-list');
        if (!container) return;
        container.innerHTML = '';
        SECTION_ORDER.forEach(key => {
            const sec = sections[key];
            if (!sec) return;
            const div = document.createElement('div');
            div.style.cssText = 'margin-bottom:8px; border-radius:6px; border:1px solid var(--border); overflow:hidden; cursor:pointer;';
            div.onclick = () => showSectionInspector(key);
            const color = SECTION_COLORS[key] || '#f3f4f6';
            div.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px; padding:8px 10px; background:${color}22; border-bottom:1px solid var(--border);">
                    <div style="width:14px; height:14px; border-radius:3px; background:${color}; border:1px solid rgba(0,0,0,0.1);"></div>
                    <span style="flex:1; font-size:12px; font-weight:600; color:var(--text);">${SECTION_LABELS[key]}</span>
                    <span style="font-size:10px; color:var(--text-muted);">${sec.height}mm</span>
                    <label style="font-size:10px; display:flex; align-items:center; gap:3px; color:var(--text-muted); cursor:pointer;" onclick="event.stopPropagation()">
                        <input type="checkbox" ${sec.enabled ? 'checked' : ''} onchange="toggleSection('${key}', this.checked)">
                        Show
                    </label>
                </div>
                <div style="padding:4px 10px; font-size:10px; color:var(--text-muted); background:rgba(0,0,0,0.1);">
                    ${sec.elements ? sec.elements.length : 0} element(s)
                </div>
            `;
            container.appendChild(div);
        });
    }

    function toggleSection(key, enabled) {
        pushHistory();
        sections[key].enabled = enabled;
        selectedSection = key;
        updateCanvasSize();
        renderElements();
        updateSectionsList();
        showSectionInspector(key);
    }

    function addSection() {
        // Sections are fixed; this is a placeholder for future custom sections
        switchTab('sections');
    }

    function resetSectionDefaults() {
        if (!confirm('Reset all sections to default heights and visibility?')) return;
        pushHistory();
        SECTION_ORDER.forEach(key => {
            const def = SECTION_DEFAULTS[key];
            sections[key].enabled = def.enabled;
            sections[key].height = def.height;
            sections[key].suppressIfBlank = def.suppressIfBlank || false;
            sections[key].keepWithBody = def.keepWithBody || false;
            if (sections[key].keepTogether !== undefined) {
                sections[key].keepTogether = def.keepTogether || false;
            }
        });
        updateCanvasSize();
        renderElements();
        updateSectionsList();
        showSectionInspector(selectedSection);
    }

    function showSectionInspector(key) {
        if (!key || !sections[key]) {
            document.getElementById('inspector-content').innerHTML =
                '<div style="text-align:center; padding:3rem 1rem; color:var(--text-muted); font-size:0.8rem;">Select an object or section</div>';
            return;
        }
        selectedSection = key;
        const sec = sections[key];
        const color = SECTION_COLORS[key] || '#f3f4f6';
        const isDetail = key === 'detail';
        const isPageFooter = key === 'pageFooter';
        const isPageHeader = key === 'pageHeader';
        document.getElementById('inspector-content').innerHTML = `
            <div class="props-header" style="display:flex; align-items:center; gap:8px; background:${color}33;">
                <div style="width:12px; height:12px; border-radius:2px; background:${color}; border:1px solid rgba(0,0,0,0.1);"></div>
                <span>${SECTION_LABELS[key]}</span>
            </div>
            <div class="prop-table">
                <div class="prop-item">
                    <div class="prop-key">Enabled</div>
                    <div class="prop-val" style="padding-left:10px;">
                        <input type="checkbox" ${sec.enabled ? 'checked' : ''} onchange="toggleSection('${key}', this.checked)">
                    </div>
                </div>
                <div class="prop-item">
                    <div class="prop-key">Height (mm)</div>
                    <div class="prop-val">
                        <input type="number" value="${sec.height}" min="2" max="200" step="0.5"
                               onchange="updateSectionHeight('${key}', parseFloat(this.value) || 10)">
                    </div>
                </div>
                ${!isDetail && !isPageHeader && !isPageFooter ? `
                <div class="prop-item">
                    <div class="prop-key">Suppress if Blank</div>
                    <div class="prop-val" style="padding-left:10px;">
                        <input type="checkbox" ${sec.suppressIfBlank ? 'checked' : ''} onchange="sections['${key}'].suppressIfBlank = this.checked; updateSectionsList();">
                    </div>
                </div>
                <div class="prop-item">
                    <div class="prop-key">Keep with Body</div>
                    <div class="prop-val" style="padding-left:10px;">
                        <input type="checkbox" ${sec.keepWithBody ? 'checked' : ''} onchange="sections['${key}'].keepWithBody = this.checked; updateSectionsList();">
                    </div>
                </div>
                ` : ''}
                ${isDetail ? `
                <div class="prop-item">
                    <div class="prop-key">Keep Together</div>
                    <div class="prop-val" style="padding-left:10px;">
                        <input type="checkbox" ${sec.keepTogether ? 'checked' : ''} onchange="sections['${key}'].keepTogether = this.checked; updateSectionsList();">
                    </div>
                </div>
                ` : ''}
                <div class="prop-item">
                    <div class="prop-key">Elements</div>
                    <div class="prop-val" style="padding-left:10px; font-size:11px; color:var(--text-muted);">
                        ${sec.elements ? sec.elements.length : 0}
                    </div>
                </div>
            </div>
        `;
    }

    function updateSectionHeight(key, newHeight) {
        if (!sections[key]) return;
        pushHistory();
        sections[key].height = Math.max(2, Math.min(200, newHeight));
        updateCanvasSize();
        renderElements();
        updateSectionsList();
        showSectionInspector(key);
    }

    let backgroundConfig = @json($template->background_config ?? ['is_printed' => false, 'opacity' => 40]);
    let activeId = null;
    let activeIds = [];
    let draggingEl = null, resizingEl = null, resizeHandle = null;
    let startX, startY, startW, startH, startMouseX, startMouseY;
    let snapEnabled = false, SNAP_MM = 2;
    let undoStack = [], redoStack = [];
    let rubberBanding = false, rbStartX, rbStartY;
    let liveDataMode = false;
    let smartGuidesEnabled = false;
    let sampleDataCache = {};
    let sampleDataRows = [];
    let sampleDataFields = [];
    const GUIDE_PROXIMITY_MM = 2;
    let availableFonts = [];

    // PDF.js preview state
    let pdfDoc = null;
    let previewCurrentPage = 1;
    let previewCurrentZoom = 1;
    let previewPdfData = null;

    // ── Font Management ──────────────────────────────────────
    async function loadFontForPreview(fontFamily, fontUrl) {
        try {
            const font = new FontFace(fontFamily, `url(${fontUrl})`);
            await font.load();
            document.fonts.add(font);
            return true;
        } catch (e) {
            console.warn('Font load failed:', fontFamily, e);
            return false;
        }
    }

    async function fetchAndPopulateFonts() {
        try {
            const response = await fetch('/api/v1/fonts');
            availableFonts = await response.json();
            const select = document.getElementById('propFontFamily');
            if (!select) return;
            // Keep Arial as first option
            select.innerHTML = '<option value="Arial">Arial (Default)</option>';
            for (const font of availableFonts) {
                const opt = document.createElement('option');
                opt.value = font.font_family;
                opt.textContent = font.name;
                opt.dataset.filePath = font.file_path;
                select.appendChild(opt);
                // Pre-load font for canvas preview
                const fontUrl = `/fonts/${font.id}/preview`;
                await loadFontForPreview(font.font_family, fontUrl);
            }
        } catch (e) {
            console.warn('Failed to fetch fonts:', e);
        }
    }

    // ── Undo / Redo ──────────────────────────────────────────
    function pushHistory() {
        const state = { elements: JSON.parse(JSON.stringify(elements)), sections: JSON.parse(JSON.stringify(sections)) };
        undoStack.push(JSON.stringify(state));
        if (undoStack.length > 60) undoStack.shift();
        redoStack = [];
        updateUndoButtons();
    }
    function undo() {
        if (!undoStack.length) return;
        const currentState = { elements: JSON.parse(JSON.stringify(elements)), sections: JSON.parse(JSON.stringify(sections)) };
        redoStack.push(JSON.stringify(currentState));
        const state = JSON.parse(undoStack.pop());
        elements = state.elements || [];
        sections = state.sections || JSON.parse(JSON.stringify(SECTION_DEFAULTS));
        activeIds = []; activeId = null;
        renderElements(); updateInspector(); updateUndoButtons();
    }
    function redo() {
        if (!redoStack.length) return;
        const currentState = { elements: JSON.parse(JSON.stringify(elements)), sections: JSON.parse(JSON.stringify(sections)) };
        undoStack.push(JSON.stringify(currentState));
        const state = JSON.parse(redoStack.pop());
        elements = state.elements || [];
        sections = state.sections || JSON.parse(JSON.stringify(SECTION_DEFAULTS));
        activeIds = []; activeId = null;
        renderElements(); updateInspector(); updateUndoButtons();
    }
    function updateUndoButtons() {
        document.getElementById('undo-btn').disabled = !undoStack.length;
        document.getElementById('redo-btn').disabled = !redoStack.length;
    }

    // ── Snap ─────────────────────────────────────────────────
    function toggleSnap() {
        snapEnabled = !snapEnabled;
        const btn = document.getElementById('snap-btn');
        btn.style.borderColor = snapEnabled ? 'var(--primary)' : '';
        btn.style.color = snapEnabled ? 'var(--primary)' : '';
        drawSnapGrid();
    }
    function snapVal(v) { return snapEnabled ? Math.round(v / SNAP_MM) * SNAP_MM : v; }
    function drawSnapGrid() {
        const cv = document.getElementById('snap-grid');
        const c = document.getElementById('canvas');
        cv.width = c.offsetWidth; cv.height = c.offsetHeight;
        cv.style.display = snapEnabled ? 'block' : 'none';
        if (!snapEnabled) return;
        const ctx = cv.getContext('2d');
        ctx.clearRect(0, 0, cv.width, cv.height);
        ctx.strokeStyle = 'rgba(59,130,246,0.12)';
        ctx.lineWidth = 1;
        const step = SNAP_MM * BASE_SCALE * zoomLevel;
        for (let x = 0; x < cv.width; x += step) { ctx.beginPath(); ctx.moveTo(x,0); ctx.lineTo(x,cv.height); ctx.stroke(); }
        for (let y = 0; y < cv.height; y += step) { ctx.beginPath(); ctx.moveTo(0,y); ctx.lineTo(cv.width,y); ctx.stroke(); }
    }

    // ── Minimap ───────────────────────────────────────────────
    function drawMinimap() {
        const mc = document.getElementById('minimap-canvas');
        const ctx = mc.getContext('2d');
        const W = mc.width, H = mc.height;
        const pw = parseFloat(document.getElementById('paper-w').value) || 215.9;
        const ph = parseFloat(document.getElementById('paper-h').value) || 139.7;
        ctx.clearRect(0, 0, W, H);
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);
        const sx = W / pw, sy = H / ph;
        const allEls = flattenSections();
        allEls.forEach(el => {
            if (el.hidden) return;
            ctx.fillStyle = el.type === 'label' ? '#64748b' : el.type === 'table' ? '#3b82f6' : el.type === 'line' ? '#ef4444' : el.type === 'running_total' ? '#818cf8' : '#0ea5e9';
            ctx.fillRect(el.x * sx, el.y * sy, (el.width || 1) * sx + 1, (el.height || 1) * sy + 1);
        });
    }

    // ── Schema Integration ───────────────────────────────────────
    function loadSelectedSchema() {
        const schemaId = document.getElementById('data-schema-select').value;
        const container = document.getElementById('schema-fields-container');
        const historyBtn = document.getElementById('load-history-btn');
        container.innerHTML = '';
        
        if (!schemaId) {
            historyBtn.style.display = 'none';
            sampleDataCache = {};
            return;
        }

        const schema = availableSchemas.find(s => s.id == schemaId);
        if (!schema) return;

        historyBtn.style.display = templateId ? 'block' : 'none';

        if (schema.sample_data && Object.keys(schema.sample_data).length > 0) {
            sampleDataCache = schema.sample_data;
            document.getElementById('json-input').value = JSON.stringify(schema.sample_data, null, 2);
            parseJSON();
            document.getElementById('preview-json').value = JSON.stringify(schema.sample_data, null, 2);
        }

        const usedKeys = elements.filter(e => e.type === 'field').map(e => e.key);
        const usedTables = elements.filter(e => e.type === 'table').map(e => e.key);

        let html = '';
        // Fields with type badges
        const fields = schema.fields || {};
        if (Object.keys(fields).length > 0) {
            html += '<div style="font-weight:bold; margin-bottom:4px; color:var(--text);">Fields</div>';
            html += '<div style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px;">';
            for (const [key, meta] of Object.entries(fields)) {
                const type = meta.type || 'string';
                const format = meta.format || '';
                const typeClass = format === 'currency' ? 'currency' : type;
                const isUsed = usedKeys.includes(key);
                const opacity = isUsed ? 'opacity:0.5;' : '';
                const icon = isUsed ? '✓' : '➕';
                html += `<span class="badge-delphi" style="cursor:pointer;${opacity}" onclick="addFieldFromSchema('${key}', 'field')" title="${meta.label || key} (${type}${format ? ':'+format : ''})${meta.required ? ' *required' : ''}">${icon} ${key}<span class="field-type-tag ${typeClass}">${type}</span></span>`;
            }
            html += '</div>';
        }

        // Tables
        const tables = schema.tables || {};
        if (Object.keys(tables).length > 0) {
            html += '<div style="font-weight:bold; margin-bottom:4px; color:var(--text);">Tables</div>';
            html += '<div style="display:flex; flex-wrap:wrap; gap:4px;">';
            for (const [key, meta] of Object.entries(tables)) {
                const cols = meta.columns || {};
                const colsSafe = encodeURIComponent(JSON.stringify(cols));
                const isUsed = usedTables.includes(key);
                const opacity = isUsed ? 'opacity:0.5;' : '';
                const icon = isUsed ? '✓' : '▦';
                html += `<span class="badge-delphi" style="cursor:pointer; background:rgba(59,130,246,0.15); color:#2563eb;${opacity}" onclick="addFieldFromSchema('${key}', 'table', '${colsSafe}')" title="${meta.label || key}">${icon} ${key}</span>`;
            }
            html += '</div>';
        }
        
        container.innerHTML = html;
        if (liveDataMode) renderElements();
    }

    function addFieldFromSchema(key, type, colsStr = null) {
        pushHistory();
        const el = {
            id: 'el_' + Date.now(),
            type: type,
            key: key,
            x: 20, y: 20,
            width: type === 'table' ? 150 : 40,
            height: type === 'table' ? 30 : 5,
            font_size: 10,
            bold: false,
            border: false,
            align: 'L',
            locked: false,
            hidden: false
        };

        if (type === 'table' && colsStr) {
            try {
                const colsDict = JSON.parse(decodeURIComponent(colsStr));
                el.columns = [];
                for (const [cKey, cMeta] of Object.entries(colsDict)) {
                    el.columns.push({
                        label: cMeta.label || cKey,
                        key: cKey,
                        width: 30,
                        align: 'L',
                        show_border: true
                    });
                }
            } catch(e) {}
        }
        elements.push(el);
        activeIds = [el.id]; activeId = el.id;
        renderElements();
        updateInspector();
        updateLayersList();
    }

    function openJobHistoryModal() {
        if (!templateId) return;
        fetch(`/templates/${templateId}/job-history`)
            .then(r => r.json())
            .then(data => {
                if (!data.jobs || data.jobs.length === 0) {
                    alert('No job history found for this template.');
                    return;
                }
                // Just load the most recent job data for now
                const recentJob = data.jobs[0];
                const sampleData = recentJob.template_data;
                document.getElementById('json-input').value = JSON.stringify(sampleData, null, 2);
                parseJSON();
                document.getElementById('preview-json').value = JSON.stringify(sampleData, null, 2);
                alert(`Loaded sample data from Job ${recentJob.job_id.substring(0, 8)} (${new Date(recentJob.created_at).toLocaleString()})`);
            });
    }

    // ── Init ─────────────────────────────────────────────────
    function init() {
        initSections(elements);
        // Ensure elements reference is the flat list from detail section for backward compat
        elements = sections.detail.elements;
        elements.forEach((el, idx) => {
            if (!el.id) el.id = 'el_' + Date.now() + '_' + idx;
            if (!el.fontFamily) el.fontFamily = 'Arial';
            if ((el.type === 'field' || el.type === 'label' || el.type === 'image') && el.rotation === undefined) {
                el.rotation = 0;
            }
        });
        updateCanvasSize(); renderElements(); renderStyles();
        loadSelectedSchema();
        fetchAndPopulateFonts();
        document.getElementById('canvas').addEventListener('mousedown', canvasMouseDown);
        document.getElementById('canvas').addEventListener('contextmenu', canvasContextMenu);
        document.addEventListener('click', () => hideCtxMenu());

        // Fetch sample data from server if template exists
        if (templateId) {
            fetch(`/templates/${templateId}/sample-data`)
                .then(r => r.json())
                .then(data => {
                    if (data.sample_data && Object.keys(data.sample_data).length > 0) {
                        sampleDataCache = data.sample_data;
                        document.getElementById('json-input').value = JSON.stringify(data.sample_data, null, 2);
                        parseJSON();
                        if (liveDataMode) renderElements();
                    }
                })
                .catch(err => console.warn('Could not load sample data:', err));
        }
    }

    // ── Live Data Preview ────────────────────────────────────
    function toggleLiveData() {
        liveDataMode = !liveDataMode;
        const btn = document.getElementById('live-data-btn');
        btn.classList.toggle('active', liveDataMode);
        // Try to load sample data from JSON input if cache is empty
        if (liveDataMode && Object.keys(sampleDataCache).length === 0) {
            try { sampleDataCache = JSON.parse(document.getElementById('json-input').value || '{}'); } catch(e) {}
        }
        renderElements();
    }

    function resolveDataValue(key, data) {
        if (!key || !data) return null;
        const keys = key.split('.');
        let val = data;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) val = val[k];
            else return null;
        }
        return val;
    }

    function formatValueJS(val, type, format, extra = {}) {
        if (val === null || val === undefined || val === '') return '';
        if (type === 'date') {
            try {
                const date = new Date(val);
                if (isNaN(date.getTime())) return val;
                // Simple date formatter
                const d = date.getDate().toString().padStart(2, '0');
                const m = (date.getMonth() + 1).toString().padStart(2, '0');
                const y = date.getFullYear();
                const yy = y.toString().slice(-2);
                const hrs = date.getHours().toString().padStart(2, '0');
                const mins = date.getMinutes().toString().padStart(2, '0');
                
                let pattern = format || 'dd/MM/yyyy';
                return pattern.replace('dd', d).replace('MM', m).replace('yyyy', y).replace('yy', yy).replace('HH', hrs).replace('mm', mins);
            } catch (e) { return val; }
        }
        if (type === 'number' || type === 'currency') {
            const num = parseFloat(val);
            if (isNaN(num)) return val;
            const decimals = extra.decimal_places !== undefined ? extra.decimal_places : 2;
            const formatted = num.toLocaleString('id-ID', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
            if (type === 'currency' || format === 'currency') {
                const symbol = extra.currency_symbol || 'Rp';
                return `${symbol} ${formatted}`;
            }
            return formatted;
        }
        return val;
    }

    function getLiveDisplayValue(el) {
        if (!liveDataMode || Object.keys(sampleDataCache).length === 0) return null;
        if (el.type === 'field') {
            let val = resolveDataValue(el.key, sampleDataCache);
            if (val !== null && val !== undefined) {
                if (el.format_type && el.format_type !== 'none') {
                    return formatValueJS(val, el.format_type, el.format_string, {
                        decimal_places: el.decimal_places,
                        currency_symbol: el.format_string // Reuse format_string for symbol in currency mode
                    });
                }
                return String(val);
            }
        }
        return null;
    }

    function getLiveTableRows(el) {
        if (!liveDataMode || Object.keys(sampleDataCache).length === 0) return null;
        if (el.type !== 'table') return null;
        const rows = resolveDataValue(el.key, sampleDataCache);
        return Array.isArray(rows) ? rows.slice(0, 3) : null;
    }

    // ── Smart Alignment Guides ──────────────────────────────
    function toggleSmartGuides() {
        smartGuidesEnabled = !smartGuidesEnabled;
        const btn = document.getElementById('guides-btn');
        btn.style.borderColor = smartGuidesEnabled ? 'var(--primary)' : '';
        btn.style.color = smartGuidesEnabled ? 'var(--primary)' : '';
    }

    function showSmartGuides(movingEl) {
        clearSmartGuides();
        if (!smartGuidesEnabled || !movingEl) return;
        const canvas = document.getElementById('canvas');
        const threshold = GUIDE_PROXIMITY_MM;
        const edges = { top: movingEl.y, bottom: movingEl.y + (movingEl.height || 5), left: movingEl.x, right: movingEl.x + movingEl.width, cx: movingEl.x + movingEl.width / 2, cy: movingEl.y + (movingEl.height || 5) / 2 };
        
        elements.forEach(other => {
            if (other.id === movingEl.id || other.hidden) return;
            const oe = { top: other.y, bottom: other.y + (other.height || 5), left: other.x, right: other.x + other.width, cx: other.x + other.width / 2, cy: other.y + (other.height || 5) / 2 };
            
            // Horizontal guides (matching Y positions)
            [['top','top'],['bottom','bottom'],['top','bottom'],['cy','cy']].forEach(([a,b]) => {
                if (Math.abs(edges[a] - oe[b]) < threshold) {
                    const guide = document.createElement('div');
                    guide.className = 'smart-guide smart-guide-h';
                    guide.style.top = (oe[b] * BASE_SCALE) + 'px';
                    canvas.appendChild(guide);
                }
            });
            // Vertical guides (matching X positions)
            [['left','left'],['right','right'],['left','right'],['cx','cx']].forEach(([a,b]) => {
                if (Math.abs(edges[a] - oe[b]) < threshold) {
                    const guide = document.createElement('div');
                    guide.className = 'smart-guide smart-guide-v';
                    guide.style.left = (oe[b] * BASE_SCALE) + 'px';
                    canvas.appendChild(guide);
                }
            });
        });
    }

    function clearSmartGuides() {
        document.querySelectorAll('.smart-guide').forEach(g => g.remove());
    }

    // ── Paper Presets ────────────────────────────────────────
    function applyPaperPreset() {
        const val = document.getElementById('paper-preset').value;
        if (!val) return;
        const [w, h] = val.split(',').map(Number);
        document.getElementById('paper-w').value = w;
        document.getElementById('paper-h').value = h;
        updateCanvasSize();
    }

    // ── Tab switch ───────────────────────────────────────────
    function switchTab(tab) {
        document.querySelectorAll('.tab-item').forEach(i => i.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const t = document.querySelector(`.tab-item[onclick*="${tab}"]`);
        if (t) t.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        if (tab === 'layers') updateLayersList();
        if (tab === 'sections') updateSectionsList();
        if (tab === 'props') showSectionInspector(selectedSection);
    }

    // ── JSON Explorer ────────────────────────────────────────
    function parseJSON() {
        const input = document.getElementById('json-input').value;
        try {
            const data = JSON.parse(input);
            const tree = document.getElementById('json-tree');
            tree.innerHTML = ''; renderJSONNode(data, '', tree);
            // Update sample data cache to reflect new JSON
            sampleDataCache = data;
            if (liveDataMode) renderElements();
        } catch(e) { alert('Invalid JSON'); }
    }
    function renderJSONNode(obj, path, container) {
        Object.keys(obj).forEach(key => {
            const fp = path ? `${path}.${key}` : key;
            const val = obj[key]; const div = document.createElement('div'); div.style.paddingLeft = '10px';
            if (typeof val === 'object' && val !== null) {
                div.innerHTML = `<span style="color:var(--text-muted)">▸</span> ${key}`;
                const sub = document.createElement('div'); renderJSONNode(val, fp, sub); div.appendChild(sub);
            } else {
                div.innerHTML = `<span class="badge-delphi" style="cursor:pointer" onclick="addFieldFromData('${fp}')">${key}</span>: <span style="color:var(--primary)">${val}</span>`;
            }
            container.appendChild(div);
        });
    }
    function addFieldFromData(path) {
        pushHistory();
        const id = 'el_new_' + Date.now();
        elements.push({ id, type: 'field', key: path, x: 50, y: 50, width: 60, height: 10, font_size: 10, bold: false, border: false, align: 'L' });
        renderElements(); selectElements([id]);
    }

    // ── Layer panel ──────────────────────────────────────────
    function updateLayersList() {
        const list = document.getElementById('layers-list'); list.innerHTML = '';
        [...elements].reverse().forEach((el, ridx) => {
            const idx = elements.length - 1 - ridx;
            const row = document.createElement('div');
            row.className = 'layer-row' + (activeIds.includes(el.id) ? ' active' : '');
            row.onclick = () => selectElements([el.id]);
            const typeIcon = el.type === 'table' ? '▦' : el.type === 'label' ? 'Aa' : el.type === 'line' ? '—' : el.type === 'running_total' ? 'Σ' : 'T';
            row.innerHTML = `
                <span style="color:var(--text-muted); font-size:10px; margin-right:6px;">${typeIcon}</span>
                <span class="lbl">${el.key || el.text || el.type}</span>
                <span class="layer-icon ${el.locked ? 'on' : ''}" onclick="toggleLock(event, '${el.id}')" title="Lock">${el.locked ? '🔒' : '🔓'}</span>
                <span class="layer-icon ${!el.hidden ? 'on' : ''}" onclick="toggleVisible(event, '${el.id}')" title="Visibility">${el.hidden ? '🙈' : '👁'}</span>
            `;
            list.appendChild(row);
        });
    }
    function toggleLock(e, id) {
        e.stopPropagation();
        const el = elements.find(i => i.id === id);
        if (el) { el.locked = !el.locked; updateLayersList(); }
    }
    function toggleVisible(e, id) {
        e.stopPropagation();
        const el = elements.find(i => i.id === id);
        if (el) { el.hidden = !el.hidden; renderElements(); updateLayersList(); }
    }
    function bringToFront() {
        if (!activeId) return; pushHistory();
        const idx = elements.findIndex(e => e.id === activeId);
        if (idx < elements.length - 1) { const [el] = elements.splice(idx, 1); elements.push(el); renderElements(); updateLayersList(); }
    }
    function sendToBack() {
        if (!activeId) return; pushHistory();
        const idx = elements.findIndex(e => e.id === activeId);
        if (idx > 0) { const [el] = elements.splice(idx, 1); elements.unshift(el); renderElements(); updateLayersList(); }
    }
    function moveLayerUp() {
        if (!activeId) return; pushHistory();
        const idx = elements.findIndex(e => e.id === activeId);
        if (idx < elements.length - 1) { [elements[idx], elements[idx+1]] = [elements[idx+1], elements[idx]]; renderElements(); updateLayersList(); }
    }
    function moveLayerDown() {
        if (!activeId) return; pushHistory();
        const idx = elements.findIndex(e => e.id === activeId);
        if (idx > 0) { [elements[idx], elements[idx-1]] = [elements[idx-1], elements[idx]]; renderElements(); updateLayersList(); }
    }

    // ── Zoom ─────────────────────────────────────────────────
    function changeZoom(delta, reset = false) {
        zoomLevel = reset ? 1.0 : Math.max(0.2, Math.min(3.0, zoomLevel + delta));
        document.getElementById('zoom-val').textContent = Math.round(zoomLevel * 100) + '%';
        updateCanvasSize(); renderElements();
    }
    function updateCanvasSize() {
        const w = parseFloat(document.getElementById('paper-w').value) || 215.9;
        const h = parseFloat(document.getElementById('paper-h').value) || 139.7;
        const totalSectionH = getTotalSectionsHeight();
        const canvasH = Math.max(h, totalSectionH + 10);
        const c = document.getElementById('canvas');
        c.style.width = (w * BASE_SCALE) + 'px';
        c.style.height = (canvasH * BASE_SCALE) + 'px';
        c.style.transform = `scale(${zoomLevel})`;
        drawSnapGrid(); drawMinimap();
    }

    // ── Add Element ──────────────────────────────────────────
    function addElement(type) {
        pushHistory();
        const id = 'el_new_' + Date.now();
        const centerX = 10, centerY = 10;
        const defaultStyles = { font_size: 10, fontFamily: 'Arial', bold: false, border: false, align: 'L' };
        let el = { id, type, key: '', x: centerX, y: centerY, width: 50, height: 10, font_size: 10, fontFamily: 'Arial', bold: false, border: false, align: 'L' };
        if (type === 'field')  { el.key = 'field_key'; el.rotation = 0; }
        if (type === 'label')  { el.key = ''; el.text = 'Label Text'; el.width = 60; el.rotation = 0; }
        if (type === 'table')  { el.key = 'items'; el.width = 180; el.columns = [{ label: 'Item', key: 'name', width: 100 }, { label: 'Qty', key: 'qty', width: 40, align: 'R' }]; }
        if (type === 'line')   { el.key = ''; el.width = 180; el.height = 0.5; el.lineColor = '#000000'; }
        if (type === 'image')  { el.key = ''; el.src = 'https://via.placeholder.com/150?text=Logo'; el.width = 30; el.height = 30; el.rotation = 0; }
        if (type === 'barcode') {
            el = { id, type: 'barcode', symbology: 'code128', value: '', showText: true, barWidth: 0, height_mm: 20, x: centerX, y: centerY, width: 80, height: 25, ...defaultStyles };
        }
        if (type === 'qrcode') {
            el = { id, type: 'qrcode', value: '', errorCorrection: 'M', x: centerX, y: centerY, size: 25, ...defaultStyles };
        }
        if (type === 'running_total') {
            el = {
                id, type: 'running_total',
                name: 'Running Total',
                field: '',
                operation: 'sum',
                reset: 'never',
                resetGroup: '',
                evaluate: 'on_change',
                x: centerX, y: centerY, width: 50, height: 6,
                fontSize: 10,
                fontFamily: 'Arial',
                format: { type: 'number', decimals: 2 }
            };
        }
        elements.push(el); renderElements(); selectElements([id]);
    }

    // ── Duplicate ────────────────────────────────────────────
    function duplicateSelected() {
        if (!activeIds.length) return; pushHistory();
        const newIds = [];
        activeIds.forEach(aid => {
            const orig = elements.find(e => e.id === aid); if (!orig) return;
            const copy = JSON.parse(JSON.stringify(orig));
            copy.id = 'el_dup_' + Date.now() + '_' + Math.random().toString(36).slice(2,6);
            copy.x += 5; copy.y += 5;
            elements.push(copy); newIds.push(copy.id);
        });
        renderElements(); selectElements(newIds);
    }

    // ── Test Print ──────────────────────────────────────────
    const agents_data = @json(\App\Models\PrintAgent::all());
    
    function updatePrinterDropdown(agentId) {
        const select = document.getElementById('test-printer-name');
        select.innerHTML = '<option value="">Select Printer</option>';
        if (!agentId) return;
        
        const agent = agents_data.find(a => a.id == agentId);
        if (agent && agent.printers) {
            agent.printers.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p; opt.textContent = p;
                select.appendChild(opt);
            });
        }
    }

    // ── Distribute ───────────────────────────────────────────
    function distributeH() {
        const sel = elements.filter(e => activeIds.includes(e.id)); if (sel.length < 3) return;
        pushHistory();
        sel.sort((a, b) => a.x - b.x);
        const minX = sel[0].x, maxX = sel[sel.length-1].x + sel[sel.length-1].width;
        const totalW = sel.reduce((s, e) => s + e.width, 0);
        const gap = (maxX - minX - totalW) / (sel.length - 1);
        let curX = minX;
        sel.forEach(e => { e.x = parseFloat(curX.toFixed(2)); curX += e.width + gap; });
        renderElements();
    }
    function distributeV() {
        const sel = elements.filter(e => activeIds.includes(e.id)); if (sel.length < 3) return;
        pushHistory();
        sel.sort((a, b) => a.y - b.y);
        const minY = sel[0].y, maxY = sel[sel.length-1].y + sel[sel.length-1].height;
        const totalH = sel.reduce((s, e) => s + e.height, 0);
        const gap = (maxY - minY - totalH) / (sel.length - 1);
        let curY = minY;
        sel.forEach(e => { e.y = parseFloat(curY.toFixed(2)); curY += e.height + gap; });
        renderElements();
    }

    // ── Canvas mouse events (deselect + rubber band) ─────────
    function canvasMouseDown(e) {
        if (e.target.classList.contains('design-element') || e.target.closest('.design-element')) return;
        if (e.button !== 0) return;
        // Deselect
        activeIds = []; activeId = null; renderElements(); updateInspector();
        // Rubber band
        const rect = document.getElementById('canvas').getBoundingClientRect();
        rbStartX = (e.clientX - rect.left) / zoomLevel;
        rbStartY = (e.clientY - rect.top) / zoomLevel;
        rubberBanding = true;
        const rb = document.getElementById('rubber-band');
        rb.style.left = rbStartX + 'px'; rb.style.top = rbStartY + 'px';
        rb.style.width = '0'; rb.style.height = '0'; rb.style.display = 'block';
    }

    // ── Mouse move (drag / resize / rubber band / tooltip) ───
    window.addEventListener('mousemove', (e) => {
        const dx = (e.clientX-startMouseX)/(BASE_SCALE*zoomLevel), dy = (e.clientY-startMouseY)/(BASE_SCALE*zoomLevel);
        if (draggingEl) {
            activeIds.forEach(id => {
                const el = elements.find(i => i.id === id); if (!el || el.locked) return;
                el.x = snapVal(parseFloat((el.origX+dx).toFixed(2)));
                el.y = snapVal(parseFloat((el.origY+dy).toFixed(2)));
                const div = document.querySelector(`.design-element[data-id="${el.id}"]`);
                if (div) { div.style.left = (el.x * BASE_SCALE) + 'px'; div.style.top = (el.y * BASE_SCALE) + 'px'; }
            });
            // Coord tooltip + smart guides
            const el = elements.find(i => i.id === activeId);
            if (el) {
                showCoordTip(e.clientX, e.clientY, el.x, el.y);
                showSmartGuides(el);
            }
            updateInspector();
        } else if (resizingEl) {
            if (resizeHandle.includes('e')) resizingEl.width = Math.max(1, snapVal(startW + dx));
            if (resizeHandle.includes('s')) resizingEl.height = Math.max(0.5, snapVal(startH + dy));
            if (resizeHandle.includes('w')) { const nw = Math.max(1, snapVal(startW-dx)); resizingEl.x = startX+(startW-nw); resizingEl.width = nw; }
            if (resizeHandle.includes('n')) { const nh = Math.max(0.5, snapVal(startH-dy)); resizingEl.y = startY+(startH-nh); resizingEl.height = nh; }
            const div = document.querySelector(`.design-element[data-id="${resizingEl.id}"]`);
            if (div) {
                div.style.left = (resizingEl.x * BASE_SCALE) + 'px'; div.style.top = (resizingEl.y * BASE_SCALE) + 'px';
                div.style.width = (resizingEl.width * BASE_SCALE) + 'px'; div.style.height = (resizingEl.height * BASE_SCALE) + 'px';
            }
            showCoordTip(e.clientX, e.clientY, resizingEl.width, resizingEl.height, 'W×H');
            updateInspector();
        } else if (rubberBanding) {
            const rect = document.getElementById('canvas').getBoundingClientRect();
            const cx = (e.clientX - rect.left) / zoomLevel, cy = (e.clientY - rect.top) / zoomLevel;
            const rb = document.getElementById('rubber-band');
            rb.style.left = Math.min(cx, rbStartX) + 'px'; rb.style.top = Math.min(cy, rbStartY) + 'px';
            rb.style.width = Math.abs(cx - rbStartX) + 'px'; rb.style.height = Math.abs(cy - rbStartY) + 'px';
        } else if (sectionResizing) {
            const dy = (e.clientY - sectionResizing.startY) / (BASE_SCALE * zoomLevel);
            const newH = Math.max(5, sectionResizing.startHeight + dy);
            sections[sectionResizing.key].height = parseFloat(newH.toFixed(1));
            updateCanvasSize();
            renderElements();
        } else {
            hideCoordTip();
        }
    });

    function showCoordTip(cx, cy, a, b, label = 'X,Y') {
        const t = document.getElementById('coord-tip');
        t.style.display = 'block'; t.style.left = (cx+14)+'px'; t.style.top = (cy-20)+'px';
        t.textContent = `${label}: ${parseFloat(a).toFixed(1)}, ${parseFloat(b).toFixed(1)}`;
    }
    function hideCoordTip() { document.getElementById('coord-tip').style.display = 'none'; }

    window.addEventListener('mouseup', (e) => {
        if (rubberBanding) {
            rubberBanding = false;
            document.getElementById('rubber-band').style.display = 'none';
            const rect = document.getElementById('canvas').getBoundingClientRect();
            const cx = (e.clientX - rect.left) / zoomLevel, cy = (e.clientY - rect.top) / zoomLevel;
            const x1 = Math.min(cx, rbStartX)/BASE_SCALE, y1 = Math.min(cy, rbStartY)/BASE_SCALE;
            const x2 = Math.max(cx, rbStartX)/BASE_SCALE, y2 = Math.max(cy, rbStartY)/BASE_SCALE;
            if (x2 - x1 > 2 || y2 - y1 > 2) {
                const hit = elements.filter(el => el.x < x2 && el.x+el.width > x1 && el.y < y2 && el.y+el.height > y1).map(e => e.id);
                if (hit.length) selectElements(hit);
            }
        }
        if (draggingEl) {
            // ── Section boundary detection on drop ──────────────
            const movedIds = [];
            // Collect all dragged element IDs
            activeIds.forEach(id => { if (!movedIds.includes(id)) movedIds.push(id); });
            // Also include draggingEl if not in activeIds
            if (draggingEl && !movedIds.includes(draggingEl.id)) movedIds.push(draggingEl.id);

            movedIds.forEach(id => {
                const sourceKey = findElementSection(id);
                if (!sourceKey) return;
                const el = sections[sourceKey].elements.find(e => e.id === id);
                if (!el) return;

                // Calculate global Y = section offset + element Y
                const srcOffset = getSectionOffset(sourceKey);
                const globalY = srcOffset + el.y + (el.height || 10) / 2;

                // Determine which section this globalY falls into
                let targetKey = null;
                let cumY = 0;
                for (const sk of SECTION_ORDER) {
                    const sec = sections[sk];
                    if (!sec || !sec.enabled) continue;
                    if (globalY >= cumY && globalY < cumY + sec.height) {
                        targetKey = sk;
                        break;
                    }
                    cumY += sec.height + 2;
                }
                if (!targetKey) targetKey = 'detail';

                if (sourceKey !== targetKey) {
                    // Move element from source section to target section
                    const elData = sections[sourceKey].elements.find(e => e.id === id);
                    if (elData) {
                        removeElementFromAllSections(id);
                        // Adjust Y to be relative to target section
                        const targetOffset = getSectionOffset(targetKey);
                        elData.y = parseFloat(Math.max(0, (globalY - (elData.height || 10) / 2 - targetOffset)).toFixed(2));
                        if (!sections[targetKey].elements) sections[targetKey].elements = [];
                        sections[targetKey].elements.push(elData);
                    }
                }
            });
        }
        if (draggingEl || resizingEl || sectionResizing) { pushHistory(); renderElements(); drawMinimap(); }
        draggingEl = null; resizingEl = null; sectionResizing = null; hideCoordTip(); clearSmartGuides();
    });

    // ── Render ───────────────────────────────────────────────
    function renderElements() {
        const c = document.getElementById('canvas');
        c.querySelectorAll('.design-element').forEach(el => el.remove());
        c.querySelectorAll('.section-band').forEach(el => el.remove());
        c.querySelectorAll('.section-resize-handle').forEach(el => el.remove());
        
        const activeSchemaId = document.getElementById('data-schema-select')?.value;
        const activeSchema = availableSchemas.find(s => s.id == activeSchemaId);
        let validKeys = []; let validTables = [];
        if (activeSchema) {
            validKeys = Object.keys(activeSchema.fields || {});
            validTables = Object.keys(activeSchema.tables || {});
        }

        let currentY = 0;
        const pw = parseFloat(document.getElementById('paper-w').value) || 215.9;

        SECTION_ORDER.forEach(sectionKey => {
            const section = sections[sectionKey];
            if (!section || !section.enabled) return;

            const sectionHeight = section.height;
            const sectionHpx = sectionHeight * BASE_SCALE;
            const currentYpx = currentY * BASE_SCALE;

            // Section band background
            const band = document.createElement('div');
            band.className = 'section-band';
            band.dataset.section = sectionKey;
            band.style.cssText = `
                position: absolute; left: 0; top: ${currentYpx}px;
                width: ${pw * BASE_SCALE}px; height: ${sectionHpx}px;
                background: ${SECTION_COLORS[sectionKey]};
                border: 1px dashed #d1d5db;
                box-sizing: border-box; pointer-events: none;
                z-index: 0;
            `;
            c.appendChild(band);

            // Section label (clickable for section properties)
            const label = document.createElement('div');
            label.className = 'section-label';
            label.dataset.section = sectionKey;
            label.style.cssText = `
                position: absolute; left: 4px; top: ${currentYpx + 2}px;
                font-size: 9px; font-family: sans-serif;
                color: #6b7280; pointer-events: auto; cursor: pointer;
                z-index: 1; user-select: none;
            `;
            label.textContent = SECTION_LABELS[sectionKey] + ` (${sectionHeight}mm)`;
            label.title = 'Click to edit section properties';
            label.onclick = (e) => {
                e.stopPropagation();
                showSectionInspector(sectionKey);
            };
            c.appendChild(label);

            // Render elements in this section
            const secEls = section.elements || [];
            secEls.forEach(el => {
                if (el.hidden) return;
                const displayEl = JSON.parse(JSON.stringify(el));
                if (displayEl.styleIdx !== undefined && globalStyles[displayEl.styleIdx]) {
                    const s = globalStyles[displayEl.styleIdx];
                    displayEl.font_size = s.font_size; displayEl.bold = s.bold;
                }
                const div = document.createElement('div');
                div.className = 'design-element';
                if (el.locked) div.style.cursor = 'not-allowed';
                div.setAttribute('data-id', displayEl.id);
                if (activeIds.includes(displayEl.id)) div.classList.add('active');
                div.style.left = (displayEl.x * BASE_SCALE) + 'px';
                div.style.top = ((displayEl.y + currentY) * BASE_SCALE) + 'px';
                div.style.width = (displayEl.width * BASE_SCALE) + 'px';
                div.style.height = ((displayEl.height || 10) * BASE_SCALE) + 'px';

                // Apply rotation
                if ((displayEl.type === 'field' || displayEl.type === 'label' || displayEl.type === 'image') && displayEl.rotation && displayEl.rotation != 0) {
                    const cx = (displayEl.width * BASE_SCALE) / 2;
                    const cy = ((displayEl.height || 10) * BASE_SCALE) / 2;
                    div.style.transform = `rotate(${displayEl.rotation}deg)`;
                    div.style.transformOrigin = `${cx}px ${cy}px`;
                }

                if (activeSchema && displayEl.type === 'field' && !validKeys.includes(displayEl.key) && displayEl.key) {
                    div.style.outline = '2px solid var(--danger)';
                    div.style.outlineOffset = '-2px';
                    div.title = 'Invalid field: ' + displayEl.key + ' is not in schema';
                }
                if (activeSchema && displayEl.type === 'table' && !validTables.includes(displayEl.key) && displayEl.key) {
                    div.style.outline = '2px solid var(--danger)';
                    div.style.outlineOffset = '-2px';
                    div.title = 'Invalid table: ' + displayEl.key + ' is not in schema';
                }

                if (displayEl.type === 'line') {
                    div.innerHTML = `<div style="width:100%; height:${Math.max(1, displayEl.height*BASE_SCALE)}px; background:${displayEl.lineColor||'#000'}; border-radius:1px;"></div>`;
                } else if (displayEl.type === 'label') {
                    if (displayEl.border) div.style.border = '1px solid #cbd5e1';
                    const labelFontFamily = displayEl.fontFamily || 'Arial';
                    div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; font-family:'${labelFontFamily}', sans-serif; color:#1e293b; padding:2px; height:100%; overflow:hidden; font-weight:${displayEl.bold?'bold':'normal'}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}; background:rgba(100,116,139,0.08);">${displayEl.text || 'Label'}</div>`;
                } else if (displayEl.type === 'table') {
                    if (displayEl.border) div.style.border = '1px solid #cbd5e1';
                    const cols = displayEl.columns || [];
                    const colsHtml = cols.map(c => `<td style="border:1px solid #94a3b8; padding:1px 3px; font-size:${displayEl.font_size*BASE_SCALE*0.18}px; font-weight:bold; color:#1e40af; white-space:nowrap; overflow:hidden;">${c.label}</td>`).join('');
                    const liveRows = getLiveTableRows(displayEl);
                    let rowsHtml = '';
                    if (liveRows && liveRows.length > 0) {
                        liveRows.forEach((row, ri) => {
                            const bg = ri % 2 === 0 ? '' : 'background:rgba(59,130,246,0.04);';
                            rowsHtml += '<tr>' + cols.map(c => {
                                let val = resolveDataValue(c.key, row) ?? '';
                                if (c.format_type && c.format_type !== 'none') {
                                    val = formatValueJS(val, c.format_type, c.format_string, {
                                        decimal_places: c.decimal_places,
                                        currency_symbol: c.format_string
                                    });
                                }
                                return `<td style="border:1px solid #e2e8f0; padding:1px 3px; font-size:${displayEl.font_size*BASE_SCALE*0.16}px; color:#334155;${bg}">${val}</td>`;
                            }).join('') + '</tr>';
                        });
                        div.classList.add('field-resolved');
                    } else {
                        rowsHtml = '<tr>' + cols.map(c => '<td style="border:1px solid #e2e8f0; padding:1px 3px; font-size:' + (displayEl.font_size*BASE_SCALE*0.16) + 'px; color:#64748b;">@{{' + c.key + '}}</td>').join('') + '</tr>';
                    }
                    div.innerHTML = `<table style="border-collapse:collapse; width:100%; table-layout:fixed;"><tr>${colsHtml}</tr>${rowsHtml}</table>`;
                } else if (displayEl.type === 'image') {
                    const img = document.createElement('img');
                    img.src = displayEl.src || 'https://via.placeholder.com/150?text=Image';
                    img.style.width = '100%'; img.style.height = '100%'; img.style.objectFit = 'contain'; img.style.pointerEvents = 'none';
                    div.appendChild(img);
                    if (displayEl.key) {
                        const badge = document.createElement('div');
                        badge.textContent = 'LINKED: ' + displayEl.key;
                        badge.style.position = 'absolute'; badge.style.bottom = '0'; badge.style.left = '0';
                        badge.style.background = 'rgba(59,130,246,0.8)'; badge.style.color = 'white';
                        badge.style.fontSize = '8px'; badge.style.padding = '1px 3px';
                        div.appendChild(badge);
                    }
                } else if (displayEl.type === 'barcode') {
                    div.style.border = '1px dashed #94a3b8';
                    div.style.background = 'rgba(241,245,249,0.6)';
                    const barcodeVal = displayEl.value || '(no value)';
                    const symLabel = displayEl.symbology || 'code128';
                    div.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2px;font-family:monospace;">
                        <div style="font-size:9px;color:#64748b;font-weight:bold;">[BARCODE]</div>
                        <div style="font-size:8px;color:#475569;margin-top:2px;">${symLabel}</div>
                        <div style="font-size:7px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">${barcodeVal}</div>
                    </div>`;
                } else if (displayEl.type === 'qrcode') {
                    div.style.border = '1px dashed #94a3b8';
                    div.style.background = 'rgba(241,245,249,0.6)';
                    const qrVal = displayEl.value || '(no value)';
                    div.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2px;font-family:monospace;">
                        <div style="font-size:9px;color:#64748b;font-weight:bold;">[QR CODE]</div>
                        <div style="font-size:7px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;">${qrVal}</div>
                    </div>`;
                } else if (displayEl.type === 'running_total') {
                    div.style.border = '1px dashed #818cf8';
                    div.style.background = 'rgba(129,140,248,0.1)';
                    const rtField = displayEl.field || '(no field)';
                    const rtOp = displayEl.operation || 'sum';
                    const opLabel = { sum: 'Sum', count: 'Count', average: 'Average', min: 'Min', max: 'Max' };
                    div.innerHTML = `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;padding:2px;font-family:monospace;">
                        <div style="font-size:9px;color:#6366f1;font-weight:bold;">[Σ Running Total]</div>
                        <div style="font-size:8px;color:#4f46e5;margin-top:2px;">${rtField} → ${opLabel[rtOp] || rtOp}</div>
                    </div>`;
                } else {
                    if (displayEl.border) div.style.border = '1px solid #1e293b';
                    const fieldFontFamily = displayEl.fontFamily || 'Arial';
                    const liveVal = getLiveDisplayValue(displayEl);
                    
                    // Evaluate conditional formatting from sample data
                    let condTextColor = null, condBgColor = null, condBold = null, condItalic = null, condUnderline = null;
                    if (liveDataMode && Object.keys(sampleDataCache).length > 0) {
                        const condStyles = getConditionalStyle(displayEl, sampleDataCache);
                        if (condStyles.length > 0) {
                            const s = condStyles[0];
                            if (s.color && s.color !== '#000000') condTextColor = s.color;
                            if (s.backgroundColor && s.backgroundColor !== '#FFFFFF') condBgColor = s.backgroundColor;
                            if (s.bold) condBold = true;
                            if (s.italic) condItalic = true;
                            if (s.underline) condUnderline = true;
                        }
                    }
                    
                    if (liveVal !== null) {
                        div.classList.add('field-resolved');
                        const textColor = condTextColor || '#0f172a';
                        const bgColor = condBgColor || 'transparent';
                        const fontWeight = condBold ? 'bold' : (displayEl.bold ? 'bold' : 'normal');
                        const fontStyle = condItalic ? 'italic' : 'normal';
                        const textDecor = condUnderline ? 'underline' : 'none';
                        div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; font-family:'${fieldFontFamily}', sans-serif; color:${textColor}; background:${bgColor}; padding:2px; height:100%; overflow:hidden; font-weight:${fontWeight}; font-style:${fontStyle}; text-decoration:${textDecor}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}">${liveVal}</div>`;
                    } else {
                        if (liveDataMode) div.classList.add('field-unresolved');
                        div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; font-family:'${fieldFontFamily}', sans-serif; color:#1e293b; padding:2px; height:100%; overflow:hidden; font-weight:${displayEl.bold?'bold':'normal'}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}">@{{ ${displayEl.key} }}</div>`;
                    }
                }

                // Resize handles
                if (activeIds.length === 1 && activeIds[0] === displayEl.id && !el.locked) {
                    ['nw','n','ne','e','se','s','sw','w'].forEach(hdl => {
                        const handle = document.createElement('div');
                        handle.className = `handle res-${hdl}`;
                        handle.setAttribute('data-handle', hdl);
                        handle.onmousedown = (ev) => {
                            ev.stopPropagation(); ev.preventDefault();
                            resizingEl = el; resizeHandle = hdl;
                            startMouseX = ev.clientX; startMouseY = ev.clientY;
                            startX = el.x; startY = el.y; startW = el.width; startH = el.height || 10;
                        };
                        div.appendChild(handle);
                    });
                }

                div.onmousedown = (ev) => {
                    if (ev.target.classList.contains('handle')) return;
                    if (el.locked) return;
                    ev.stopPropagation();
                    draggingEl = el;
                    let tIds = [el.id];
                    if (el.groupId) tIds = elements.filter(i => i.groupId === el.groupId).map(i => i.id);
                    if (ev.shiftKey) {
                        tIds.forEach(id => { if (activeIds.includes(id)) activeIds = activeIds.filter(a => a !== id); else activeIds.push(id); });
                        selectElements(activeIds);
                    } else if (!activeIds.includes(el.id)) {
                        activeIds = tIds; selectElements(activeIds);
                    }
                    activeIds.forEach(id => { const t = elements.find(x => x.id === id); if (t) { t.origX = t.x; t.origY = t.y; } });
                    startMouseX = ev.clientX; startMouseY = ev.clientY;
                };
                c.appendChild(div);
            });

            // Section resize handle (except detail and pageFooter)
            if (sectionKey !== 'detail' && sectionKey !== 'pageFooter') {
                const rh = document.createElement('div');
                rh.className = 'section-resize-handle';
                rh.dataset.section = sectionKey;
                rh.style.cssText = `
                    position: absolute; left: 0; top: ${currentYpx + sectionHpx - 3}px;
                    width: ${pw * BASE_SCALE}px; height: 6px;
                    cursor: ns-resize; z-index: 50;
                    background: transparent;
                `;
                rh.title = 'Drag to resize section height';
                rh.onmousedown = (ev) => {
                    ev.stopPropagation(); ev.preventDefault();
                    sectionResizing = { key: sectionKey, startY: ev.clientY, startHeight: sectionHeight };
                };
                // Visual indicator line
                const line = document.createElement('div');
                line.style.cssText = `
                    position: absolute; left: 0; top: 2px;
                    width: 100%; height: 2px;
                    background: rgba(59,130,246,0.5);
                    pointer-events: none;
                `;
                rh.appendChild(line);
                c.appendChild(rh);
            }

            currentY += sectionHeight + 2; // gap between sections
        });

        document.getElementById('align-tools').style.display = activeIds.length > 1 ? 'flex' : 'none';
        drawRulers(); updateLayersList(); drawMinimap();
    }

    // ── Context Menu ─────────────────────────────────────────
    function canvasContextMenu(e) {
        const el = e.target.closest('.design-element');
        if (!el) return;
        e.preventDefault();
        const id = el.getAttribute('data-id');
        if (!activeIds.includes(id)) selectElements([id]);
        const menu = document.getElementById('ctx-menu');
        menu.style.display = 'block';
        menu.style.left = e.clientX + 'px'; menu.style.top = e.clientY + 'px';
    }
    function hideCtxMenu() { document.getElementById('ctx-menu').style.display = 'none'; }
    function ctxDuplicate() { hideCtxMenu(); duplicateSelected(); }
    function ctxBringFront() { hideCtxMenu(); bringToFront(); }
    function ctxSendBack() { hideCtxMenu(); sendToBack(); }
    function ctxLock() { hideCtxMenu(); activeIds.forEach(id => { const el = elements.find(e => e.id === id); if (el) el.locked = !el.locked; }); renderElements(); updateLayersList(); }
    function ctxToggleVisible() { hideCtxMenu(); activeIds.forEach(id => { const el = elements.find(e => e.id === id); if (el) el.hidden = !el.hidden; }); renderElements(); updateLayersList(); }
    function ctxGroup() { hideCtxMenu(); groupElements(); }
    function ctxDelete() { hideCtxMenu(); deleteActive(); }

    // ── Select / Align / Group ───────────────────────────────
    function selectElements(ids) {
        activeIds = ids; activeId = ids.length === 1 ? ids[0] : null;
        renderElements(); updateInspector();
    }
    function alignElements(type) {
        if (activeIds.length < 2) return; pushHistory();
        const sel = elements.filter(el => activeIds.includes(el.id));
        if (type === 'left') { const mx = Math.min(...sel.map(e => e.x)); sel.forEach(e => e.x = mx); }
        if (type === 'right') { const mx = Math.max(...sel.map(e => e.x + e.width)); sel.forEach(e => e.x = mx - e.width); }
        if (type === 'top') { const my = Math.min(...sel.map(e => e.y)); sel.forEach(e => e.y = my); }
        if (type === 'bottom') { const my = Math.max(...sel.map(e => e.y + (e.height||10))); sel.forEach(e => e.y = my - (e.height||10)); }
        renderElements();
    }
    function groupElements() {
        if (activeIds.length < 2) return; pushHistory();
        const gId = 'group_' + Date.now();
        elements.forEach(el => { if (activeIds.includes(el.id)) el.groupId = gId; });
        renderElements(); selectElements(activeIds);
    }
    function ungroupElements() {
        pushHistory();
        elements.forEach(el => { if (activeIds.includes(el.id)) delete el.groupId; });
        renderElements();
    }

    // ── Styles ───────────────────────────────────────────────
    function addStyle() { globalStyles.push({ name: 'New Style', font_size: 10, bold: false }); renderStyles(); }
    function renderStyles() {
        const cont = document.getElementById('styles-container'); if (!cont) return; cont.innerHTML = '';
        globalStyles.forEach((s, i) => {
            const d = document.createElement('div'); d.style.padding='5px'; d.style.border='1px solid var(--border)'; d.style.marginBottom='5px';
            d.innerHTML = `<input type="text" value="${s.name}" onchange="globalStyles[${i}].name=this.value" style="background:none;border:none;color:var(--primary);font-size:11px;width:100%"><br><input type="number" value="${s.font_size}" onchange="globalStyles[${i}].font_size=parseInt(this.value);renderElements();" style="width:40px;background:none;color:white;border:1px solid var(--border);font-size:10px;"> <label style="font-size:10px"><input type="checkbox" ${s.bold?'checked':''} onchange="globalStyles[${i}].bold=this.checked;renderElements();"> B</label>`;
            cont.appendChild(d);
        });
    }

    // ── Inspector ────────────────────────────────────────────
    function updateInspector() {
        const el = elements.find(e => e.id === activeId), cont = document.getElementById('inspector-content');
        if (!el && activeIds.length > 1) {
            cont.innerHTML = `<div style="text-align:center;padding:1.5rem 1rem;"><p style="color:var(--primary);font-weight:bold;">${activeIds.length} elements selected</p><div style="display:grid;gap:0.5rem;margin-top:1rem;">
                <button onclick="groupElements()" class="btn btn-primary btn-sm">📦 Group</button>
                <button onclick="distributeH()" class="btn btn-secondary btn-sm">⇔ Distribute H</button>
                <button onclick="distributeV()" class="btn btn-secondary btn-sm">⇕ Distribute V</button>
                <button onclick="alignElements('left')" class="btn btn-secondary btn-sm">⇤ Align Left</button>
                <button onclick="duplicateSelected()" class="btn btn-secondary btn-sm">⧉ Duplicate All</button>
                <button onclick="deleteActive()" class="btn btn-danger btn-sm">🗑 Delete All</button>
            </div></div>`;
            return;
        }
        if (!el) { cont.innerHTML = `<div style="text-align:center;padding:3rem 1rem;color:var(--text-muted);font-size:0.8rem;">Select an object</div>`; return; }

        const lockedWarn = el.locked ? `<div style="background:rgba(239,68,68,0.1);border:1px solid var(--danger);border-radius:4px;padding:6px 10px;font-size:11px;color:var(--danger);margin:8px;">🔒 Locked — unlock from Layers</div>` : '';
        let html = lockedWarn + `
            <div class="props-section"><div class="props-label">Identity</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Type</div><div class="prop-val" style="padding-left:8px;font-size:11px;color:var(--text-muted);">${el.type}</div></div>
                <div class="prop-item"><div class="prop-key">${el.type==='label'?'Text':'Key'}</div><div class="prop-val"><input type="text" value="${el.type==='label'?(el.text||''):(el.key||'')}" oninput="updateElProps('${el.type==='label'?'text':'key'}', this.value)"></div></div>
                <div class="prop-item"><div class="prop-key">Group</div><div class="prop-val" style="padding-left:10px;">${el.groupId ? `<span style="color:var(--primary);font-size:10px;">${el.groupId.slice(-6)}</span> <button onclick="ungroupElements()" style="background:none;border:none;color:var(--danger);cursor:pointer;">[X]</button>` : 'None'}</div></div>
            </div></div>`;

        if (el.type === 'line') {
            html += `<div class="props-section"><div class="props-label">Line</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Color</div><div class="prop-val"><input type="color" value="${el.lineColor||'#000000'}" oninput="updateElProps('lineColor',this.value)" style="height:28px;border:none;background:none;cursor:pointer;"></div></div>
                <div class="prop-item"><div class="prop-key">Thickness</div><div class="prop-val"><input type="number" step="0.1" min="0.1" value="${el.height||0.5}" oninput="updateElProps('height',parseFloat(this.value))"></div></div>
            </div></div>`;
        } else if (el.type === 'image') {
            html += `<div class="props-section"><div class="props-label">Image</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Source URL</div><div class="prop-val"><input type="text" value="${el.src||''}" oninput="updateElProps('src',this.value)"></div></div>
                <div class="prop-item"><div class="prop-key">Rotation</div><div class="prop-val"><select onchange="updateElProps('rotation',parseInt(this.value))">
                    <option value="0" ${(!el.rotation||el.rotation==0)?'selected':''}>0°</option>
                    <option value="90" ${el.rotation==90?'selected':''}>90°</option>
                    <option value="180" ${el.rotation==180?'selected':''}>180°</option>
                    <option value="270" ${el.rotation==270?'selected':''}>270°</option>
                </select></div></div>
            </div></div>`;
        } else if (el.type === 'barcode') {
            html += `<div class="props-section"><div class="props-label">Barcode</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Value</div><div class="prop-val"><input type="text" value="${el.value||''}" oninput="updateElProps('value',this.value)" placeholder="Data or @{{field_name}}"></div></div>
                <div class="prop-item"><div class="prop-key">Symbology</div><div class="prop-val"><select onchange="updateElProps('symbology',this.value)">
                    <option value="code128" ${el.symbology==='code128'?'selected':''}>Code 128</option>
                    <option value="code39" ${el.symbology==='code39'?'selected':''}>Code 39</option>
                    <option value="ean13" ${el.symbology==='ean13'?'selected':''}>EAN-13</option>
                    <option value="ean8" ${el.symbology==='ean8'?'selected':''}>EAN-8</option>
                    <option value="upca" ${el.symbology==='upca'?'selected':''}>UPC-A</option>
                    <option value="itf14" ${el.symbology==='itf14'?'selected':''}>ITF-14</option>
                </select></div></div>
                <div class="prop-item"><div class="prop-key">Show Text</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.showText?'checked':''} onchange="updateElProps('showText',this.checked)"></div></div>
                <div class="prop-item"><div class="prop-key">Height (mm)</div><div class="prop-val"><input type="number" step="0.5" value="${el.height_mm||20}" oninput="updateElProps('height_mm',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Bar Width</div><div class="prop-val"><input type="number" step="0.1" min="0" value="${el.barWidth||0}" oninput="updateElProps('barWidth',parseFloat(this.value))" title="0 = auto"></div></div>
            </div></div>`;
            html += `<div class="props-section"><div class="props-label">Appearance</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">FontFamily</div><div class="prop-val"><select onchange="updateElProps('fontFamily',this.value)"><option value="Arial">Arial (Default)</option></select></div></div>
            </div></div>`;
        } else if (el.type === 'qrcode') {
            html += `<div class="props-section"><div class="props-label">QR Code</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Value</div><div class="prop-val"><input type="text" value="${el.value||''}" oninput="updateElProps('value',this.value)" placeholder="URL or text"></div></div>
                <div class="prop-item"><div class="prop-key">Error Correction</div><div class="prop-val"><select onchange="updateElProps('errorCorrection',this.value)">
                    <option value="L" ${el.errorCorrection==='L'?'selected':''}>L (Low)</option>
                    <option value="M" ${el.errorCorrection==='M'||!el.errorCorrection?'selected':''}>M (Medium)</option>
                    <option value="Q" ${el.errorCorrection==='Q'?'selected':''}>Q (Quartile)</option>
                    <option value="H" ${el.errorCorrection==='H'?'selected':''}>H (High)</option>
                </select></div></div>
                <div class="prop-item"><div class="prop-key">Size (mm)</div><div class="prop-val"><input type="number" step="0.5" value="${el.size||25}" oninput="updateElProps('size',parseFloat(this.value))"></div></div>
            </div></div>`;
        } else if (el.type === 'running_total') {
            const opLabel = { sum: 'Sum', count: 'Count', average: 'Average', min: 'Min', max: 'Max' };
            const resetLabel = { never: 'Never', on_page: 'On Page', on_group: 'On Group' };
            const evalLabel = { on_change: 'On Change', on_record: 'On Record' };
            html += `
            <div class="props-section"><div class="props-label">Running Total</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Field Name</div><div class="prop-val"><input type="text" value="${el.field||''}" oninput="updateElProps('field',this.value)" placeholder="data_field_name"></div></div>
                <div class="prop-item"><div class="prop-key">Operation</div><div class="prop-val"><select onchange="updateElProps('operation',this.value)">
                    <option value="sum" ${(el.operation||'sum')==='sum'?'selected':''}>Sum</option>
                    <option value="count" ${el.operation==='count'?'selected':''}>Count</option>
                    <option value="average" ${el.operation==='average'?'selected':''}>Average</option>
                    <option value="min" ${el.operation==='min'?'selected':''}>Min</option>
                    <option value="max" ${el.operation==='max'?'selected':''}>Max</option>
                </select></div></div>
                <div class="prop-item"><div class="prop-key">Reset</div><div class="prop-val"><select onchange="updateElProps('reset',this.value);updateInspector();">
                    <option value="never" ${(el.reset||'never')==='never'?'selected':''}>Never</option>
                    <option value="on_page" ${el.reset==='on_page'?'selected':''}>On Page</option>
                    <option value="on_group" ${el.reset==='on_group'?'selected':''}>On Group</option>
                </select></div></div>
                ${el.reset === 'on_group' ? `
                <div class="prop-item"><div class="prop-key">Group Field</div><div class="prop-val"><input type="text" value="${el.resetGroup||''}" oninput="updateElProps('resetGroup',this.value)" placeholder="group_field_name"></div></div>
                ` : ''}
                <div class="prop-item"><div class="prop-key">Evaluate</div><div class="prop-val"><select onchange="updateElProps('evaluate',this.value)">
                    <option value="on_change" ${(el.evaluate||'on_change')==='on_change'?'selected':''}>On Change</option>
                    <option value="on_record" ${el.evaluate==='on_record'?'selected':''}>On Record</option>
                </select></div></div>
            </div></div>`;
            html += `
            <div class="props-section"><div class="props-label">Appearance</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">FontFamily</div><div class="prop-val"><select id="propFontFamily" onchange="updateElProps('fontFamily',this.value)"><option value="Arial">Arial (Default)</option></select></div></div>
                <div class="prop-item"><div class="prop-key">FontSize</div><div class="prop-val"><input type="number" value="${el.fontSize||10}" oninput="updateElProps('fontSize',parseInt(this.value))"></div></div>
            </div></div>`;
            html += `<div class="props-section"><div class="props-label">Formatting</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Type</div><div class="prop-val">
                    <select onchange="updateElProps('format_type',this.value)">
                        <option value="none" ${el.format_type==='none'||!el.format_type?'selected':''}>None</option>
                        <option value="date" ${el.format_type==='date'?'selected':''}>Date</option>
                        <option value="number" ${el.format_type==='number'?'selected':''}>Number</option>
                        <option value="currency" ${el.format_type==='currency'?'selected':''}>Currency</option>
                    </select>
                </div></div>`;
            if (el.format_type === 'date') {
                html += `<div class="prop-item"><div class="prop-key">Pattern</div><div class="prop-val"><input type="text" value="${el.format_string||'dd/MM/yyyy'}" oninput="updateElProps('format_string',this.value)" placeholder="dd/MM/yyyy"></div></div>`;
            } else if (el.format_type === 'number' || el.format_type === 'currency') {
                if (el.format_type === 'currency') {
                    html += `<div class="prop-item"><div class="prop-key">Symbol</div><div class="prop-val"><input type="text" value="${el.format_string||'Rp'}" oninput="updateElProps('format_string',this.value)" placeholder="Rp"></div></div>`;
                }
                html += `<div class="prop-item"><div class="prop-key">Decimals</div><div class="prop-val"><input type="number" min="0" max="4" value="${el.decimal_places!==undefined?el.decimal_places:2}" oninput="updateElProps('decimal_places',parseInt(this.value))"></div></div>`;
            }
            html += `</div></div>`;
        } else {
            html += `
            <div class="props-section"><div class="props-label">Global Style</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Link</div><div class="prop-val"><select onchange="updateElProps('styleIdx',this.value==='none'?undefined:parseInt(this.value))" style="color:var(--primary)"><option value="none">Manual</option>${globalStyles.map((s,i)=>`<option value="${i}" ${el.styleIdx===i?'selected':''}>${s.name}</option>`).join('')}</select></div></div>
            </div></div>
            <div class="props-section"><div class="props-label">Appearance</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">FontFamily</div><div class="prop-val"><select id="propFontFamily" onchange="updateElProps('fontFamily',this.value)"><option value="Arial">Arial (Default)</option></select></div></div>
                <div class="prop-item"><div class="prop-key">FontSize</div><div class="prop-val"><input type="number" value="${el.font_size}" oninput="updateElProps('font_size',parseInt(this.value))" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">Align</div><div class="prop-val"><select onchange="updateElProps('align',this.value)"><option value="L" ${el.align==='L'?'selected':''}>Left</option><option value="C" ${el.align==='C'?'selected':''}>Center</option><option value="R" ${el.align==='R'?'selected':''}>Right</option></select></div></div>
                <div class="prop-item"><div class="prop-key">Bold</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.bold?'checked':''} onchange="updateElProps('bold',this.checked)" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">Border</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.border?'checked':''} onchange="updateElProps('border',this.checked)"></div></div>
                <div class="prop-item"><div class="prop-key">Rotation</div><div class="prop-val"><select onchange="updateElProps('rotation',parseInt(this.value))">
                    <option value="0" ${(!el.rotation||el.rotation==0)?'selected':''}>0°</option>
                    <option value="90" ${el.rotation==90?'selected':''}>90°</option>
                    <option value="180" ${el.rotation==180?'selected':''}>180°</option>
                    <option value="270" ${el.rotation==270?'selected':''}>270°</option>
                </select></div></div>
            </div></div>`;

            if (el.type === 'field') {
                html += `<div class="props-section"><div class="props-label">Formatting</div><div class="prop-table">
                    <div class="prop-item"><div class="prop-key">Type</div><div class="prop-val">
                        <select onchange="updateElProps('format_type',this.value)">
                            <option value="none" ${el.format_type==='none'||!el.format_type?'selected':''}>None</option>
                            <option value="date" ${el.format_type==='date'?'selected':''}>Date</option>
                            <option value="number" ${el.format_type==='number'?'selected':''}>Number</option>
                            <option value="currency" ${el.format_type==='currency'?'selected':''}>Currency</option>
                            <option value="terbilang" ${el.format_type==='terbilang'?'selected':''}>Terbilang</option>
                        </select>
                    </div></div>`;
                
                if (el.format_type === 'date') {
                    html += `<div class="prop-item"><div class="prop-key">Pattern</div><div class="prop-val"><input type="text" value="${el.format_string||'dd/MM/yyyy'}" oninput="updateElProps('format_string',this.value)" placeholder="dd/MM/yyyy"></div></div>`;
                } else if (el.format_type === 'number' || el.format_type === 'currency') {
                    if (el.format_type === 'currency') {
                        html += `<div class="prop-item"><div class="prop-key">Symbol</div><div class="prop-val"><input type="text" value="${el.format_string||'Rp'}" oninput="updateElProps('format_string',this.value)" placeholder="Rp"></div></div>`;
                    }
                    html += `<div class="prop-item"><div class="prop-key">Decimals</div><div class="prop-val"><input type="number" min="0" max="4" value="${el.decimal_places!==undefined?el.decimal_places:2}" oninput="updateElProps('decimal_places',parseInt(this.value))"></div></div>`;
                }
                html += `</div></div>`;
            }

            // ── Conditional Formatting ──────────────────────
            html += `
            <div class="props-section" id="conditionalFormatSection" style="display:none;">
                <div class="props-label" style="display:flex;justify-content:space-between;align-items:center;">
                    <span>Conditional Formatting</span>
                    <button onclick="addConditionalFormat()" class="btn btn-primary btn-sm" style="font-size:10px;padding:2px 8px;">+ Add Rule</button>
                </div>
                <div id="conditionalFormatList" class="p-2" style="padding:0.5rem;display:flex;flex-direction:column;gap:0.5rem;">
                    <!-- Rules rendered here by JS -->
                </div>
            </div>`;
        }

        html += `
            <div class="props-section"><div class="props-label">Layout (mm)</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">X</div><div class="prop-val"><input type="number" step="0.1" value="${el.x||0}" oninput="updateElProps('x',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Y</div><div class="prop-val"><input type="number" step="0.1" value="${el.y||0}" oninput="updateElProps('y',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">W</div><div class="prop-val"><input type="number" step="0.1" value="${el.width||0}" oninput="updateElProps('width',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">H</div><div class="prop-val"><input type="number" step="0.1" value="${el.height||0}" oninput="updateElProps('height',parseFloat(this.value))"></div></div>
            </div></div>`;

        if (el.type === 'table' && el.columns) {
            html += `<div class="props-section"><div class="props-label">Table Settings</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Header H</div><div class="prop-val"><input type="number" step="0.5" value="${el.header_height||7}" oninput="updateElProps('header_height',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Row H</div><div class="prop-val"><input type="number" step="0.5" value="${el.row_height||6}" oninput="updateElProps('row_height',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Btm Pad</div><div class="prop-val"><input type="number" step="1" value="${el.bottom_padding||10}" oninput="updateElProps('bottom_padding',parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Hdr BG</div><div class="prop-val"><input type="color" value="${el.header_bg_color||'#ffffff'}" oninput="updateElProps('header_bg_color',this.value)" style="height:28px;border:none;background:none;cursor:pointer;"></div></div>
            </div></div>`;
            html += `<div class="props-section"><div class="props-label">Table Columns</div><div class="prop-table">`;
            el.columns.forEach((col, idx) => {
                html += `
                    <div class="prop-item" style="background:rgba(255,255,255,0.03);">
                        <div class="prop-key">Col ${idx+1}</div>
                        <div class="prop-val" style="display:flex; gap:4px; padding-left:4px;">
                            <button onclick="moveColUp(${idx})" style="color:var(--text);background:none;border:none;cursor:pointer;font-size:10px;">▲</button>
                            <button onclick="moveColDown(${idx})" style="color:var(--text);background:none;border:none;cursor:pointer;font-size:10px;">▼</button>
                            <button onclick="deleteCol(${idx})" style="color:var(--danger);background:none;border:none;cursor:pointer;font-size:10px;margin-left:auto;">[×]</button>
                        </div>
                    </div>
                    <div class="prop-item"><div class="prop-key">Label</div><div class="prop-val"><input type="text" value="${col.label}" oninput="updateCol(${idx},'label',this.value)"></div></div>
                    <div class="prop-item"><div class="prop-key">Key</div><div class="prop-val"><input type="text" value="${col.key}" oninput="updateCol(${idx},'key',this.value)"></div></div>
                    <div class="prop-item"><div class="prop-key">Width</div><div class="prop-val"><input type="number" value="${col.width}" oninput="updateCol(${idx},'width',parseFloat(this.value))"></div></div>
                    <div class="prop-item"><div class="prop-key">Align</div><div class="prop-val"><select onchange="updateCol(${idx},'align',this.value)"><option value="L" ${col.align==='L'||!col.align?'selected':''}>L</option><option value="C" ${col.align==='C'?'selected':''}>C</option><option value="R" ${col.align==='R'?'selected':''}>R</option></select></div></div>
                    <div class="prop-item"><div class="prop-key">Format</div><div class="prop-val"><select onchange="updateCol(${idx},'format_type',this.value)"><option value="none" ${col.format_type==='none'?'selected':''}>None</option><option value="date" ${col.format_type==='date'?'selected':''}>Date</option><option value="number" ${col.format_type==='number'?'selected':''}>Number</option><option value="currency" ${col.format_type==='currency'?'selected':''}>Currency</option></select></div></div>`;
                if (col.format_type === 'date') {
                    html += `<div class="prop-item"><div class="prop-key">Pattern</div><div class="prop-val"><input type="text" value="${col.format_string||'dd/MM/yyyy'}" oninput="updateCol(${idx},'format_string',this.value)" style="font-size:10px;"></div></div>`;
                } else if (col.format_type === 'number' || col.format_type === 'currency') {
                    if (col.format_type === 'currency') {
                        html += `<div class="prop-item"><div class="prop-key">Symbol</div><div class="prop-val"><input type="text" value="${col.format_string||'Rp'}" oninput="updateCol(${idx},'format_string',this.value)" style="font-size:10px;"></div></div>`;
                    }
                    html += `<div class="prop-item"><div class="prop-key">Decs</div><div class="prop-val"><input type="number" min="0" value="${col.decimal_places!==undefined?col.decimal_places:2}" oninput="updateCol(${idx},'decimal_places',parseInt(this.value))"></div></div>`;
                }
                // ── Computed Column Expression ──────────────────
                html += `
                    <div class="prop-item">
                        <div class="prop-key">Expression</div>
                        <div class="prop-val" style="display:flex; gap:2px; padding:1px;">
                            <input type="text" value="${escapeHtml(col.expression || '')}"
                                placeholder="e.g. qty * price"
                                oninput="updateCol(${idx},'expression',this.value); updateCol(${idx},'computed',true)"
                                style="flex:1; min-width:0; height:26px; border:none; background:transparent; color:var(--text); padding:0 4px; font-size:10px; font-family:monospace; outline:none;">
                            <button onclick="openFormulaEditor(${idx}, '${el.id}', '${escapeJs(col.expression || '')}')"
                                class="px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded text-xs hover:bg-purple-200"
                                style="padding:2px 6px; background:rgba(168,85,247,0.15); color:#a855f7; border:none; border-radius:3px; font-size:11px; cursor:pointer; font-weight:700; white-space:nowrap;"
                                title="Open Formula Editor">fx</button>
                        </div>
                    </div>`;
            });
            html += `</div><div style="padding:0.5rem;"><button onclick="addCol()" class="btn btn-secondary btn-sm" style="width:100%;">+ Add Column</button></div></div>`;
        }

        html += `<div style="padding:0.75rem;display:flex;gap:0.5rem;">
            <button onclick="duplicateSelected()" class="btn btn-secondary btn-sm" style="flex:1;">⧉ Dup</button>
            <button onclick="deleteActive()" class="btn btn-danger btn-sm" style="flex:1;">🗑 Delete</button>
        </div>`;
        cont.innerHTML = html;
        // Update conditional formatting list when inspector is refreshed
        updateConditionalFormatList();
    }

    function updateCol(idx, prop, val) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns[idx]){el.columns[idx][prop]=val;renderElements();if(prop==='format_type')updateInspector();} }
    function addCol() { const el=elements.find(e=>e.id===activeId); if(el&&el.type==='table'){if(!el.columns)el.columns=[];el.columns.push({label:'Col',key:'key',width:30,align:'L'});updateInspector();} }
    function deleteCol(idx) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns.length>1){el.columns.splice(idx,1);updateInspector();renderElements();} }
    function moveColUp(idx) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el&&idx>0){const c=el.columns.splice(idx,1)[0]; el.columns.splice(idx-1,0,c); updateInspector(); renderElements(); } }
    function moveColDown(idx) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el&&idx<el.columns.length-1){const c=el.columns.splice(idx,1)[0]; el.columns.splice(idx+1,0,c); updateInspector(); renderElements(); } }
    function updateElProps(prop,val) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el){el[prop]=val;renderElements();updateInspector();} }
    function deleteActive() { if(!confirm('Delete selected element(s)?'))return; pushHistory(); elements=elements.filter(el=>!activeIds.includes(el.id)); activeIds=[];activeId=null; renderElements();updateInspector(); }

    // ── Conditional Formatting ────────────────────────────────

    function addConditionalFormat() {
        const el = elements.find(e => e.id === activeId);
        if (!el) return;
        if (!el.conditionalFormats) {
            el.conditionalFormats = [];
        }
        el.conditionalFormats.push({
            name: 'Rule ' + (el.conditionalFormats.length + 1),
            field: el.field || el.key || '',
            operator: 'equals',
            value: '',
            value2: '',
            style: {
                color: '#000000',
                backgroundColor: '#FFFFFF',
                bold: false,
                italic: false,
                underline: false,
            },
            enabled: true,
        });
        pushHistory();
        updateConditionalFormatList();
        markChanged();
    }

    function removeConditionalFormat(index) {
        const el = elements.find(e => e.id === activeId);
        if (!el?.conditionalFormats) return;
        el.conditionalFormats.splice(index, 1);
        pushHistory();
        updateConditionalFormatList();
        renderElements();
        markChanged();
    }

    function updateConditionalFormatList() {
        const el = elements.find(e => e.id === activeId);
        const section = document.getElementById('conditionalFormatSection');
        const container = document.getElementById('conditionalFormatList');

        if (!section || !container) return;

        if (!el || el.type !== 'field' || !el.conditionalFormats || el.conditionalFormats.length === 0) {
            section.style.display = 'none';
            return;
        }

        section.style.display = 'block';

        const schemaFields = getSchemaFieldKeys();
        // Always include the current element's field key
        if (el.field && !schemaFields.includes(el.field)) {
            schemaFields.unshift(el.field);
        }
        if (el.key && !schemaFields.includes(el.key)) {
            schemaFields.unshift(el.key);
        }

        container.innerHTML = el.conditionalFormats.map((rule, i) => `
            <div class="border rounded p-2" style="border:1px solid var(--border);border-radius:4px;padding:0.5rem;${rule.enabled ? '' : 'opacity:0.5;'}">
                <div class="flex items-center justify-between mb-1" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.25rem;">
                    <input type="text" value="${escapeHtml(rule.name)}"
                        onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].name = this.value; markChanged(); }"
                        style="font-size:11px;font-weight:600;border:none;background:none;color:var(--text);width:auto;padding:0;" />
                    <div style="display:flex;align-items:center;gap:0.25rem;">
                        <input type="checkbox" ${rule.enabled ? 'checked' : ''}
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].enabled = this.checked; updateConditionalFormatList(); renderElements(); markChanged(); }"
                            style="width:12px;height:12px;cursor:pointer;" title="Enable/disable rule" />
                        <button onclick="removeConditionalFormat(${i})" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:12px;padding:0;line-height:1;" title="Remove rule">✕</button>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.25rem;font-size:11px;">
                    <select onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].field = this.value; markChanged(); }"
                        style="border:1px solid var(--border);border-radius:3px;padding:2px 4px;background:var(--bg);color:var(--text);font-size:11px;">
                        ${schemaFields.map(f => `<option value="${f}" ${rule.field === f ? 'selected' : ''}>${f}</option>`).join('')}
                        ${schemaFields.length === 0 ? `<option value="${rule.field || ''}" ${rule.field ? 'selected' : ''}>${rule.field || '(no fields)'}</option>` : ''}
                    </select>
                    <select onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].operator = this.value; updateConditionalFormatList(); markChanged(); }"
                        style="border:1px solid var(--border);border-radius:3px;padding:2px 4px;background:var(--bg);color:var(--text);font-size:11px;">
                        ${CONDITIONAL_OPERATORS.map(op => `<option value="${op.value}" ${rule.operator === op.value ? 'selected' : ''}>${op.label}</option>`).join('')}
                    </select>
                    <input type="text" value="${escapeHtml(rule.value)}" placeholder="Value"
                        onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].value = this.value; updateConditionalFormatList(); renderElements(); markChanged(); }"
                        style="border:1px solid var(--border);border-radius:3px;padding:2px 4px;background:var(--bg);color:var(--text);font-size:11px;${rule.operator === 'is_null' || rule.operator === 'is_not_null' ? 'display:none;' : ''}" />
                    ${rule.operator === 'between' ? `<input type="text" value="${escapeHtml(rule.value2)}" placeholder="and"
                        onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].value2 = this.value; updateConditionalFormatList(); renderElements(); markChanged(); }"
                        style="border:1px solid var(--border);border-radius:3px;padding:2px 4px;background:var(--bg);color:var(--text);font-size:11px;" />` : ''}
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.25rem;font-size:11px;">
                    <span style="display:flex;align-items:center;gap:2px;">
                        <span style="color:var(--text-muted);font-size:9px;">A</span>
                        <input type="color" value="${rule.style.color || '#000000'}"
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].style.color = this.value; renderElements(); markChanged(); }"
                            style="width:20px;height:20px;padding:0;border:1px solid var(--border);cursor:pointer;background:none;" title="Text color" />
                    </span>
                    <span style="display:flex;align-items:center;gap:2px;">
                        <span style="color:var(--text-muted);font-size:9px;">▨</span>
                        <input type="color" value="${rule.style.backgroundColor || '#FFFFFF'}"
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].style.backgroundColor = this.value; renderElements(); markChanged(); }"
                            style="width:20px;height:20px;padding:0;border:1px solid var(--border);cursor:pointer;background:none;" title="Background color" />
                    </span>
                    <label style="display:inline-flex;align-items:center;gap:2px;cursor:pointer;color:var(--text);font-size:10px;">
                        <input type="checkbox" ${rule.style.bold ? 'checked' : ''}
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].style.bold = this.checked; renderElements(); markChanged(); }"
                            style="width:11px;height:11px;"> <b>B</b>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:2px;cursor:pointer;color:var(--text);font-size:10px;">
                        <input type="checkbox" ${rule.style.italic ? 'checked' : ''}
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].style.italic = this.checked; renderElements(); markChanged(); }"
                            style="width:11px;height:11px;"> <i>I</i>
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:2px;cursor:pointer;color:var(--text);font-size:10px;">
                        <input type="checkbox" ${rule.style.underline ? 'checked' : ''}
                            onchange="selectedElement=elements.find(e=>e.id===activeId); if(selectedElement&&selectedElement.conditionalFormats) { selectedElement.conditionalFormats[${i}].style.underline = this.checked; renderElements(); markChanged(); }"
                            style="width:11px;height:11px;"> <u>U</u>
                    </label>
                </div>
            </div>
        `).join('');
    }

    // ── Conditional Style Evaluation (Canvas Preview) ───────

    function getConditionalStyle(el, data) {
        const styles = [];
        if (!el.conditionalFormats || !data) return styles;

        for (const rule of el.conditionalFormats) {
            if (!rule.enabled) continue;

            const fieldValue = resolveDataValue(rule.field, data);
            const compareValue = rule.value;
            let match = false;

            switch (rule.operator) {
                case 'equals': match = fieldValue == compareValue; break;
                case 'not_equals': match = fieldValue != compareValue; break;
                case 'greater_than': match = parseFloat(fieldValue) > parseFloat(compareValue); break;
                case 'less_than': match = parseFloat(fieldValue) < parseFloat(compareValue); break;
                case 'greater_equal': match = parseFloat(fieldValue) >= parseFloat(compareValue); break;
                case 'less_equal': match = parseFloat(fieldValue) <= parseFloat(compareValue); break;
                case 'contains': match = String(fieldValue).includes(compareValue); break;
                case 'starts_with': match = String(fieldValue).startsWith(compareValue); break;
                case 'ends_with': match = String(fieldValue).endsWith(compareValue); break;
                case 'is_null': match = fieldValue === null || fieldValue === undefined || fieldValue === ''; break;
                case 'is_not_null': match = fieldValue !== null && fieldValue !== undefined && fieldValue !== ''; break;
                case 'between': match = parseFloat(fieldValue) >= parseFloat(compareValue) && parseFloat(fieldValue) <= parseFloat(rule.value2); break;
                default: match = false;
            }

            if (match) {
                styles.push(rule.style);
                break; // first match wins
            }
        }

        return styles;
    }

    // ── Rulers ───────────────────────────────────────────────
    function drawRulers() {
        const rt=document.getElementById('ruler-top'), rl=document.getElementById('ruler-left');
        const w=(parseFloat(document.getElementById('paper-w').value)*BASE_SCALE)*zoomLevel;
        const h=(parseFloat(document.getElementById('paper-h').value)*BASE_SCALE)*zoomLevel;
        let tH=''; for(let i=0;i<w/(BASE_SCALE*zoomLevel);i+=10) tH+=`<div style="position:absolute;left:${i*BASE_SCALE*zoomLevel}px;font-size:9px;border-left:1px solid #475569;height:10px;padding-left:2px;color:#94a3b8">${i}</div>`;
        rt.innerHTML=tH;
        let lH=''; for(let i=0;i<h/(BASE_SCALE*zoomLevel);i+=10) lH+=`<div style="position:absolute;top:${i*BASE_SCALE*zoomLevel}px;font-size:9px;border-top:1px solid #475569;width:10px;padding-top:2px;color:#94a3b8">${i}</div>`;
        rl.innerHTML=lH;
    }

    // ── Upload Background ────────────────────────────────────
    function uploadBg() {
        const fI=document.getElementById('bg-upload'); if(!fI.files[0])return;
        const fD=new FormData(); fD.append('image',fI.files[0]); fD.append('_token','{{ csrf_token() }}');
        fetch("{{ route('admin.templates.upload-bg', [], false) }}",{method:'POST',body:fD}).then(r=>r.json()).then(data=>{
            if(data.status==='ok'){const img=document.getElementById('canvas-bg-img');img.src=data.url;img.style.display='block';document.getElementById('bg-path').value=data.url;}
        });
    }
    function updateBgConfig() {
        backgroundConfig.is_printed=document.getElementById('bg-is-printed').checked;
        backgroundConfig.opacity=parseInt(document.getElementById('bg-opacity').value);
        const img=document.getElementById('canvas-bg-img');
        if(img) img.style.opacity=backgroundConfig.opacity/100;
    }

    // ── Save / Preview / Test Print ──────────────────────────
    function saveTemplate() {
        const name=document.getElementById('tpl-name').value; if(!name)return alert('Name required');
        const allElements = flattenSections();
        const payload={name,paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),background_image_path:document.getElementById('bg-path').value,elements:{sections:sections,elements:allElements},styles:globalStyles,background_config:backgroundConfig,_token:'{{ csrf_token() }}'};
        const btn=document.getElementById('save-btn'); btn.textContent='Saving…'; btn.disabled=true;
        fetch("{{ $template->id ? route('admin.templates.update', $template, false) : route('admin.templates.store', [], false) }}",{method:"{{ $template->id ? 'PUT' : 'POST' }}",headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)})
        .then(async r => {
            const data = await r.json();
            if (r.ok && data.status === 'ok') {
                window.location.href = "{{ route('admin.templates') }}";
            } else {
                throw new Error(data.message || 'Server error');
            }
        })
        .catch(err => {
            console.error('Save Error:', err);
            btn.textContent = '💾 Save';
            btn.disabled = false;
            alert('Failed to save template: ' + err.message);
        });
    }
    // ── Sample Data Panel ─────────────────────────────────────
    function getPreviewData() {
        try {
            if (Object.keys(sampleDataCache).length > 0) return sampleDataCache;
            return JSON.parse(document.getElementById('json-input').value || '{}');
        } catch(e) { return {}; }
    }

    function toggleSampleDataPanel() {
        const panel = document.getElementById('sampleDataPanel');
        const isVisible = panel.style.display !== 'none';
        panel.style.display = isVisible ? 'none' : 'flex';
        if (!isVisible) sampleDataBuildTable();
    }

    function sampleDataBuildTable() {
        // Build a flat list of key→value pairs for the table editor
        const data = getPreviewData();
        const flat = flattenObject(data);
        sampleDataFields = Object.keys(flat);
        sampleDataRows = sampleDataFields.map(key => ({ key, value: String(flat[key] ?? '') }));

        const tbody = document.getElementById('sampleDataTbody');
        tbody.innerHTML = '';
        if (sampleDataRows.length === 0) {
            // Add a default empty row
            sampleDataRows.push({ key: '', value: '' });
        }
        sampleDataRows.forEach((row, idx) => {
            const tr = document.createElement('tr');
            tr.style.borderBottom = '1px solid var(--border)';
            tr.innerHTML = `
                <td style="padding:4px 6px; color:var(--text-muted); font-size:10px; text-align:center;">${idx + 1}</td>
                <td style="padding:2px 4px;"><input type="text" value="${escapeHtml(row.key)}" onchange="sampleDataUpdateRow(${idx}, 'key', this.value)" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:4px 6px; border-radius:3px; font-size:11px; font-family:monospace;"></td>
                <td style="padding:2px 4px;"><input type="text" value="${escapeHtml(row.value)}" onchange="sampleDataUpdateRow(${idx}, 'value', this.value)" style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:4px 6px; border-radius:3px; font-size:11px;"></td>
                <td style="padding:2px 4px; text-align:center;"><button onclick="sampleDataRemoveRow(${idx})" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px;">×</button></td>
            `;
            tbody.appendChild(tr);
        });
    }

    function sampleDataUpdateRow(idx, prop, val) {
        if (sampleDataRows[idx]) sampleDataRows[idx][prop] = val;
    }

    function sampleDataAddRow() {
        sampleDataRows.push({ key: '', value: '' });
        sampleDataBuildTable();
        // Scroll to bottom
        const panel = document.getElementById('sampleDataPanel');
        panel.scrollTop = panel.scrollHeight;
    }

    function sampleDataRemoveRow(idx) {
        if (sampleDataRows.length <= 1) return;
        sampleDataRows.splice(idx, 1);
        sampleDataBuildTable();
    }

    function sampleDataApply() {
        // Convert flat key-value rows back to nested object
        const obj = {};
        sampleDataRows.forEach(row => {
            if (row.key.trim()) {
                setNestedValue(obj, row.key.trim(), row.value);
            }
        });
        sampleDataCache = obj;
        document.getElementById('json-input').value = JSON.stringify(obj, null, 2);
        parseJSON();
        if (liveDataMode) renderElements();
        toggleSampleDataPanel();
    }

    function sampleDataImportCsv() {
        document.getElementById('csvFileInput').click();
    }

    function sampleDataParseCsv(input) {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result;
            const lines = text.split('\n').map(l => l.trim()).filter(l => l.length > 0);
            if (lines.length < 2) { alert('CSV must have at least a header row and one data row.'); return; }
            const headers = lines[0].split(',').map(h => h.trim());
            sampleDataRows = [];
            // Use the first data row
            const values = lines[1].split(',').map(v => v.trim());
            headers.forEach((h, i) => {
                sampleDataRows.push({ key: h, value: values[i] || '' });
            });
            sampleDataBuildTable();
        };
        reader.readAsText(file);
        input.value = '';
    }

    function sampleDataLoadFromJobHistory() {
        if (!templateId) { alert('Save the template first to load from job history.'); return; }
        fetch(`/templates/${templateId}/job-history`)
            .then(r => r.json())
            .then(data => {
                if (!data.jobs || data.jobs.length === 0) { alert('No job history found.'); return; }
                const job = data.jobs[0];
                sampleDataCache = job.template_data || {};
                document.getElementById('json-input').value = JSON.stringify(sampleDataCache, null, 2);
                parseJSON();
                sampleDataBuildTable();
                alert('Loaded sample data from job ' + job.job_id.substring(0, 8));
            })
            .catch(() => alert('Failed to load job history.'));
    }

    function sampleDataSaveToServer() {
        if (!templateId) { alert('Save the template first before saving default sample data.'); return; }
        const data = getPreviewData();
        fetch(`/templates/${templateId}/sample-data`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify({ sample_data: data })
        })
        .then(r => r.json())
        .then(res => {
            if (res.status === 'ok') alert('✅ Sample data saved as default for this template.');
            else alert('❌ Failed to save.');
        })
        .catch(() => alert('❌ Network error.'));
    }

    function sampleDataReset() {
        if (!confirm('Reset sample data to default values?')) return;
        sampleDataCache = {};
        document.getElementById('json-input').value = '';
        document.getElementById('json-tree').innerHTML = '';
        sampleDataRows = [];
        sampleDataBuildTable();
    }

    // ── Multi-Page Preview (PDF.js) ───────────────────────────
    async function openPreview() {
        document.getElementById('previewOverlay').style.display = 'block';
        document.getElementById('previewCanvasContainer').innerHTML = `
            <div style="display:flex; align-items:center; justify-content:center; height:100%; color:var(--text-muted);">
                <div style="text-align:center;"><div style="font-size:32px; margin-bottom:12px;">⏳</div><div style="font-size:14px;">Generating PDF preview...</div></div>
            </div>
        `;

        const sampleData = getPreviewData();
        const allElements = flattenSections();
        const payload = {
            paper_width_mm: parseFloat(document.getElementById('paper-w').value),
            paper_height_mm: parseFloat(document.getElementById('paper-h').value),
            background_image_path: document.getElementById('bg-path').value,
            elements: { sections: sections, elements: allElements },
            styles: globalStyles,
            background_config: backgroundConfig,
            sample_data: sampleData,
            _token: '{{ csrf_token() }}'
        };

        try {
            const url = templateId
                ? "{{ $template->id ? route('admin.templates.preview-with-template', $template, false) : '' }}"
                : "{{ route('admin.templates.preview', [], false) }}";
            
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(payload)
            });

            const pdfData = await response.arrayBuffer();
            previewPdfData = pdfData;

            // Load with PDF.js
            pdfDoc = await pdfjsLib.getDocument({ data: pdfData }).promise;

            // Update UI
            document.getElementById('pageCount').textContent = 'of ' + pdfDoc.numPages;
            document.getElementById('pageInput').max = pdfDoc.numPages;
            previewCurrentPage = 1;

            // Rebuild canvas container
            document.getElementById('previewCanvasContainer').innerHTML = `
                <div style="position:relative; display:inline-block;">
                    <canvas id="previewCanvas" style="box-shadow:0 4px 30px rgba(0,0,0,0.5); background:white;"></canvas>
                    <div id="previewLoading" style="display:none; position:absolute; inset:0; background:rgba(255,255,255,0.8); display:flex; align-items:center; justify-content:center; border-radius:4px;">
                        <div style="text-align:center; color:#64748b;">
                            <div style="font-size:24px; margin-bottom:8px;">⏳</div>
                            <div style="font-size:13px; font-weight:500;">Rendering page...</div>
                        </div>
                    </div>
                </div>
            `;

            // Render first page
            previewRenderPage(1);
        } catch (err) {
            console.error('Preview error:', err);
            document.getElementById('previewCanvasContainer').innerHTML = `
                <div style="display:flex; align-items:center; justify-content:center; height:100%; color:var(--danger);">
                    <div style="text-align:center;"><div style="font-size:32px; margin-bottom:12px;">❌</div><div style="font-size:14px;">Failed to generate preview: ${err.message}</div></div>
                </div>
            `;
        }
    }

    async function previewRenderPage(pageNum) {
        if (!pdfDoc) return;
        previewCurrentPage = pageNum;
        
        const loadingEl = document.getElementById('previewLoading');
        if (loadingEl) loadingEl.style.display = 'flex';

        try {
            const page = await pdfDoc.getPage(pageNum);
            const scale = previewCurrentZoom;
            const viewport = page.getViewport({ scale: scale });
            
            const canvas = document.getElementById('previewCanvas');
            if (!canvas) return;
            
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            
            const ctx = canvas.getContext('2d');
            await page.render({ canvasContext: ctx, viewport: viewport }).promise;
            
            document.getElementById('pageInput').value = pageNum;
        } catch (err) {
            console.error('Render page error:', err);
        } finally {
            if (loadingEl) loadingEl.style.display = 'none';
        }
    }

    function previewPrevPage() {
        if (pdfDoc && previewCurrentPage > 1) {
            previewRenderPage(previewCurrentPage - 1);
        }
    }

    function previewNextPage() {
        if (pdfDoc && previewCurrentPage < pdfDoc.numPages) {
            previewRenderPage(previewCurrentPage + 1);
        }
    }

    function previewChangeZoom(value) {
        if (value === 'fit') {
            // Fit width: calculate scale to fit canvas container
            const container = document.getElementById('previewCanvasContainer');
            if (container && pdfDoc) {
                // We need page dimensions - use current page
                previewRenderPage(previewCurrentPage);
                // Set a reasonable zoom
                previewCurrentZoom = 1;
            }
            return;
        }
        previewCurrentZoom = parseFloat(value);
        if (pdfDoc) previewRenderPage(previewCurrentPage);
    }

    function previewDownloadPdf() {
        if (!previewPdfData) return;
        const blob = new Blob([previewPdfData], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (document.getElementById('tpl-name').value || 'template') + '-preview.pdf';
        a.click();
        URL.revokeObjectURL(url);
    }

    function closePreviewOverlay() {
        document.getElementById('previewOverlay').style.display = 'none';
        pdfDoc = null;
        previewPdfData = null;
    }

    // Page input change handler
    document.addEventListener('DOMContentLoaded', () => {
        const pageInput = document.getElementById('pageInput');
        if (pageInput) {
            pageInput.addEventListener('change', function() {
                let val = parseInt(this.value);
                if (pdfDoc) {
                    val = Math.max(1, Math.min(val, pdfDoc.numPages));
                    previewRenderPage(val);
                }
            });
        }
    });

    // ── Utility helpers ──────────────────────────────────────
    function flattenObject(obj, prefix = '') {
        let result = {};
        for (const [key, val] of Object.entries(obj)) {
            const fullKey = prefix ? `${prefix}.${key}` : key;
            if (val !== null && typeof val === 'object' && !Array.isArray(val)) {
                Object.assign(result, flattenObject(val, fullKey));
            } else if (Array.isArray(val) && val.length > 0 && typeof val[0] === 'object') {
                // For arrays of objects, take the first element's keys
                Object.assign(result, flattenObject(val[0], `${fullKey}[0]`));
            } else {
                result[fullKey] = val;
            }
        }
        return result;
    }

    function setNestedValue(obj, path, value) {
        const keys = path.split('.');
        let current = obj;
        for (let i = 0; i < keys.length - 1; i++) {
            const key = keys[i];
            // Handle array index
            const arrMatch = key.match(/^(.+?)\[(\d+)\]$/);
            if (arrMatch) {
                const arrKey = arrMatch[1];
                const idx = parseInt(arrMatch[2]);
                if (!current[arrKey]) current[arrKey] = [];
                if (!current[arrKey][idx]) current[arrKey][idx] = {};
                current = current[arrKey][idx];
            } else {
                if (!current[key]) current[key] = {};
                current = current[key];
            }
        }
        const lastKey = keys[keys.length - 1];
        current[lastKey] = value;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&').replace(/"/g, '"').replace(/'/g, ''').replace(/</g, '<').replace(/>/g, '>');
    }

    function escapeJs(str) {
        if (!str) return '';
        return String(str)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'")
            .replace(/"/g, '"')
            .replace(/\n/g, '\\n')
            .replace(/\r/g, '\\r');
    }
    function showTestPrint() { document.getElementById('test-print-modal').style.display='flex'; }
    function closeTestPrint() { document.getElementById('test-print-modal').style.display='none'; }
    function doTestPrint() {
        const agentId = document.getElementById('test-agent-id').value;
        const printerName = document.getElementById('test-printer-name').value;
        if (!agentId) return alert('Please select an agent');
        if (!printerName) return alert('Please select a printer');
        
        let sampleData = getPreviewData();

        const allElements = flattenSections();
        const payload = {
            template_data: {
                paper_width_mm: parseFloat(document.getElementById('paper-w').value),
                paper_height_mm: parseFloat(document.getElementById('paper-h').value),
                background_image_path: document.getElementById('bg-path').value,
                elements: { sections: sections, elements: allElements },
                styles: globalStyles,
                background_config: backgroundConfig
            },
            sample_data: sampleData,
            agent_id: agentId,
            printer_name: printerName,
            _token: '{{ csrf_token() }}'
        };

        const btn = document.querySelector('#test-print-modal .btn-primary');
        const oldText = btn.textContent;
        btn.textContent = '⏱ Sending...';
        btn.disabled = true;

        fetch("{{ route('admin.templates.test-print', [], false) }}", {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok') {
                alert('✅ Print job sent successfully! Job ID: ' + data.job_id);
                closeTestPrint();
            } else {
                alert('❌ Error: ' + (data.message || 'Unknown server error'));
            }
        })
        .finally(() => {
            btn.textContent = oldText;
            btn.disabled = false;
        });
    }

    // ── Export / Import ──────────────────────────────────────
    function exportTemplate() {
        const allElements = flattenSections();
        const data={name:document.getElementById('tpl-name').value,paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),elements:{sections:sections,elements:allElements},styles:globalStyles,background_config:backgroundConfig};
        const blob=new Blob([JSON.stringify(data,null,2)],{type:'application/json'});
        const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=data.name+'.json'; a.click();
    }
    function importTemplate() { document.getElementById('import-file').click(); }
    document.addEventListener('DOMContentLoaded',()=>{
        const inp=document.getElementById('import-file');
        if(inp) inp.addEventListener('change',e=>{
            const f=e.target.files[0]; if(!f)return;
            const r=new FileReader(); r.onload=ev=>{
                try{
                    const d=JSON.parse(ev.target.result);
                    if(d.sections) {
                        pushHistory();
                        sections = JSON.parse(JSON.stringify(d.sections));
                        SECTION_ORDER.forEach(key => {
                            if (!sections[key]) sections[key] = JSON.parse(JSON.stringify(SECTION_DEFAULTS[key]));
                        });
                        elements = sections.detail.elements || [];
                    } else if(d.elements) {
                        pushHistory();
                        initSections(d.elements);
                        elements = sections.detail.elements || [];
                    }
                    if(d.styles){globalStyles=d.styles;renderStyles();}
                    if(d.name){document.getElementById('tpl-name').value=d.name;}
                    if(d.paper_width_mm) document.getElementById('paper-w').value=d.paper_width_mm;
                    if(d.paper_height_mm) document.getElementById('paper-h').value=d.paper_height_mm;
                    updateCanvasSize();
                    renderElements();
                    updateSectionsList();
                }catch(err){alert('Invalid template file');}
            }; r.readAsText(f);
        });
    });

    // ── Keyboard ─────────────────────────────────────────────
    window.addEventListener('keydown', (e) => {
        const inField = e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA';
        if ((e.ctrlKey||e.metaKey) && e.key==='s') { e.preventDefault(); saveTemplate(); return; }
        if ((e.ctrlKey||e.metaKey) && e.key==='z') { e.preventDefault(); undo(); return; }
        if ((e.ctrlKey||e.metaKey) && (e.key==='y' || (e.shiftKey && e.key==='Z'))) { e.preventDefault(); redo(); return; }
        if ((e.ctrlKey||e.metaKey) && e.key==='d') { e.preventDefault(); duplicateSelected(); return; }
        if ((e.ctrlKey||e.metaKey) && e.key==='g') { e.preventDefault(); groupElements(); return; }
        if (inField) return;
        if (activeIds.length === 0) return;
        const stp = (e.ctrlKey||e.metaKey) ? 0.1 : (e.shiftKey ? 5 : 1); let mvd = false;
        if (e.key==='ArrowUp')    { activeIds.forEach(id=>{const el=elements.find(i=>i.id===id);if(el&&!el.locked)el.y=parseFloat((el.y-stp).toFixed(2));}); mvd=true; }
        if (e.key==='ArrowDown')  { activeIds.forEach(id=>{const el=elements.find(i=>i.id===id);if(el&&!el.locked)el.y=parseFloat((el.y+stp).toFixed(2));}); mvd=true; }
        if (e.key==='ArrowLeft')  { activeIds.forEach(id=>{const el=elements.find(i=>i.id===id);if(el&&!el.locked)el.x=parseFloat((el.x-stp).toFixed(2));}); mvd=true; }
        if (e.key==='ArrowRight') { activeIds.forEach(id=>{const el=elements.find(i=>i.id===id);if(el&&!el.locked)el.x=parseFloat((el.x+stp).toFixed(2));}); mvd=true; }
        if (e.key==='Delete'||e.key==='Backspace') { deleteActive(); e.preventDefault(); return; }
        if (mvd) { pushHistory(); e.preventDefault(); renderElements(); updateInspector(); }
    });

    init();
</script>

<!-- ── Formula Editor Modal ────────────────────────────────────────── -->
<div id="formulaEditorModal" class="hidden fixed inset-0 bg-black/50 z-[3000] flex items-center justify-center" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:3000; align-items:center; justify-content:center;" onclick="if(event.target===this)closeFormulaEditor()">
    <div class="bg-white rounded-lg shadow-2xl w-[700px] max-h-[80vh] flex flex-col" style="background:var(--surface); border:1px solid var(--border); border-radius:12px; box-shadow:0 20px 60px rgba(0,0,0,0.5); width:720px; max-height:80vh; display:flex; flex-direction:column;" onclick="event.stopPropagation()">
        <!-- Header -->
        <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 18px; border-bottom:1px solid var(--border); background:var(--bg); border-radius:12px 12px 0 0;">
            <h3 style="margin:0; font-size:15px; font-weight:600; color:var(--text); display:flex; align-items:center; gap:8px;">
                <span style="background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; padding:2px 8px; border-radius:4px; font-weight:700; font-size:13px;">fx</span>
                Formula Editor
            </h3>
            <button onclick="closeFormulaEditor()" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:18px; padding:4px;">✕</button>
        </div>

        <!-- Body -->
        <div style="flex:1; overflow:hidden; display:flex; flex-direction:row; min-height:0;">
            <!-- Left: Fields + Functions -->
            <div style="width:220px; border-right:1px solid var(--border); overflow-y:auto; padding:12px; flex-shrink:0;">
                <!-- Fields -->
                <div style="margin-bottom:16px;">
                    <div style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:flex; align-items:center; gap:4px;">
                        <span>📋</span> Fields
                        <button onclick="refreshSchemaFields()" style="margin-left:auto; background:none; border:none; color:var(--primary); cursor:pointer; font-size:10px; padding:0;">↻</button>
                    </div>
                    <div id="fieldList" style="display:flex; flex-direction:column; gap:2px;"></div>
                </div>
                <!-- Functions -->
                <div>
                    <div style="font-size:11px; font-weight:600; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:6px; display:flex; align-items:center; gap:4px;">
                        <span>🔧</span> Functions
                    </div>
                    <div id="functionBrowser" style="display:flex; flex-direction:column; gap:2px;"></div>
                </div>
            </div>

            <!-- Right: Expression + Preview -->
            <div style="flex:1; display:flex; flex-direction:column; padding:14px; min-width:0;">
                <!-- Expression input -->
                <label style="font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:4px;">Expression</label>
                <div style="position:relative;">
                    <textarea id="formulaExpression"
                        class="w-full border rounded p-2 text-sm font-mono"
                        placeholder="Enter formula expression... (e.g., qty * price OR SUM(items.amount))"
                        oninput="onFormulaInput()"
                        style="width:100%; min-height:80px; background:var(--bg); border:1px solid var(--border); color:var(--text); font-family:monospace; font-size:13px; padding:10px; border-radius:6px; resize:vertical; outline:none; line-height:1.5;"></textarea>
                </div>

                <!-- Validation status -->
                <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                    <button onclick="validateExpression()" style="background:var(--surface-hover); border:1px solid var(--border); color:var(--text); padding:3px 10px; border-radius:4px; font-size:11px; cursor:pointer; display:flex; align-items:center; gap:4px;">
                        🔍 Validate
                    </button>
                    <span id="validationStatus" style="font-size:12px; font-weight:500; color:var(--text-muted);">Enter an expression</span>
                </div>

                <!-- Preview result -->
                <div style="margin-top:8px;">
                    <div style="font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:3px;">Preview with sample data</div>
                    <div id="previewResult" style="background:var(--bg); border:1px solid var(--border); border-radius:6px; padding:8px 10px; font-family:monospace; font-size:13px; color:var(--text); min-height:28px; word-break:break-all;">
                        Result: —
                    </div>
                </div>

                <!-- Formula Library -->
                <div style="margin-top:12px; flex-shrink:0;">
                    <div style="font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:4px; display:flex; align-items:center; gap:4px;">
                        <span>📚</span> Formula Library
                        <button onclick="saveToFormulaLibrary()" style="margin-left:auto; background:none; border:1px dashed var(--border); color:var(--primary); padding:2px 8px; border-radius:4px; font-size:10px; cursor:pointer;">+ Save Current</button>
                    </div>
                    <div id="formulaLibrary" style="display:flex; flex-direction:column; gap:3px; max-height:100px; overflow-y:auto;"></div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div style="display:flex; align-items:center; justify-content:flex-end; gap:8px; padding:12px 18px; border-top:1px solid var(--border); background:var(--bg); border-radius:0 0 12px 12px;">
            <button onclick="closeFormulaEditor()" style="padding:7px 16px; font-size:12px; border:1px solid var(--border); border-radius:6px; background:var(--surface); color:var(--text); cursor:pointer;">Cancel</button>
            <button onclick="saveFormula()" style="padding:7px 16px; font-size:12px; border:none; border-radius:6px; background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; cursor:pointer; font-weight:600;">Save Formula</button>
        </div>
    </div>
</div>

<script>
    // ── Formula Editor State ────────────────────────────────────
    let formulaEditorColumn = null;
    let formulaEditorTableId = null;
    let formulaLibrary = [];

    // Load formula library from localStorage
    try {
        formulaLibrary = JSON.parse(localStorage.getItem('printHubFormulaLibrary') || '[]');
    } catch(e) { formulaLibrary = []; }

    // ── Open / Close Formula Editor ────────────────────────────
    function openFormulaEditor(columnIndex, tableElId, currentExpression) {
        formulaEditorColumn = columnIndex;
        formulaEditorTableId = tableElId;
        document.getElementById('formulaEditorModal').style.display = 'flex';
        document.getElementById('formulaExpression').value = currentExpression || '';
        document.getElementById('validationStatus').textContent = 'Enter an expression';
        document.getElementById('validationStatus').style.color = 'var(--text-muted)';
        document.getElementById('previewResult').textContent = 'Result: —';
        loadFunctionBrowser();
        loadSchemaFields();
        renderFormulaLibrary();

        // Focus the textarea
        setTimeout(() => {
            document.getElementById('formulaExpression').focus();
        }, 100);
    }

    function closeFormulaEditor() {
        document.getElementById('formulaEditorModal').style.display = 'none';
        formulaEditorColumn = null;
        formulaEditorTableId = null;
    }

    // ── Function Browser ─────────────────────────────────────
    function loadFunctionBrowser() {
        fetch('/api/v1/formula/functions')
            .then(r => r.json())
            .then(functions => {
                const container = document.getElementById('functionBrowser');
                if (!container) return;

                if (!functions || functions.length === 0) {
                    container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px;">No functions available</div>';
                    return;
                }

                const categories = {};
                functions.forEach(f => {
                    if (!categories[f.category]) categories[f.category] = [];
                    categories[f.category].push(f);
                });

                const categoryIcons = {
                    'Math': '🔢',
                    'String': '📝',
                    'Date': '📅',
                    'Logical': '🔗',
                    'Conversion': '🔄',
                    'Other': '📦'
                };

                container.innerHTML = Object.entries(categories).map(([cat, funcs]) => `
                    <div style="margin-bottom:2px;">
                        <div style="font-size:10px; font-weight:600; color:var(--text-muted); padding:2px 4px; margin-bottom:1px; display:flex; align-items:center; gap:3px;">
                            ${categoryIcons[cat] || '📦'} ${cat}
                            <span style="color:var(--text-muted); font-weight:400; font-size:9px;">(${funcs.length})</span>
                        </div>
                        ${funcs.map(f => `
                            <div style="display:flex; align-items:center; justify-content:space-between; padding:3px 8px; border-radius:4px; cursor:pointer; transition:background 0.15s;"
                                 onmouseover="this.style.background='var(--surface-hover)'"
                                 onmouseout="this.style.background='transparent'"
                                 onclick="insertFunction('${f.name}')"
                                 title="${f.description || ''}\n${f.syntax || ''}">
                                <span style="font-size:11px; font-family:monospace; color:var(--primary); font-weight:500;">${f.name}</span>
                                <span style="font-size:9px; color:var(--text-muted); opacity:0.6;" title="${f.description || ''}">ℹ️</span>
                            </div>
                        `).join('')}
                    </div>
                `).join('');
            })
            .catch(err => {
                console.error('Failed to load functions:', err);
                const container = document.getElementById('functionBrowser');
                if (container) container.innerHTML = '<div style="font-size:10px; color:var(--danger); padding:4px;">Failed to load functions</div>';
            });
    }

    // ── Schema Fields ──────────────────────────────────────────
    function loadSchemaFields() {
        const fields = getSchemaFieldKeys();
        const container = document.getElementById('fieldList');
        if (!container) return;

        if (!fields || fields.length === 0) {
            container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px;">No fields available. Load a schema or sample data.</div>';
            return;
        }

        container.innerHTML = fields.map(f => `
            <div style="display:flex; align-items:center; justify-content:space-between; padding:3px 8px; border-radius:4px; cursor:pointer; transition:background 0.15s;"
                 onmouseover="this.style.background='var(--surface-hover)'"
                 onmouseout="this.style.background='transparent'"
                 onclick="insertField('${f}')">
                <span style="font-size:11px; font-family:monospace; color:var(--text);">${f}</span>
                <span style="font-size:9px; color:var(--primary);">[Insert]</span>
            </div>
        `).join('');
    }

    function refreshSchemaFields() {
        loadSchemaFields();
    }

    // ── Insert Functions / Fields ──────────────────────────────
    function insertFunction(funcName) {
        const input = document.getElementById('formulaExpression');
        const cursorPos = input.selectionStart;
        const text = input.value;
        const insertion = funcName + '()';
        input.value = text.slice(0, cursorPos) + insertion + text.slice(cursorPos);
        input.focus();
        // Place cursor inside parentheses
        const newPos = cursorPos + funcName.length + 1;
        input.selectionStart = input.selectionEnd = newPos;
        onFormulaInput();
    }

    function insertField(fieldName) {
        const input = document.getElementById('formulaExpression');
        const cursorPos = input.selectionStart;
        const text = input.value;
        // Check if we need to add quotes for string fields or just the name
        input.value = text.slice(0, cursorPos) + fieldName + text.slice(cursorPos);
        input.focus();
        input.selectionStart = input.selectionEnd = cursorPos + fieldName.length;
        onFormulaInput();
    }

    // ── Expression Input Handling (debounced) ──────────────────
    let formulaValidationTimeout = null;

    function onFormulaInput() {
        clearTimeout(formulaValidationTimeout);
        formulaValidationTimeout = setTimeout(() => {
            validateExpression();
        }, 400);
    }

    // ── Validation ─────────────────────────────────────────────
    async function validateExpression() {
        const expr = document.getElementById('formulaExpression').value;
        const status = document.getElementById('validationStatus');
        const result = document.getElementById('previewResult');

        if (!expr.trim()) {
            status.textContent = 'Enter an expression';
            status.style.color = 'var(--text-muted)';
            result.textContent = 'Result: —';
            return;
        }

        try {
            const response = await fetch('/api/v1/formula/validate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ expression: expr })
            });
            const validation = await response.json();

            if (validation.valid) {
                status.innerHTML = '✅ Valid';
                status.style.color = '#22c55e';
                // Try to evaluate with sample data
                await evaluateExpression(expr);
            } else {
                status.innerHTML = '❌ ' + (validation.error || 'Syntax error');
                status.style.color = '#ef4444';
                result.textContent = 'Result: —';
            }
        } catch (e) {
            console.error('Validation failed:', e);
            status.innerHTML = '❌ Network error';
            status.style.color = '#ef4444';
        }
    }

    async function evaluateExpression(expr) {
        const result = document.getElementById('previewResult');
        try {
            // Get sample data from the designer's sample data cache
            const sampleData = window.sampleDataCache || {};
            // Also try to get from JSON input
            if (Object.keys(sampleData).length === 0) {
                try {
                    const jsonInput = document.getElementById('json-input');
                    if (jsonInput && jsonInput.value) {
                        Object.assign(sampleData, JSON.parse(jsonInput.value));
                    }
                } catch(e) {}
            }

            const response = await fetch('/api/v1/formula/evaluate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    expression: expr,
                    data: sampleData
                })
            });
            const data = await response.json();

            if (data.error) {
                result.textContent = 'Evaluation error: ' + data.error;
                result.style.color = '#ef4444';
            } else {
                let displayResult = data.result;
                if (displayResult === null || displayResult === undefined) {
                    displayResult = 'null';
                } else if (typeof displayResult === 'boolean') {
                    displayResult = displayResult ? 'true' : 'false';
                } else if (typeof displayResult === 'object') {
                    displayResult = JSON.stringify(displayResult);
                }
                result.textContent = 'Result: ' + String(displayResult);
                result.style.color = 'var(--text)';
            }
        } catch (e) {
            result.textContent = 'Evaluation error';
            result.style.color = '#ef4444';
        }
    }

    // ── Save Formula ───────────────────────────────────────────
    function saveFormula() {
        const expr = document.getElementById('formulaExpression').value;
        if (!expr.trim()) {
            alert('Please enter an expression before saving.');
            return;
        }

        // Find the table element and column
        if (formulaEditorTableId) {
            // Try to find in sections first, then flat elements
            let tableEl = null;
            if (window.sections) {
                for (const key of (window.SECTION_ORDER || [])) {
                    const sec = window.sections[key];
                    if (sec && sec.elements) {
                        tableEl = sec.elements.find(e => e.id === formulaEditorTableId);
                        if (tableEl) break;
                    }
                }
            }
            if (!tableEl && window.elements) {
                tableEl = window.elements.find(e => e.id === formulaEditorTableId);
            }
            if (tableEl && tableEl.columns && formulaEditorColumn !== null && formulaEditorColumn < tableEl.columns.length) {
                tableEl.columns[formulaEditorColumn].expression = expr;
                tableEl.columns[formulaEditorColumn].computed = true;
            }
        }

        closeFormulaEditor();

        // Update the inspector to reflect changes
        if (typeof updateInspector === 'function') {
            updateInspector();
        }
        if (typeof renderElements === 'function') {
            renderElements();
        }
    }

    // ── Formula Library ────────────────────────────────────────
    function saveToFormulaLibrary() {
        const expr = document.getElementById('formulaExpression').value;
        if (!expr.trim()) {
            alert('Please enter an expression to save.');
            return;
        }

        const name = prompt('Formula name:', '');
        if (!name || !name.trim()) return;

        // Check for duplicates
        const existing = formulaLibrary.findIndex(f => f.name === name.trim());
        if (existing !== -1) {
            if (!confirm('A formula named "' + name.trim() + '" already exists. Overwrite?')) return;
            formulaLibrary[existing].expression = expr;
        } else {
            formulaLibrary.push({ name: name.trim(), expression: expr });
        }

        localStorage.setItem('printHubFormulaLibrary', JSON.stringify(formulaLibrary));
        renderFormulaLibrary();
    }

    function loadFromLibrary(expression) {
        document.getElementById('formulaExpression').value = expression;
        validateExpression();
    }

    function deleteFromLibrary(index) {
        if (!confirm('Delete this saved formula?')) return;
        formulaLibrary.splice(index, 1);
        localStorage.setItem('printHubFormulaLibrary', JSON.stringify(formulaLibrary));
        renderFormulaLibrary();
    }

    function renderFormulaLibrary() {
        const container = document.getElementById('formulaLibrary');
        if (!container) return;

        if (!formulaLibrary || formulaLibrary.length === 0) {
            container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px;">No saved formulas yet</div>';
            return;
        }

        container.innerHTML = formulaLibrary.map((f, i) => `
            <div style="display:flex; align-items:stretch; gap:4px; border-radius:4px; overflow:hidden; border:1px solid var(--border);">
                <div style="flex:1; padding:4px 8px; cursor:pointer; min-width:0;"
                     onclick="loadFromLibrary('${escapeJs(f.expression)}')"
                     title="${escapeHtml(f.expression)}">
                    <div style="font-size:11px; font-weight:500; color:var(--text);">${escapeHtml(f.name)}</div>
                    <div style="font-size:9px; color:var(--text-muted); font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(f.expression)}</div>
                </div>
                <button onclick="deleteFromLibrary(${i})"
                        style="background:none; border:none; border-left:1px solid var(--border); color:var(--danger); cursor:pointer; font-size:12px; padding:0 6px; flex-shrink:0;"
                        title="Delete">✕</button>
            </div>
        `).join('');
    }
</script>
@endsection
