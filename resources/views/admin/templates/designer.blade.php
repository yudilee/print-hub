@extends('admin.layout')

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
        flex: 1; background: #0b0f19; overflow: auto; position: relative;
        display: flex; align-items: flex-start; justify-content: flex-start;
        padding: 40px; 
    }
    .designer-right-props {
        width: 320px; background: var(--surface); border-left: 1px solid var(--border);
        display: flex; flex-direction: column;
    }
    
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

    #canvas-wrapper { position: relative; background: #1e293b; box-shadow: 0 0 50px rgba(0,0,0,0.5); transform-origin: top left; }
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
    #coord-tip { position:fixed; background:rgba(15,23,42,0.9); color:#94a3b8; font-size:10px; padding:3px 7px; border-radius:4px; pointer-events:none; display:none; z-index:8000; font-family:monospace; }

    /* Snap grid overlay */
    #snap-grid { position:absolute; top:0; left:0; width:100%; height:100%; pointer-events:none; display:none; }

    /* Minimap */
    #minimap { position:absolute; bottom:10px; right:10px; background:rgba(15,23,42,0.85); border:1px solid var(--border); border-radius:6px; overflow:hidden; cursor:pointer; }
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
            <button onclick="showPreview()" class="btn btn-success btn-sm">👁 Preview</button>
            <button onclick="showTestPrint()" class="btn btn-warning btn-sm">🖨 Print Test</button>
            <button onclick="exportTemplate()" class="btn btn-secondary btn-sm" title="Export JSON">↓ Export</button>
            <button onclick="importTemplate()" class="btn btn-secondary btn-sm" title="Import JSON">↑ Import</button>
            <input type="file" id="import-file" accept=".json" style="display:none">
            <button onclick="window.location.href='{{ route('admin.templates') }}'" class="btn btn-secondary btn-sm">Discard</button>
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
        <div class="designer-left-toolbar">
            <button onclick="addElement('field')" class="tool-btn" title="Add Data Field (T)">T</button>
            <button onclick="addElement('label')" class="tool-btn" title="Add Static Label (L)">Aa</button>
            <button onclick="addElement('table')" class="tool-btn" title="Add Data Table">▦</button>
            <button onclick="addElement('line')" class="tool-btn" title="Add Separator Line">—</button>
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

<!-- Preview Modal -->
<div id="preview-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.8); z-index:2000; align-items:center; justify-content:center;">
    <div style="background:var(--surface); width:90%; height:90%; border-radius:12px; display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:1rem; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.1rem;">Template Preview</h3>
            <button onclick="closePreview()" class="action-btn">Close</button>
        </div>
        <div style="flex:1; display:flex;">
            <div style="width:300px; border-right:1px solid var(--border); padding:1rem; display:flex; flex-direction:column;">
                <label style="font-size:0.8rem; color:var(--text-muted); margin-bottom:0.5rem;">Sample Data (JSON)</label>
                <textarea id="preview-json" style="flex:1; background:var(--bg); color:var(--text); border:1px solid var(--border); border-radius:4px; font-family:monospace; font-size:11px; padding:8px; resize:none;"></textarea>
                <button onclick="refreshPreview()" class="btn btn-primary btn-sm" style="margin-top:1rem;">Refresh Preview</button>
            </div>
            <div style="flex:1; background:#000;">
                <iframe id="preview-iframe" style="width:100%; height:100%; border:none;"></iframe>
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
    const BASE_SCALE = 4;
    let zoomLevel = 1.0;
    let elements = @json($template->elements ?? []);
    let globalStyles = @json($template->styles ?? []);
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
    const GUIDE_PROXIMITY_MM = 2;

    // ── Undo / Redo ──────────────────────────────────────────
    function pushHistory() {
        undoStack.push(JSON.stringify(elements));
        if (undoStack.length > 60) undoStack.shift();
        redoStack = [];
        updateUndoButtons();
    }
    function undo() {
        if (!undoStack.length) return;
        redoStack.push(JSON.stringify(elements));
        elements = JSON.parse(undoStack.pop());
        activeIds = []; activeId = null;
        renderElements(); updateInspector(); updateUndoButtons();
    }
    function redo() {
        if (!redoStack.length) return;
        undoStack.push(JSON.stringify(elements));
        elements = JSON.parse(redoStack.pop());
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
        elements.forEach(el => {
            if (el.hidden) return;
            ctx.fillStyle = el.type === 'label' ? '#64748b' : el.type === 'table' ? '#3b82f6' : el.type === 'line' ? '#ef4444' : '#0ea5e9';
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
        elements.forEach((el, idx) => {
            if (!el.id) el.id = 'el_' + Date.now() + '_' + idx;
        });
        updateCanvasSize(); renderElements(); renderStyles();
        loadSelectedSchema();
        document.getElementById('canvas').addEventListener('mousedown', canvasMouseDown);
        document.getElementById('canvas').addEventListener('contextmenu', canvasContextMenu);
        document.addEventListener('click', () => hideCtxMenu());
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
            const typeIcon = el.type === 'table' ? '▦' : el.type === 'label' ? 'Aa' : el.type === 'line' ? '—' : 'T';
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
        const c = document.getElementById('canvas');
        c.style.width = (w * BASE_SCALE) + 'px'; c.style.height = (h * BASE_SCALE) + 'px';
        c.style.transform = `scale(${zoomLevel})`;
        drawSnapGrid(); drawMinimap();
    }

    // ── Add Element ──────────────────────────────────────────
    function addElement(type) {
        pushHistory();
        const id = 'el_new_' + Date.now();
        let el = { id, type, key: '', x: 10, y: 10, width: 50, height: 10, font_size: 10, bold: false, border: false, align: 'L' };
        if (type === 'field')  { el.key = 'field_key'; }
        if (type === 'label')  { el.key = ''; el.text = 'Label Text'; el.width = 60; }
        if (type === 'table')  { el.key = 'items'; el.width = 180; el.columns = [{ label: 'Item', key: 'name', width: 100 }, { label: 'Qty', key: 'qty', width: 40, align: 'R' }]; }
        if (type === 'line')   { el.key = ''; el.width = 180; el.height = 0.5; el.lineColor = '#000000'; }
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
        if (draggingEl || resizingEl) { pushHistory(); renderElements(); drawMinimap(); }
        draggingEl = null; resizingEl = null; hideCoordTip(); clearSmartGuides();
    });

    // ── Render ───────────────────────────────────────────────
    function renderElements() {
        const c = document.getElementById('canvas');
        c.querySelectorAll('.design-element').forEach(el => el.remove());
        elements.forEach(el => {
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
            div.style.top = (displayEl.y * BASE_SCALE) + 'px';
            div.style.width = (displayEl.width * BASE_SCALE) + 'px';
            div.style.height = ((displayEl.height || 10) * BASE_SCALE) + 'px';

            if (displayEl.type === 'line') {
                div.innerHTML = `<div style="width:100%; height:${Math.max(1, displayEl.height*BASE_SCALE)}px; background:${displayEl.lineColor||'#000'}; border-radius:1px;"></div>`;
            } else if (displayEl.type === 'label') {
                if (displayEl.border) div.style.border = '1px solid #cbd5e1';
                div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; color:#1e293b; padding:2px; height:100%; overflow:hidden; font-weight:${displayEl.bold?'bold':'normal'}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}; background:rgba(100,116,139,0.08);">${displayEl.text || 'Label'}</div>`;
            } else if (displayEl.type === 'table') {
                if (displayEl.border) div.style.border = '1px solid #cbd5e1';
                const cols = displayEl.columns || [];
                const colsHtml = cols.map(c => `<td style="border:1px solid #94a3b8; padding:1px 3px; font-size:${displayEl.font_size*BASE_SCALE*0.18}px; font-weight:bold; color:#1e40af; white-space:nowrap; overflow:hidden;">${c.label}</td>`).join('');
                // Live data: show sample rows
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
                    rowsHtml = '<tr>' + cols.map(c => '<td style="border:1px solid #e2e8f0; padding:1px 3px; font-size:' + (displayEl.font_size*BASE_SCALE*0.16) + 'px; color:#64748b;">{{' + c.key + '}}</td>').join('') + '</tr>';
                }
                div.innerHTML = `<table style="border-collapse:collapse; width:100%; table-layout:fixed;"><tr>${colsHtml}</tr>${rowsHtml}</table>`;
            } else {
                // Field element — live data preview
                if (displayEl.border) div.style.border = '1px solid #1e293b';
                const liveVal = getLiveDisplayValue(displayEl);
                if (liveVal !== null) {
                    div.classList.add('field-resolved');
                    div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; color:#0f172a; padding:2px; height:100%; overflow:hidden; font-weight:${displayEl.bold?'bold':'normal'}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}">${liveVal}</div>`;
                } else {
                    if (liveDataMode) div.classList.add('field-unresolved');
                    div.innerHTML = `<div style="font-size:${displayEl.font_size*BASE_SCALE*0.2}px; color:#1e293b; padding:2px; height:100%; overflow:hidden; font-weight:${displayEl.bold?'bold':'normal'}; text-align:${displayEl.align==='C'?'center':(displayEl.align==='R'?'right':'left')}">@{{ ${displayEl.key} }}</div>`;
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
        } else {
            html += `
            <div class="props-section"><div class="props-label">Global Style</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Link</div><div class="prop-val"><select onchange="updateElProps('styleIdx',this.value==='none'?undefined:parseInt(this.value))" style="color:var(--primary)"><option value="none">Manual</option>${globalStyles.map((s,i)=>`<option value="${i}" ${el.styleIdx===i?'selected':''}>${s.name}</option>`).join('')}</select></div></div>
            </div></div>
            <div class="props-section"><div class="props-label">Appearance</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">FontSize</div><div class="prop-val"><input type="number" value="${el.font_size}" oninput="updateElProps('font_size',parseInt(this.value))" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">Align</div><div class="prop-val"><select onchange="updateElProps('align',this.value)"><option value="L" ${el.align==='L'?'selected':''}>Left</option><option value="C" ${el.align==='C'?'selected':''}>Center</option><option value="R" ${el.align==='R'?'selected':''}>Right</option></select></div></div>
                <div class="prop-item"><div class="prop-key">Bold</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.bold?'checked':''} onchange="updateElProps('bold',this.checked)" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">Border</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.border?'checked':''} onchange="updateElProps('border',this.checked)"></div></div>
            </div></div>`;

            if (el.type === 'field') {
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
            }
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
                    <div class="prop-item" style="background:rgba(255,255,255,0.03);"><div class="prop-key">Col ${idx+1}</div><div class="prop-val"><button onclick="deleteCol(${idx})" style="color:var(--danger);background:none;border:none;cursor:pointer;font-size:10px;">[×]</button></div></div>
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
            });
            html += `</div><div style="padding:0.5rem;"><button onclick="addCol()" class="btn btn-secondary btn-sm" style="width:100%;">+ Add Column</button></div></div>`;
        }

        html += `<div style="padding:0.75rem;display:flex;gap:0.5rem;">
            <button onclick="duplicateSelected()" class="btn btn-secondary btn-sm" style="flex:1;">⧉ Dup</button>
            <button onclick="deleteActive()" class="btn btn-danger btn-sm" style="flex:1;">🗑 Delete</button>
        </div>`;
        cont.innerHTML = html;
    }

    function updateCol(idx, prop, val) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns[idx]){el.columns[idx][prop]=val;renderElements();if(prop==='format_type')updateInspector();} }
    function addCol() { const el=elements.find(e=>e.id===activeId); if(el&&el.type==='table'){if(!el.columns)el.columns=[];el.columns.push({label:'Col',key:'key',width:30,align:'L'});updateInspector();} }
    function deleteCol(idx) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns.length>1){el.columns.splice(idx,1);updateInspector();renderElements();} }
    function updateElProps(prop,val) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el){el[prop]=val;renderElements();updateInspector();} }
    function deleteActive() { if(!confirm('Delete selected element(s)?'))return; pushHistory(); elements=elements.filter(el=>!activeIds.includes(el.id)); activeIds=[];activeId=null; renderElements();updateInspector(); }

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
        const payload={name,paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),background_image_path:document.getElementById('bg-path').value,elements,styles:globalStyles,background_config:backgroundConfig,_token:'{{ csrf_token() }}'};
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
    function showPreview() { document.getElementById('preview-modal').style.display='flex'; refreshPreview(); }
    function closePreview() { document.getElementById('preview-modal').style.display='none'; document.getElementById('preview-iframe').src=''; }
    function refreshPreview() {
        const sampleStr=document.getElementById('preview-json').value||'{}'; let sampleData={}; try{sampleData=JSON.parse(sampleStr);}catch(e){}
        const payload={paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),background_image_path:document.getElementById('bg-path').value,elements,styles:globalStyles,background_config:backgroundConfig,sample_data:sampleData,_token:'{{ csrf_token() }}'};
        fetch("{{ route('admin.templates.preview', [], false) }}",{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)}).then(r=>r.blob()).then(blob=>{document.getElementById('preview-iframe').src=URL.createObjectURL(blob);});
    }
    function showTestPrint() { document.getElementById('test-print-modal').style.display='flex'; }
    function closeTestPrint() { document.getElementById('test-print-modal').style.display='none'; }
    function closeTestPrint() { document.getElementById('test-print-modal').style.display='none'; }
    function doTestPrint() {
        const agentId = document.getElementById('test-agent-id').value;
        const printerName = document.getElementById('test-printer-name').value;
        if (!agentId) return alert('Please select an agent');
        if (!printerName) return alert('Please select a printer');
        
        const sampleStr = document.getElementById('preview-json').value || '{}';
        let sampleData = {};
        try { sampleData = JSON.parse(sampleStr); } catch(e) { return alert('Invalid JSON in sample data'); }

        const payload = {
            template_data: {
                paper_width_mm: parseFloat(document.getElementById('paper-w').value),
                paper_height_mm: parseFloat(document.getElementById('paper-h').value),
                background_image_path: document.getElementById('bg-path').value,
                elements,
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
        const data={name:document.getElementById('tpl-name').value,paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),elements,styles:globalStyles,background_config:backgroundConfig};
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
                    if(d.elements){pushHistory();elements=d.elements;renderElements();}
                    if(d.styles){globalStyles=d.styles;renderStyles();}
                    if(d.name){document.getElementById('tpl-name').value=d.name;}
                    if(d.paper_width_mm) document.getElementById('paper-w').value=d.paper_width_mm;
                    if(d.paper_height_mm) document.getElementById('paper-h').value=d.paper_height_mm;
                    updateCanvasSize();
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

    function switchTab(tab) {
        document.querySelectorAll('.tab-item').forEach(i => i.classList.remove('active'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const t = document.querySelector(`.tab-item[onclick*="${tab}"]`);
        if (t) t.classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
        if (tab === 'layers') updateLayersList();
    }

    function parseJSON() {
        const input = document.getElementById('json-input').value;
        try {
            const data = JSON.parse(input);
            const tree = document.getElementById('json-tree');
            tree.innerHTML = ''; renderJSONNode(data, '', tree);
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
        const id = 'el_new_' + Date.now();
        elements.push({ id, type: 'field', key: path, x: 50, y: 50, width: 60, height: 10, font_size: 10, bold: false, border: false, align: 'L' });
        renderElements(); selectElements([id]);
    }

    function updateLayersList() {
        const list = document.getElementById('layers-list'); list.innerHTML = '';
        elements.forEach((el, idx) => {
            const div = document.createElement('div'); div.className = 'prop-item' + (activeIds.includes(el.id) ? ' active' : '');
            div.style.padding = '8px'; div.onclick = () => selectElements([el.id]);
            div.innerHTML = `<div style="font-size:11px; flex:1">#${idx+1} ${el.key}</div>`;
            list.appendChild(div);
        });
    }

    init();
</script>
@endsection
