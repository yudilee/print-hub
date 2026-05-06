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

    #canvas-wrapper { position: relative; box-shadow: 0 0 50px rgba(0,0,0,0.5); }
    #canvas { position: absolute; top: 0; left: 0; background: white; overflow: hidden; transform-origin: top left; }
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

    /* ── Field Explorer Styles ───────────────────────── */
    .fe-search { width:100%; padding:6px 8px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:11px; outline:none; box-sizing:border-box; }
    .fe-search:focus { border-color:var(--primary); }
    .fe-group { margin-bottom:6px; }
    .fe-group-header { display:flex; align-items:center; gap:4px; padding:4px 6px; font-size:10px; font-weight:600; color:var(--text-muted); cursor:pointer; border-radius:4px; user-select:none; }
    .fe-group-header:hover { background:var(--surface-hover); }
    .fe-group-header .arrow { transition:transform 0.15s; font-size:8px; }
    .fe-group-header .arrow.collapsed { transform:rotate(-90deg); }
    .fe-item { display:flex; align-items:center; gap:4px; padding:3px 6px 3px 18px; border-radius:4px; cursor:pointer; font-size:11px; color:var(--text); transition:background 0.1s; }
    .fe-item:hover { background:var(--surface-hover); }
    .fe-item.dragging { opacity:0.5; }
    .fe-item .fe-type { font-size:8px; padding:0 4px; border-radius:3px; font-weight:600; text-transform:uppercase; opacity:0.7; }
    .fe-item .fe-status { width:14px; height:14px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:8px; flex-shrink:0; }
    .fe-status-used { background:rgba(34,197,94,0.2); color:#22c55e; }
    .fe-status-unused { background:rgba(148,163,184,0.2); color:#94a3b8; }
    .fe-status-missing { background:rgba(239,68,68,0.2); color:#ef4444; }
    .fe-drag-ghost { position:fixed; pointer-events:none; z-index:9999; background:var(--primary); color:white; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,0.3); opacity:0.9; transform:translate(-50%,-50%); }
    .canvas-drop-highlight { outline:3px dashed var(--primary) !important; outline-offset:-3px !important; transition:outline 0.15s; }
    
    /* ── Binding Status on Canvas Elements ────────────── */
    .design-element .bind-indicator { position:absolute; top:-6px; right:-6px; width:14px; height:14px; border-radius:50%; z-index:10; display:flex; align-items:center; justify-content:center; font-size:7px; font-weight:bold; border:2px solid var(--surface); box-shadow:0 1px 4px rgba(0,0,0,0.3); }
    .bind-bound { background:#22c55e; color:white; }
    .bind-unbound { background:#6b7280; color:white; }
    .bind-resolved { background:#3b82f6; color:white; }
    .bind-unresolved { background:#f59e0b; color:white; }
    
    /* ── Field Preview Tooltip ────────────────────────── */
    .fe-preview-tip { position:fixed; z-index:9998; background:#1e293b; border:1px solid #475569; border-radius:8px; padding:8px 12px; font-size:11px; color:#f1f5f9; max-width:300px; box-shadow:0 8px 24px rgba(0,0,0,0.4); pointer-events:none; }
    .fe-preview-tip .tip-label { color:#94a3b8; font-size:9px; margin-bottom:2px; }
    .fe-preview-tip .tip-value { color:#fbbf24; font-family:monospace; font-size:12px; word-break:break-all; }
    
    /* ── Schema Outdated Banner ───────────────────────── */
    .schema-outdated-banner { background:rgba(251,191,36,0.15); border:1px solid #fbbf24; border-radius:6px; padding:8px 12px; margin:8px; font-size:11px; color:#fbbf24; display:flex; align-items:center; gap:8px; }
    .schema-outdated-banner .btn-update { background:#fbbf24; color:#0f172a; border:none; padding:3px 10px; border-radius:4px; font-size:10px; font-weight:600; cursor:pointer; }
    .schema-outdated-banner .btn-update:hover { background:#f59e0b; }
    .schema-diff-list { max-height:150px; overflow-y:auto; margin-top:6px; font-size:10px; }
    .schema-diff-added { color:#22c55e; }
    .schema-diff-removed { color:#ef4444; }
    
    /* ── Schema Info Bar ──────────────────────────────── */
    .schema-info-bar { display:flex; align-items:center; gap:8px; padding:6px 10px; background:rgba(59,130,246,0.08); border-radius:6px; margin:8px 0; font-size:10px; color:var(--text-muted); }
    .schema-info-bar .schema-name { font-weight:600; color:var(--text); }
    .schema-info-bar .schema-version { background:rgba(59,130,246,0.15); padding:1px 6px; border-radius:4px; color:var(--primary); }
    .schema-info-bar .schema-count { margin-left:auto; }

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

    /* ── Connector Section ── */
    .connector-section { padding: 8px; border-bottom: 1px solid var(--border); }
    .connector-section select { width: 100%; padding: 4px 6px; font-size: 12px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); }
    .connector-section select:focus { border-color: var(--accent); outline: none; }
    .connector-status { font-size: 10px; margin-top: 4px; }
    .connector-status.connected { color: #22c55e; }
    .connector-status.disconnected { color: #ef4444; }
    .connector-status.checking { color: #f59e0b; }
    #fetch-live-btn { cursor: pointer; }
    #fetch-live-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    #fetch-live-btn.loading { position: relative; }
    #fetch-live-btn.loading::after { content: ''; position: absolute; inset: 0; background: rgba(255,255,255,0.3); border-radius: 4px; }

    /* ── Test Scenarios ── */
    .scenarios-section { padding: 8px; border-bottom: 1px solid var(--border); }
    .scenario-item { transition: background 0.15s; }
    .scenario-item:hover { background: var(--bg-hover, rgba(0,0,0,0.05)); }
    #new-scenario-name:focus { border-color: var(--accent); outline: none; }

    /* ── Conditional Formatting Editor ── */
    .cf-editor { margin-top: 8px; }
    .cf-rule { background: var(--bg2); border: 1px solid var(--border); border-radius: 6px; padding: 8px; margin-bottom: 8px; }
    .cf-rule-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
    .cf-rule-title { font-size: 11px; font-weight: 600; color: var(--text); }
    .cf-rule-remove { padding: 2px 6px; border: none; background: transparent; cursor: pointer; color: #ef4444; font-size: 14px; }
    .cf-condition-row { display: flex; gap: 4px; align-items: center; margin-bottom: 6px; flex-wrap: wrap; }
    .cf-condition-row select, .cf-condition-row input { padding: 3px 6px; font-size: 11px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); }
    .cf-field-picker { min-width: 100px; }
    .cf-operator { min-width: 80px; }
    .cf-value-input { min-width: 80px; flex: 1; }
    .cf-style-preview { display: flex; gap: 8px; align-items: center; margin-top: 6px; flex-wrap: wrap; }
    .cf-style-preview label { font-size: 10px; color: var(--text-muted); }
    .cf-style-preview input[type="color"] { width: 28px; height: 28px; padding: 0; border: 1px solid var(--border); border-radius: 4px; cursor: pointer; }
    .cf-style-preview input[type="checkbox"] { margin: 0; }
    .cf-preview-box { padding: 6px 12px; border-radius: 4px; font-size: 12px; margin-top: 6px; text-align: center; min-height: 28px; display: flex; align-items: center; justify-content: center; }
    .cf-add-rule { padding: 4px 10px; font-size: 11px; background: var(--bg2); border: 1px dashed var(--border); border-radius: 4px; cursor: pointer; color: var(--text-muted); width: 100%; }
    .cf-add-rule:hover { background: var(--accent); color: #fff; border-color: var(--accent); }

    /* ── Visual Formula Editor (fe-*) ───────────────────── */
    .fe-container { margin-top: 8px; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
    .fe-tabs { display: flex; background: var(--bg); border-bottom: 1px solid var(--border); }
    .fe-tab { flex: 1; padding: 7px 6px; text-align: center; font-size: 10px; font-weight: 600; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.15s; user-select: none; }
    .fe-tab:hover { color: var(--text); background: rgba(255,255,255,0.03); }
    .fe-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: rgba(59,130,246,0.08); }
    .fe-tab .fe-tab-icon { font-size: 11px; margin-right: 3px; }
    .fe-panel { display: none; padding: 10px; }
    .fe-panel.active { display: block; }
    .fe-editor-toolbar { display: flex; gap: 4px; margin-bottom: 6px; flex-wrap: wrap; align-items: center; }
    .fe-editor-toolbar button { padding: 3px 8px; font-size: 10px; background: var(--surface-hover); border: 1px solid var(--border); border-radius: 4px; color: var(--text); cursor: pointer; transition: all 0.15s; }
    .fe-editor-toolbar button:hover { background: var(--border); border-color: var(--primary); color: var(--primary); }
    .fe-editor-toolbar .fe-btn-primary { background: rgba(59,130,246,0.15); border-color: var(--primary); color: var(--primary); }
    .fe-editor-toolbar .fe-btn-primary:hover { background: var(--primary); color: #fff; }
    .fe-editor-area { position: relative; }
    .fe-editor-textarea { width: 100%; min-height: 56px; background: var(--bg); border: 1px solid var(--border); border-radius: 4px; color: var(--text); font-family: monospace; font-size: 12px; padding: 8px; resize: vertical; outline: none; line-height: 1.5; box-sizing: border-box; }
    .fe-editor-textarea:focus { border-color: var(--primary); }
    .fe-validation { font-size: 10px; margin-top: 4px; min-height: 16px; display: flex; align-items: center; gap: 6px; }
    .fe-validation.valid { color: #22c55e; }
    .fe-validation.error { color: #ef4444; }
    .fe-validation.pending { color: var(--text-muted); }
    .fe-field-dropdown { width: 100%; padding: 4px 6px; font-size: 11px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); outline: none; margin-bottom: 6px; box-sizing: border-box; }
    .fe-field-dropdown:focus { border-color: var(--primary); }
    .fe-functions-list { max-height: 180px; overflow-y: auto; }
    .fe-functions-list .fe-fn-group { margin-bottom: 6px; }
    .fe-functions-list .fe-fn-group-title { font-size: 10px; font-weight: 600; color: var(--text-muted); padding: 2px 4px; margin-bottom: 2px; display: flex; align-items: center; gap: 3px; }
    .fe-functions-list .fe-fn-item { display: flex; align-items: baseline; gap: 6px; padding: 3px 6px; border-radius: 4px; cursor: pointer; transition: background 0.1s; }
    .fe-functions-list .fe-fn-item:hover { background: var(--surface-hover); }
    .fe-functions-list .fe-fn-name { font-size: 11px; font-family: monospace; color: var(--primary); font-weight: 500; }
    .fe-functions-list .fe-fn-params { font-size: 9px; color: var(--text-muted); }
    .fe-functions-list .fe-fn-desc { font-size: 9px; color: var(--text-muted); opacity: 0.8; }

    /* ── Running Total Builder (rt-*) ───────────────────── */
    .rt-container { margin-top: 8px; }
    .rt-row { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
    .rt-row label { font-size: 10px; color: var(--text-muted); min-width: 60px; flex-shrink: 0; }
    .rt-row select, .rt-row input { flex: 1; padding: 4px 6px; font-size: 11px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); outline: none; }
    .rt-row select:focus, .rt-row input:focus { border-color: var(--primary); }
    .rt-field-group { border: 1px solid var(--border); border-radius: 6px; padding: 8px; margin-bottom: 8px; background: rgba(0,0,0,0.05); }
    .rt-field-group-title { font-size: 10px; font-weight: 600; color: var(--text-muted); margin-bottom: 4px; display: flex; align-items: center; gap: 4px; }
    .rt-toggle-row { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
    .rt-toggle-row input[type="checkbox"] { margin: 0; }
    .rt-toggle-row label { font-size: 10px; color: var(--text); cursor: pointer; }
    .rt-toggle-row .rt-toggle-hint { font-size: 9px; color: var(--text-muted); margin-left: auto; }
    .rt-preview { background: var(--bg); border: 1px solid var(--border); border-radius: 4px; padding: 6px 8px; font-family: monospace; font-size: 11px; color: var(--text-muted); word-break: break-all; margin-top: 6px; min-height: 20px; }
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
                <div class="tab-item" onclick="switchTab('explorer')">Explorer</div>
                <div class="tab-item" onclick="switchTab('data')">Data</div>
                <div class="tab-item" onclick="switchTab('sgf')">SGF</div>
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

            <!-- ── Field Explorer Tab ──────────────────────────── -->
            <div id="tab-explorer" class="tab-panel">
                <div class="props-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Field Explorer</span>
                    <div style="display:flex; gap:4px;">
                        <button onclick="refreshFieldExplorer()" class="action-btn" style="padding:2px 6px; font-size:10px;" title="Refresh">↻</button>
                    </div>
                </div>
                <div id="schema-outdated-banner-explorer" class="schema-outdated-banner" style="display:none; margin:8px;">
                    ⚠️ <span id="schema-outdated-msg-explorer"></span>
                </div>
                <div style="padding:8px; border-bottom:1px solid var(--border);">
                    <select id="explorer-schema-select" class="fe-search" onchange="loadFieldExplorer()">
                        <option value="">-- Select Schema --</option>
                        @foreach($schemas ?? [] as $s)
                            <option value="{{ $s->id }}" {{ ($template->data_schema_id ?? '') == $s->id ? 'selected' : '' }}>
                                {{ $s->label ?: $s->schema_name }} (v{{ $s->version }}){{ $s->clientApp ? ' · '.$s->clientApp->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div id="schema-info-bar" class="schema-info-bar" style="display:none;"></div>
                    <div id="multi-schema-indicator" style="display:none; margin-top:4px; font-size:10px; color:var(--text-muted);"></div>
                    <input type="text" id="fe-search-input" class="fe-search" placeholder="🔍 Search fields..." oninput="filterFieldExplorer(this.value)" style="margin-top:6px;">
                </div>
                <div id="fe-container" style="flex:1; overflow-y:auto; padding:8px; font-size:11px;">
                    <div style="text-align:center; color:var(--text-muted); padding:2rem 1rem; font-size:0.8rem;">
                        Select a schema to browse fields
                    </div>
                </div>
            </div>

            <div id="tab-data" class="tab-panel">
                <div class="props-header">Global Styles</div>
                <div id="styles-list" style="padding:1rem; border-bottom:1px solid var(--border);">
                    <button onclick="addStyle()" class="btn btn-secondary btn-sm" style="width:100%">+ New Style</button>
                    <div id="styles-container" style="margin-top:0.5rem;"></div>
                </div>

                <div class="props-header">Data Schema</div>
                <div id="schema-outdated-banner" style="display:none;"></div>
                <div style="padding:1rem; border-bottom:1px solid var(--border);">
                    <label style="font-size:0.8rem; color:var(--text-muted); display:block; margin-bottom:5px;">Primary Schema</label>
                    <select id="data-schema-select" class="form-control" onchange="loadSelectedSchema()">
                        <option value="">-- No Schema --</option>
                        @foreach($schemas ?? [] as $s)
                            <option value="{{ $s->id }}" {{ ($template->data_schema_id ?? '') == $s->id ? 'selected' : '' }}>
                                {{ $s->label ?: $s->schema_name }} (v{{ $s->version }}){{ $s->clientApp ? ' · '.$s->clientApp->name : '' }}
                            </option>
                        @endforeach
                    </select>
                    <div id="schema-fields-container" style="margin-top:10px; font-size:11px; max-height:200px; overflow-y:auto; padding-right:5px;"></div>
                    <button id="load-history-btn" onclick="openJobHistoryModal()" class="btn btn-secondary btn-sm" style="width:100%; margin-top:10px; display:none;">📦 Load from Job History</button>
                </div>

                <!-- ── Additional Schemas (Multi-Client App) ── -->
                <div class="props-header">Additional Schemas</div>
                <div id="additional-schemas-section" style="padding:0.5rem 1rem 0.75rem; border-bottom:1px solid var(--border);">
                    <div id="additional-schemas-list" style="margin-bottom:6px;"></div>
                    <div style="display:flex; gap:4px;">
                        <select id="add-schema-select" class="form-control" style="flex:1; font-size:11px; padding:4px 6px;">
                            <option value="">-- Add Schema --</option>
                            @foreach($schemas ?? [] as $s)
                                <option value="{{ $s->id }}" {{ ($template->data_schema_id ?? '') == $s->id ? 'disabled' : '' }}>
                                    {{ $s->label ?: $s->schema_name }} (v{{ $s->version }}){{ $s->clientApp ? ' · '.$s->clientApp->name : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button onclick="addSchemaToTemplate()" class="action-btn" style="padding:4px 10px; font-size:11px; white-space:nowrap;">+ Add</button>
                    </div>
                </div>

                <div class="props-header">🔌 Connector</div>
                <div id="connector-section" class="connector-section" style="padding:8px; border-bottom:1px solid var(--border);">
                    <label style="font-size:11px; font-weight:600; color:var(--text-muted); display:block; margin-bottom:4px;">
                        Data Source
                    </label>
                    <select id="connector-select" onchange="onConnectorSelect()" style="width:100%; padding:4px 6px; font-size:12px; border:1px solid var(--border); border-radius:4px; background:var(--bg); color:var(--text);">
                        <option value="">— No connector —</option>
                    </select>
                    <div id="connector-status" class="connector-status" style="font-size:10px; margin-top:4px; color:var(--text-muted);"></div>
                    <button id="fetch-live-btn" style="display:none; margin-top:6px; padding:4px 10px; font-size:11px; background:var(--primary); color:#fff; border:none; border-radius:4px;">
                        🔄 Fetch Live Data
                    </button>
                </div>

                <!-- ── Runtime Parameters ── -->
                <div class="props-header">⚙️ Runtime Parameters</div>
                <div id="parameters-section" style="padding:0.5rem 1rem 0.75rem; border-bottom:1px solid var(--border);">
                    <div style="font-size:10px; color:var(--text-muted); margin-bottom:6px;">
                        Define parameters that prompt at preview/print time.
                        Values can be used in expressions as <code>{param_name}</code>.
                    </div>
                    <div id="parameters-list" style="margin-bottom:8px;"></div>
                    <div style="display:flex; gap:4px; align-items:center; flex-wrap:wrap;">
                        <input type="text" id="param-name" placeholder="Name" style="flex:1; min-width:80px; padding:4px 6px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text);">
                        <input type="text" id="param-label" placeholder="Label" style="flex:1; min-width:80px; padding:4px 6px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text);">
                        <select id="param-type" style="padding:4px 6px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text);">
                            <option value="text">Text</option>
                            <option value="number">Number</option>
                            <option value="date">Date</option>
                            <option value="boolean">Boolean</option>
                            <option value="select">Select</option>
                        </select>
                        <button onclick="addParameter()" class="action-btn" style="padding:4px 10px; font-size:11px; white-space:nowrap;">+ Add</button>
                    </div>
                    <div id="param-select-options-row" style="display:none; margin-top:6px; gap:4px; align-items:center; flex-wrap:wrap;">
                        <input type="text" id="param-options" placeholder="option1,option2,..." style="flex:1; min-width:120px; padding:4px 6px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text);">
                        <span style="font-size:9px; color:var(--text-muted);">(comma-separated)</span>
                    </div>
                    <div style="margin-top:6px;">
                        <input type="text" id="param-default" placeholder="Default value (optional)" style="width:100%; padding:4px 6px; font-size:11px; background:var(--bg); border:1px solid var(--border); border-radius:4px; color:var(--text); box-sizing:border-box;">
                    </div>
                    <div style="margin-top:4px; display:flex; align-items:center; gap:6px;">
                        <input type="checkbox" id="param-required" style="accent-color:var(--primary);">
                        <label for="param-required" style="font-size:11px; color:var(--text-muted);">Required</label>
                    </div>
                </div>

                <!-- ── Test Scenarios ── -->
                <div class="scenarios-section" style="padding: 8px; border-bottom: 1px solid var(--border);">
                    <label style="font-size: 11px; font-weight: 600; color: var(--text-muted); display: block; margin-bottom: 4px;">
                        🧪 Test Scenarios
                    </label>
                    <div id="scenarios-list">
                        <!-- Scenarios rendered here -->
                    </div>
                    <div style="margin-top:6px;display:flex;gap:4px;">
                        <input type="text" id="new-scenario-name" placeholder="Scenario name" style="flex:1;padding:3px 6px;font-size:11px;border:1px solid var(--border);border-radius:4px;background:var(--bg);color:var(--text);">
                        <button onclick="createScenario()" style="padding:3px 10px;font-size:11px;background:var(--primary);color:#fff;border:none;border-radius:4px;cursor:pointer;">+ Add</button>
                    </div>
                </div>

                <div class="props-header">Sample JSON Explorer</div>
                <div style="padding:1rem;">
                    <textarea id="json-input" placeholder="Paste Sample JSON here..." style="width:100%; height:80px; background:var(--bg); border:1px solid var(--border); color:var(--text); font-family:monospace; font-size:10px; padding:8px; border-radius:4px;"></textarea>
                    <button onclick="parseJSON()" class="btn btn-secondary btn-sm" style="width:100%; margin-top:0.5rem;">Parse JSON</button>
                    <div id="json-tree" style="margin-top:1rem; font-size:0.75rem; font-family:monospace;"></div>
                </div>

                <!-- ── SGF Panel (Sorting, Grouping, Filtering) ── -->
                <div id="tab-sgf" class="tab-panel">
                    <div class="props-header">🔀 Sorting</div>
                    <div id="sgf-sort-section" style="padding:8px; border-bottom:1px solid var(--border);">
                        <div style="font-size:10px; color:var(--text-muted); margin-bottom:6px;">
                            Define sort fields to order detail rows. Sort by multiple fields for nested ordering.
                        </div>
                        <div id="sgf-sort-list" style="margin-bottom:8px;"></div>
                        <button onclick="sgfAddSort()" style="padding:3px 10px; font-size:11px; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">+ Add Sort Field</button>
                    </div>

                    <div class="props-header">📁 Grouping</div>
                    <div id="sgf-group-section" style="padding:8px; border-bottom:1px solid var(--border);">
                        <div style="font-size:10px; color:var(--text-muted); margin-bottom:6px;">
                            Group rows by field value changes. Groups auto-sort and render with a header showing the group name + value.
                        </div>
                        <div id="sgf-group-list" style="margin-bottom:8px;"></div>
                        <button onclick="sgfAddGroup()" style="padding:3px 10px; font-size:11px; background:var(--primary); color:#fff; border:none; border-radius:4px; cursor:pointer;">+ Add Group Field</button>
                    </div>

                    <div class="props-header">🔍 Filtering</div>
                    <div id="sgf-filter-section" style="padding:8px; border-bottom:1px solid var(--border);">
                        <div style="font-size:10px; color:var(--text-muted); margin-bottom:6px;">
                            Filter rows using an expression. Use <code style="font-size:10px; background:var(--bg); padding:1px 4px; border-radius:2px;">{field_name}</code> for field references and standard operators.
                        </div>
                        <input type="text" id="sgf-filter-expression" oninput="sgfUpdateFilter(this.value)"
                               placeholder='e.g., {amount} > 0'
                               style="width:100%; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:8px; border-radius:4px; font-size:12px; font-family:monospace;">
                        <div style="margin-top:6px; font-size:10px; color:var(--text-muted);">
                            Tips: Use <code style="font-size:10px;">></code>, <code style="font-size:10px;"><</code>, <code style="font-size:10px;">==</code>, <code style="font-size:10px;">!=</code> for comparisons.
                            Combine with <code style="font-size:10px;">and</code> / <code style="font-size:10px;">or</code> for complex filters.
                        </div>
                    </div>
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

<?php
$templateSchemasData = $template->relationLoaded('schemas') ? $template->schemas->map(fn($s) => [
    'id'              => $s->id,
    'schema_name'     => $s->schema_name,
    'label'           => $s->label,
    'version'         => $s->version,
    'fields'          => $s->fields,
    'tables'          => $s->tables,
    'sample_data'     => $s->sample_data,
    'client_app_id'   => $s->client_app_id,
    'client_app_name' => $s->clientApp?->name,
    'pivot'           => [
        'alias' => $s->pivot->alias,
    ],
]) : [];
?>
<script>
    const availableSchemas = @json($schemas ?? []);
    const templateSchemas = @json($templateSchemasData);
    const templateId = "{{ $template->id ?? '' }}";
    const BASE_SCALE = 4;

    // ── Runtime Parameters ──────────────────────────────────
    let templateParams = [];
    try {
        const raw = '{{ json_encode($template->parameters ?? []) }}';
        // Decode HTML entities that Blade/Laravel may have encoded
        const decoded = raw.replace(/"/g, '"').replace(/&#039;/g, "'").replace(/&/g, '&');
        templateParams = JSON.parse(decoded);
        if (!Array.isArray(templateParams)) templateParams = [];
    } catch (e) {
        templateParams = [];
    }

    // ── Sorting, Grouping & Filtering (SGF) ──────────────────
    let dataOptions = { sortFields: [], groupFields: [], filterExpression: '' };
    try {
        const raw = '{{ json_encode($template->data_options ?? []) }}';
        const decoded = raw.replace(/"/g, '"').replace(/&#039;/g, "'").replace(/&/g, '&');
        const parsed = JSON.parse(decoded);
        if (parsed && typeof parsed === 'object') {
            dataOptions = {
                sortFields: Array.isArray(parsed.sortFields) ? parsed.sortFields : [],
                groupFields: Array.isArray(parsed.groupFields) ? parsed.groupFields : [],
                filterExpression: parsed.filterExpression || '',
            };
        }
    } catch (e) {
        dataOptions = { sortFields: [], groupFields: [], filterExpression: '' };
    }
    document.addEventListener('DOMContentLoaded', () => {
        renderParameters();
        // Render SGF panels after DOM is ready
        setTimeout(() => { sgfRenderSortList(); sgfRenderGroupList(); }, 0);
    });
    let zoomLevel = 1.0;
    let elements = @json($template->elements ?? []);
    let globalStyles = @json($template->styles ?? []);

    function getSchemaFieldKeys() {
        const activeSchemaId = document.getElementById('data-schema-select')?.value;
        const activeSchema = availableSchemas.find(s => s.id == activeSchemaId);
        if (activeSchema && activeSchema.fields) {
            return Object.keys(activeSchema.fields);
        }
        // Include fields from additional schemas (prefixed)
        if (templateSchemas && templateSchemas.length > 1) {
            const allKeys = [];
            templateSchemas.forEach(s => {
                if (!s.fields) return;
                const prefix = (s.pivot?.alias || s.client_app_name || '').toLowerCase();
                Object.keys(s.fields).forEach(k => {
                    if (s.id == activeSchemaId) {
                        allKeys.push(k);
                    } else if (prefix) {
                        allKeys.push(prefix + '.' + k);
                    } else {
                        allKeys.push(k);
                    }
                });
            });
            if (allKeys.length > 0) return allKeys;
        }
        // Fallback: try to extract fields from sample data
        try {
            const sampleData = JSON.parse(document.getElementById('json-input').value || '{}');
            return Object.keys(sampleData).filter(k => typeof sampleData[k] !== 'object' || sampleData[k] === null);
        } catch(e) {}
        return [];
    }
    // Variables moved to top

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
        console.log('[Designer] initSections()', {
            rawType: typeof rawElements,
            isArray: Array.isArray(rawElements),
            hasSections: !!(rawElements && rawElements.sections),
            isNull: !rawElements,
            rawKeys: rawElements && typeof rawElements === 'object' && !Array.isArray(rawElements) ? Object.keys(rawElements) : null
        });
        if (!rawElements || typeof rawElements !== 'object') {
            sections = JSON.parse(JSON.stringify(SECTION_DEFAULTS));
            sections.detail.elements = [];
            console.log('[Designer] initSections: null/invalid path, fresh defaults');
            return sections;
        }
        if (rawElements.sections) {
            sections = JSON.parse(JSON.stringify(rawElements.sections));
            console.log('[Designer] initSections: sections format detected', {
                sectionKeys: Object.keys(sections),
                detailHasElements: 'elements' in (sections.detail || {}),
                detailElsType: sections.detail ? typeof sections.detail.elements : 'no-detail'
            });
            SECTION_ORDER.forEach(key => {
                if (!sections[key]) {
                    sections[key] = JSON.parse(JSON.stringify(SECTION_DEFAULTS[key]));
                    console.log('[Designer] initSections: added missing section', key);
                }
                // Ensure every section has an elements array
                if (sections[key] && !Array.isArray(sections[key].elements)) {
                    sections[key].elements = [];
                    console.log('[Designer] initSections: added missing elements array for section', key);
                }
            });
            return sections;
        }
        // Legacy flat format — put all in detail
        sections = JSON.parse(JSON.stringify(SECTION_DEFAULTS));
        console.log('[Designer] initSections: legacy flat format', { isArray: Array.isArray(rawElements), count: rawElements?.length });
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
    let selectedConnectorId = null;
    let connectors = [];

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

        // Sync with explorer tab
        const explorerSelect = document.getElementById('explorer-schema-select');
        if (explorerSelect && explorerSelect.value !== schemaId) {
            explorerSelect.value = schemaId;
        }
        
        if (!schemaId) {
            historyBtn.style.display = 'none';
            sampleDataCache = {};
            loadAdditionalSchemas();
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

        // Show schema info
        const fieldCount = Object.keys(schema.fields || {}).length;
        const tableCount = Object.keys(schema.tables || {}).length;
        const attachedCount = (templateSchemas || []).length;

        let html = `<div class="schema-info-bar" style="display:flex;">
            <span class="schema-name">${schema.label || schema.schema_name}</span>
            <span class="schema-version">v${schema.version}</span>
            <span class="schema-count">${fieldCount} fields · ${tableCount} tables${attachedCount > 1 ? ` · 📦 +${attachedCount - 1} more` : ''}</span>
        </div>`;

        // Search bar for quick filter
        html += `<input type="text" id="badge-filter" class="fe-search" placeholder="🔍 Filter fields..." oninput="filterSchemaBadges(this.value)" style="margin-bottom:6px;">`;

        // Fields with type badges
        const fields = schema.fields || {};
        const filteredFields = Object.entries(fields);
        html += `<div id="schema-fields-list"><div style="font-weight:bold; margin-bottom:4px; color:var(--text); margin-top:6px;">Fields</div>`;
        html += '<div id="field-badges" style="display:flex; flex-wrap:wrap; gap:4px; margin-bottom:8px;">';
        for (const [key, meta] of filteredFields) {
            const type = meta.type || 'string';
            const format = meta.format || '';
            const typeClass = format === 'currency' ? 'currency' : type;
            const isUsed = usedKeys.includes(key);
            const opacity = isUsed ? 'opacity:0.5;' : '';
            const icon = isUsed ? '✓' : '➕';
            
            // Sample preview value
            let previewAttr = '';
            if (schema.sample_data) {
                const resolved = resolveDataValue(key, schema.sample_data);
                if (resolved !== null && resolved !== undefined) previewAttr = escapeHtml(String(resolved).substring(0, 40));
            }
            
            html += `<span class="badge-delphi" draggable="true" style="cursor:grab;${opacity}"
                ondragstart="feDragStart(event, '${key}', 'field')"
                ondragend="feDragEnd(event)"
                onclick="addFieldFromSchema('${key}', 'field')"
                onmouseover="feShowPreview(event, '${key}', '${escapeJs(previewAttr)}')"
                onmouseout="feHidePreview()"
                title="${meta.label || key} (${type}${format ? ':'+format : ''})${meta.required ? ' *required' : ''}">
                ${icon} ${key}<span class="field-type-tag ${typeClass}">${type}</span>
            </span>`;
        }
        html += '</div></div>';

        // Tables
        const tables = schema.tables || {};
        if (Object.keys(tables).length > 0) {
            html += '<div style="font-weight:bold; margin-bottom:4px; color:var(--text);">Tables</div>';
            html += '<div id="table-badges" style="display:flex; flex-wrap:wrap; gap:4px;">';
            for (const [key, meta] of Object.entries(tables)) {
                const cols = meta.columns || {};
                const colsSafe = encodeURIComponent(JSON.stringify(cols));
                const isUsed = usedTables.includes(key);
                const opacity = isUsed ? 'opacity:0.5;' : '';
                const icon = isUsed ? '✓' : '▦';
                html += `<span class="badge-delphi" draggable="true" style="cursor:grab; background:rgba(59,130,246,0.15); color:#2563eb;${opacity}"
                    ondragstart="feDragStart(event, '${key}', 'table', '${colsSafe}')"
                    ondragend="feDragEnd(event)"
                    onclick="addFieldFromSchema('${key}', 'table', '${colsSafe}')"
                    title="${meta.label || key}">
                    ${icon} ${key}
                </span>`;
            }
            html += '</div>';
        }
        
        container.innerHTML = html;
        if (liveDataMode) renderElements();

        // ── Auto-select connector based on schema's client app ──
        if (schema && schema.client_app_id) {
            const matchingConnector = connectors.find(c => c.client_app_id == schema.client_app_id);
            if (matchingConnector) {
                const select = document.getElementById('connector-select');
                if (select) {
                    select.value = matchingConnector.id;
                    onConnectorSelect();
                }
            }
        }

        // ── Refresh additional schemas list ──
        loadAdditionalSchemas();
        updateMultiSchemaSelect();
    }

    // ── Connector Registry ─────────────────────────────────
    function loadConnectors() {
        const select = document.getElementById('connector-select');
        const statusEl = document.getElementById('connector-status');
        if (!select) return;

        // Show loading state
        select.disabled = true;
        select.innerHTML = '<option value="">Loading connectors…</option>';

        fetch('/api/v1/connectors')
            .then(r => r.json())
            .then(data => {
                select.disabled = false;
                select.innerHTML = '<option value="">— No connector —</option>';

                // Handle both array and { data: [] } response formats
                connectors = Array.isArray(data) ? data : (data.data || []);

                connectors.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    const icon = c.icon || '🔌';
                    opt.textContent = icon + ' ' + (c.name || c.connector_name || 'Connector #' + c.id);
                    select.appendChild(opt);
                });

                // If we already have a selectedConnectorId, re-select it
                if (selectedConnectorId) {
                    select.value = selectedConnectorId;
                    onConnectorSelect();
                }
            })
            .catch(err => {
                console.error('Failed to load connectors:', err);
                select.disabled = false;
                select.innerHTML = '<option value="">— No connector —</option>';
                if (statusEl) {
                    statusEl.textContent = '❌ Failed to load connectors';
                    statusEl.className = 'connector-status disconnected';
                }
            });
    }

    function onConnectorSelect() {
        const select = document.getElementById('connector-select');
        const statusEl = document.getElementById('connector-status');
        const fetchBtn = document.getElementById('fetch-live-btn');
        const connectorId = select.value;

        if (!connectorId) {
            selectedConnectorId = null;
            if (statusEl) {
                statusEl.textContent = '';
                statusEl.className = 'connector-status';
            }
            if (fetchBtn) fetchBtn.style.display = 'none';
            return;
        }

        selectedConnectorId = connectorId;
        if (statusEl) {
            statusEl.textContent = '⏳ Testing connection…';
            statusEl.className = 'connector-status checking';
        }
        if (fetchBtn) fetchBtn.style.display = 'none';

        // Test the connection
        fetch('/api/v1/connectors/' + connectorId + '/test', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'ok' || data.connected) {
                if (statusEl) {
                    statusEl.textContent = '✅ Connected';
                    statusEl.className = 'connector-status connected';
                }
                if (fetchBtn) {
                    fetchBtn.style.display = 'inline-block';
                    fetchBtn.disabled = false;
                }
            } else {
                if (statusEl) {
                    statusEl.textContent = '❌ Connection failed';
                    statusEl.className = 'connector-status disconnected';
                }
                if (fetchBtn) fetchBtn.style.display = 'none';
            }
        })
        .catch(() => {
            if (statusEl) {
                statusEl.textContent = '❌ Connection failed';
                statusEl.className = 'connector-status disconnected';
            }
            if (fetchBtn) fetchBtn.style.display = 'none';
        });
    }

    // ── Fetch Live Data ────────────────────────────────────
    function wireFetchLiveBtn() {
        const btn = document.getElementById('fetch-live-btn');
        if (!btn) return;
        btn.addEventListener('click', async function() {
            if (!selectedConnectorId) {
                showToast('⚠️ Select a connector first.', 'warning');
                return;
            }
            btn.disabled = true;
            btn.textContent = '⏳ Fetching...';
            try {
                const resp = await fetch('/api/v1/connectors/' + selectedConnectorId + '/fetch-preview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                });
                const json = await resp.json();
                if (!resp.ok) {
                    showToast('❌ ' + (json.message || 'Fetch failed'), 'error');
                    return;
                }
                if (!json.data || Object.keys(json.data).length === 0) {
                    showToast('⚠️ Connector returned no data.', 'warning');
                    return;
                }
                // Populate the schema store with fetched data so the field explorer
                // and canvas can use them.
                const schemaName = 'live_' + Date.now();
                schemaStore[schemaName] = { fields: json.data };
                selectedSchemaName = schemaName;
                refreshFieldExplorer();
                showToast('✅ Live data loaded (' + Object.keys(json.data).length + ' fields).', 'success');
            } catch (err) {
                showToast('❌ Network error: ' + err.message, 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '🔄 Fetch Live Data';
            }
        });
    }

    /**
     * Show a temporary toast notification.
     * @param {string} msg  - Message text
     * @param {string} type - 'success' | 'error' | 'warning' | 'info'
     */
    function showToast(msg, type) {
        const existing = document.querySelector('.print-hub-toast');
        if (existing) existing.remove();
        const colors = { success: '#22c55e', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
        const toast = document.createElement('div');
        toast.className = 'print-hub-toast';
        toast.textContent = msg;
        Object.assign(toast.style, {
            position: 'fixed', bottom: '24px', right: '24px', zIndex: '9999',
            padding: '10px 20px', borderRadius: '8px', color: '#fff',
            fontSize: '13px', fontWeight: '500', boxShadow: '0 4px 12px rgba(0,0,0,0.25)',
            background: colors[type] || colors.info, transition: 'opacity 0.3s ease',
            maxWidth: '360px', wordBreak: 'break-word',
        });
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3500);
    }

    // ── Badge Filtering ────────────────────────────────────
    function filterSchemaBadges(val) {
        const filterVal = val.toLowerCase();
        const badges = document.querySelectorAll('#field-badges .badge-delphi, #table-badges .badge-delphi');
        badges.forEach(b => {
            const text = b.textContent.toLowerCase();
            b.style.display = (!filterVal || text.includes(filterVal)) ? 'inline-block' : 'none';
        });
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
        console.log('[Designer] init() started', { elementsBefore: elements ? (Array.isArray(elements) ? 'array['+elements.length+']' : typeof elements) : null });
        initSections(elements);
        console.log('[Designer] after initSections', { sectionsKeys: sections ? Object.keys(sections) : null, detailEls: sections?.detail?.elements, detailElsType: typeof sections?.detail?.elements });
        // Ensure elements reference is the flat list from detail section for backward compat
        elements = sections.detail.elements;
        console.log('[Designer] elements reassigned', { elementsType: typeof elements, isArray: Array.isArray(elements), length: elements?.length });
        if (!Array.isArray(elements)) {
            console.error('[Designer] FATAL: elements is not an array after initSections!', elements);
            elements = [];
        }
        elements.forEach((el, idx) => {
            if (!el.id) el.id = 'el_' + Date.now() + '_' + idx;
            if (!el.fontFamily) el.fontFamily = 'Arial';
            if ((el.type === 'field' || el.type === 'label' || el.type === 'image') && el.rotation === undefined) {
                el.rotation = 0;
            }
        });
        updateCanvasSize(); renderElements(); renderStyles();
        console.log('[Designer] init() complete - canvas rendered');

        loadSelectedSchema();
        loadFieldExplorer();
        fetchAndPopulateFonts();
        document.getElementById('canvas').addEventListener('mousedown', canvasMouseDown);
        document.getElementById('canvas').addEventListener('contextmenu', canvasContextMenu);
        initCanvasDropHandlers();
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
        if (tab === 'explorer') { loadFieldExplorer(); }
        if (tab === 'data') { loadConnectors(); loadScenarios(); }
        if (tab === 'sgf') { sgfRenderSortList(); sgfRenderGroupList(); }
    }

    // ── Sorting, Grouping & Filtering (SGF) ──────────────────
    function sgfRenderSortList() {
        const container = document.getElementById('sgf-sort-list');
        if (!container) return;
        const fields = dataOptions.sortFields || [];
        if (fields.length === 0) {
            container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px 0;">No sort fields defined.</div>';
            return;
        }
        container.innerHTML = fields.map((sf, idx) => `
            <div style="display:flex; align-items:center; gap:4px; margin-bottom:4px; background:var(--bg); border-radius:4px; padding:4px 6px;">
                <span style="font-size:10px; color:var(--text-muted); min-width:16px;">${idx + 1}.</span>
                <input type="text" value="${escapeHtml(sf.field || '')}" onchange="sgfUpdateSortField(${idx}, this.value)"
                       placeholder="field_name" style="flex:1; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:3px 6px; border-radius:3px; font-size:11px; font-family:monospace;">
                <button onclick="sgfToggleSortDirection(${idx})" style="padding:2px 6px; font-size:10px; border:1px solid var(--border); border-radius:3px; background:var(--surface); color:var(--text); cursor:pointer; min-width:40px;">
                    ${(sf.direction || 'asc') === 'asc' ? '▲ Asc' : '▼ Desc'}
                </button>
                <button onclick="sgfRemoveSort(${idx})" style="padding:2px 6px; font-size:12px; border:none; background:none; color:var(--danger); cursor:pointer;">×</button>
            </div>
        `).join('');
    }

    function sgfAddSort() {
        if (!dataOptions.sortFields) dataOptions.sortFields = [];
        dataOptions.sortFields.push({ field: '', direction: 'asc' });
        sgfRenderSortList();
    }

    function sgfRemoveSort(idx) {
        dataOptions.sortFields.splice(idx, 1);
        sgfRenderSortList();
    }

    function sgfToggleSortDirection(idx) {
        const sf = dataOptions.sortFields[idx];
        if (sf) {
            sf.direction = sf.direction === 'asc' ? 'desc' : 'asc';
            sgfRenderSortList();
        }
    }

    function sgfUpdateSortField(idx, value) {
        if (dataOptions.sortFields[idx]) {
            dataOptions.sortFields[idx].field = value;
        }
    }

    function sgfRenderGroupList() {
        const container = document.getElementById('sgf-group-list');
        if (!container) return;
        const fields = dataOptions.groupFields || [];
        if (fields.length === 0) {
            container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px 0;">No group fields defined.</div>';
            return;
        }
        container.innerHTML = fields.map((gf, idx) => `
            <div style="display:flex; align-items:center; gap:4px; margin-bottom:4px; background:var(--bg); border-radius:4px; padding:4px 6px;">
                <span style="font-size:10px; color:var(--text-muted); min-width:16px;">${idx + 1}.</span>
                <input type="text" value="${escapeHtml(gf.field || '')}" onchange="sgfUpdateGroupField(${idx}, this.value)"
                       placeholder="field_name" style="flex:1; background:var(--bg); border:1px solid var(--border); color:var(--text); padding:3px 6px; border-radius:3px; font-size:11px; font-family:monospace;">
                <button onclick="sgfRemoveGroup(${idx})" style="padding:2px 6px; font-size:12px; border:none; background:none; color:var(--danger); cursor:pointer;">×</button>
            </div>
        `).join('');
    }

    function sgfAddGroup() {
        if (!dataOptions.groupFields) dataOptions.groupFields = [];
        dataOptions.groupFields.push({ field: '', sortDirection: 'asc', keepTogether: true, repeatHeader: true, newPageBefore: false });
        sgfRenderGroupList();
    }

    function sgfRemoveGroup(idx) {
        dataOptions.groupFields.splice(idx, 1);
        sgfRenderGroupList();
    }

    function sgfUpdateGroupField(idx, value) {
        if (dataOptions.groupFields[idx]) {
            dataOptions.groupFields[idx].field = value;
        }
    }

    function sgfUpdateFilter(value) {
        dataOptions.filterExpression = value;
    }

    // ── Field Explorer ───────────────────────────────────────
    function getUsedFieldKeys() {
        const used = {};
        const allEls = flattenSections();
        allEls.forEach(el => {
            if (el.type === 'field' && el.key) used[el.key] = true;
            if (el.type === 'table' && el.key) used[el.key] = true;
        });
        return used;
    }

    function getSchemaById(schemaId) {
        return availableSchemas.find(s => s.id == schemaId);
    }

    function loadFieldExplorer() {
        const select = document.getElementById('explorer-schema-select');
        const container = document.getElementById('fe-container');
        const schemaId = select.value;

        // Sync with data tab selector
        const dataSelect = document.getElementById('data-schema-select');
        if (dataSelect && dataSelect.value !== schemaId) {
            dataSelect.value = schemaId;
            loadSelectedSchema();
        }

        if (!schemaId) {
            container.innerHTML = '<div style="text-align:center; color:var(--text-muted); padding:2rem 1rem; font-size:0.8rem;">Select a schema to browse fields</div>';
            document.getElementById('schema-info-bar').style.display = 'none';
            document.getElementById('multi-schema-indicator').style.display = 'none';
            document.getElementById('schema-outdated-banner-explorer').style.display = 'none';
            return;
        }

        const schema = getSchemaById(schemaId);
        if (!schema) return;

        // Check if we have multiple schemas attached to show a combined view
        const attachedSchemas = getAttachedTemplateSchemas();
        const hasMultiple = attachedSchemas.length > 1;

        // Show schema info bar
        const infoBar = document.getElementById('schema-info-bar');
        const fieldCount = Object.keys(schema.fields || {}).length;
        const tableCount = Object.keys(schema.tables || {}).length;
        const usedKeys = getUsedFieldKeys();
        const usedCount = Object.keys(usedKeys).length;
        infoBar.style.display = 'flex';
        infoBar.innerHTML = `
            <span class="schema-name">${schema.label || schema.schema_name}</span>
            <span class="schema-version">v${schema.version}</span>
            <span class="schema-count">${fieldCount} fields · ${tableCount} tables · ${usedCount} used</span>
        `;

        // Show multi-schema indicator
        const multiIndicator = document.getElementById('multi-schema-indicator');
        if (hasMultiple) {
            multiIndicator.style.display = 'block';
            multiIndicator.innerHTML = `📦 ${attachedSchemas.length} schemas attached — showing <strong>${schema.label || schema.schema_name}</strong>`;
        } else {
            multiIndicator.style.display = 'none';
        }

        // Check schema sync status
        checkSchemaSync(schema);

        // Build hierarchical tree
        buildFieldExplorerTree(schema, container, usedKeys);
        document.getElementById('fe-search-input').value = '';
    }

    /**
     * Get all schemas currently attached to this template (from the pivot relationship).
     * Falls back to the legacy single schema if no pivot data exists.
     */
    function getAttachedTemplateSchemas() {
        if (templateSchemas && templateSchemas.length > 0) {
            return templateSchemas;
        }
        // Fallback: return the primary schema if selected
        const primaryId = document.getElementById('data-schema-select')?.value;
        if (primaryId) {
            const schema = getSchemaById(primaryId);
            if (schema) return [{ ...schema, pivot: { alias: null }, is_primary: true }];
        }
        return [];
    }

    /**
     * Build the field explorer tree. Supports multi-schema "Explore All" mode
     * where fields are grouped by client app.
     */
    function buildFieldExplorerTree(schema, container, usedKeys) {
        const fields = schema.fields || {};
        const tables = schema.tables || {};
        const allUsed = usedKeys || getUsedFieldKeys();
        const filterVal = (document.getElementById('fe-search-input')?.value || '').toLowerCase();

        // Check if we should render in multi-schema grouped mode
        const attachedSchemas = getAttachedTemplateSchemas();
        const hasMultiple = attachedSchemas.length > 1;

        if (hasMultiple) {
            // Multi-schema mode: render all attached schemas grouped by client app
            renderMultiSchemaExplorer(attachedSchemas, container, allUsed, filterVal);
            return;
        }

        // ── Single-schema mode (original behavior) ──
        const groups = {};
        const ungrouped = {};

        for (const [key, meta] of Object.entries(fields)) {
            const parts = key.split('.');
            if (parts.length > 1) {
                const ns = parts[0];
                if (!groups[ns]) groups[ns] = {};
                groups[ns][key] = meta;
            } else {
                ungrouped[key] = meta;
            }
        }

        let html = '';

        // Helper to render a field item
        function renderFieldItem(key, meta) {
            if (filterVal && !key.toLowerCase().includes(filterVal) && !(meta.label || '').toLowerCase().includes(filterVal)) return '';
            const type = meta.type || 'string';
            const format = meta.format || '';
            const isUsed = !!allUsed[key];
            let statusClass = 'fe-status-unused';
            let statusIcon = '○';
            if (isUsed) { statusClass = 'fe-status-used'; statusIcon = '✓'; }
            
            let previewVal = '';
            if (schema.sample_data) {
                const resolved = resolveDataValue(key, schema.sample_data);
                if (resolved !== null && resolved !== undefined) previewVal = String(resolved);
            }
            
            const typeClass = format === 'currency' ? 'currency' : type;
            const labelAttr = (meta.label || key) + (previewVal ? ` → ${previewVal.substring(0, 30)}` : '');
            
            return `<div class="fe-item" draggable="true"
                ondragstart="feDragStart(event, '${key}', 'field')"
                ondragend="feDragEnd(event)"
                onclick="feFieldClick('${key}', 'field')"
                onmouseover="feShowPreview(event, '${key}', '${escapeJs(previewVal)}')"
                onmouseout="feHidePreview()"
                title="${labelAttr}">
                <span class="fe-status ${statusClass}">${statusIcon}</span>
                <span>${key}</span>
                <span class="fe-type ${typeClass}">${type}${format ? ':'+format : ''}</span>
                ${meta.required ? '<span style="color:#ef4444;font-size:9px;">*req</span>' : ''}
                ${previewVal ? `<span style="margin-left:auto;color:var(--text-muted);font-size:9px;overflow:hidden;text-overflow:ellipsis;max-width:60px;">${previewVal.substring(0, 10)}</span>` : ''}
            </div>`;
        }

        // Render ungrouped fields (flat keys)
        const uFields = Object.entries(ungrouped).filter(([k, m]) => !filterVal || k.toLowerCase().includes(filterVal) || (m.label || '').toLowerCase().includes(filterVal));
        if (uFields.length > 0) {
            html += `<div class="fe-group">
                <div class="fe-group-header" onclick="toggleFeGroup(this)">
                    <span class="arrow">▾</span> General Fields <span style="color:var(--text-muted);font-weight:normal;">(${uFields.length})</span>
                </div>
                <div class="fe-group-body">`;
            uFields.forEach(([key, meta]) => { html += renderFieldItem(key, meta); });
            html += `</div></div>`;
        }

        // Render grouped (namespaced) fields
        for (const [ns, nsFields] of Object.entries(groups)) {
            const nsEntries = Object.entries(nsFields).filter(([k, m]) => !filterVal || k.toLowerCase().includes(filterVal) || (m.label || '').toLowerCase().includes(filterVal));
            if (nsEntries.length === 0 && filterVal) continue;
            html += `<div class="fe-group">
                <div class="fe-group-header" onclick="toggleFeGroup(this)">
                    <span class="arrow">▾</span> ${ns} <span style="color:var(--text-muted);font-weight:normal;">(${nsEntries.length})</span>
                </div>
                <div class="fe-group-body">`;
            nsEntries.forEach(([key, meta]) => { html += renderFieldItem(key, meta); });
            html += `</div></div>`;
        }

        // Render tables
        const tEntries = Object.entries(tables).filter(([k, m]) => !filterVal || k.toLowerCase().includes(filterVal) || (m.label || '').toLowerCase().includes(filterVal));
        if (tEntries.length > 0) {
            html += `<div class="fe-group">
                <div class="fe-group-header" onclick="toggleFeGroup(this)">
                    <span class="arrow">▾</span> Tables <span style="color:var(--text-muted);font-weight:normal;">(${tEntries.length})</span>
                </div>
                <div class="fe-group-body">`;
            tEntries.forEach(([key, meta]) => {
                if (filterVal && !key.toLowerCase().includes(filterVal)) return;
                const cols = meta.columns || {};
                const colsSafe = encodeURIComponent(JSON.stringify(cols));
                const isUsed = !!allUsed[key];
                let statusClass = 'fe-status-unused';
                let statusIcon = '○';
                if (isUsed) { statusClass = 'fe-status-used'; statusIcon = '✓'; }
                html += `<div class="fe-item" draggable="true"
                    ondragstart="feDragStart(event, '${key}', 'table', '${colsSafe}')"
                    ondragend="feDragEnd(event)"
                    onclick="feFieldClick('${key}', 'table', '${colsSafe}')"
                    title="${meta.label || key} (${Object.keys(cols).length} columns)">
                    <span class="fe-status ${statusClass}">${statusIcon}</span>
                    <span>${key}</span>
                    <span class="fe-type" style="background:rgba(59,130,246,0.15);color:#3b82f6;">table</span>
                    <span style="margin-left:auto;color:var(--text-muted);font-size:9px;">${Object.keys(cols).length} cols</span>
                </div>`;
            });
            html += `</div></div>`;
        }

        if (!html) {
            html = '<div style="text-align:center;color:var(--text-muted);padding:2rem 1rem;font-size:0.8rem;">No fields match filter</div>';
        }

        container.innerHTML = html;
    }

    /**
     * Render the Field Explorer in multi-schema mode, grouping fields by client app.
     * Each schema's fields are rendered under a header showing the client app name/alias.
     */
    function renderMultiSchemaExplorer(schemas, container, allUsed, filterVal) {
        let html = '';

        schemas.forEach((entry, idx) => {
            const schema = entry;
            const fields = schema.fields || {};
            const tables = schema.tables || {};
            const alias = entry.pivot?.alias || entry.client_app_name || schema.schema_name;
            const fieldCount = Object.keys(fields).length;
            const tableCount = Object.keys(tables).length;
            const hasContent = fieldCount > 0 || tableCount > 0;

            if (!hasContent) return;

            // Schema group header — shows client app name/alias
            html += `<div class="fe-group">
                <div class="fe-group-header" onclick="toggleFeGroup(this)" style="${idx === 0 ? '' : 'border-top:1px dashed var(--border);'}">
                    <span class="arrow">▾</span>
                    <span style="font-weight:600;">${alias}</span>
                    <span style="color:var(--text-muted);font-weight:normal;font-size:10px;">
                        ${fieldCount} fields · ${tableCount} tables
                        ${entry.is_primary ? '<span style="color:#6366f1;margin-left:4px;">★ primary</span>' : ''}
                    </span>
                </div>
                <div class="fe-group-body">`;

            // Fields within this schema
            for (const [key, meta] of Object.entries(fields)) {
                if (filterVal && !key.toLowerCase().includes(filterVal) && !(meta.label || '').toLowerCase().includes(filterVal)) continue;
                const type = meta.type || 'string';
                const format = meta.format || '';
                const isUsed = !!allUsed[key];
                let statusClass = 'fe-status-unused';
                let statusIcon = '○';
                if (isUsed) { statusClass = 'fe-status-used'; statusIcon = '✓'; }

                let previewVal = '';
                if (schema.sample_data) {
                    const resolved = resolveDataValue(key, schema.sample_data);
                    if (resolved !== null && resolved !== undefined) previewVal = String(resolved);
                }

                const typeClass = format === 'currency' ? 'currency' : type;
                const labelAttr = (meta.label || key) + (previewVal ? ` → ${previewVal.substring(0, 30)}` : '');

                html += `<div class="fe-item" draggable="true"
                    ondragstart="feDragStart(event, '${key}', 'field')"
                    ondragend="feDragEnd(event)"
                    onclick="feFieldClick('${key}', 'field')"
                    onmouseover="feShowPreview(event, '${key}', '${escapeJs(previewVal)}')"
                    onmouseout="feHidePreview()"
                    title="${labelAttr}">
                    <span class="fe-status ${statusClass}">${statusIcon}</span>
                    <span>${key}</span>
                    <span class="fe-type ${typeClass}">${type}${format ? ':'+format : ''}</span>
                    ${meta.required ? '<span style="color:#ef4444;font-size:9px;">*req</span>' : ''}
                    ${previewVal ? `<span style="margin-left:auto;color:var(--text-muted);font-size:9px;overflow:hidden;text-overflow:ellipsis;max-width:60px;">${previewVal.substring(0, 10)}</span>` : ''}
                </div>`;
            }

            // Tables within this schema
            for (const [key, meta] of Object.entries(tables)) {
                if (filterVal && !key.toLowerCase().includes(filterVal)) continue;
                const cols = meta.columns || {};
                const colsSafe = encodeURIComponent(JSON.stringify(cols));
                const isUsed = !!allUsed[key];
                let statusClass = 'fe-status-unused';
                let statusIcon = '○';
                if (isUsed) { statusClass = 'fe-status-used'; statusIcon = '✓'; }
                html += `<div class="fe-item" draggable="true"
                    ondragstart="feDragStart(event, '${key}', 'table', '${colsSafe}')"
                    ondragend="feDragEnd(event)"
                    onclick="feFieldClick('${key}', 'table', '${colsSafe}')"
                    title="${meta.label || key} (${Object.keys(cols).length} columns)">
                    <span class="fe-status ${statusClass}">${statusIcon}</span>
                    <span>${key}</span>
                    <span class="fe-type" style="background:rgba(59,130,246,0.15);color:#3b82f6;">table</span>
                    <span style="margin-left:auto;color:var(--text-muted);font-size:9px;">${Object.keys(cols).length} cols</span>
                </div>`;
            }

            html += `</div></div>`;
        });

        if (!html) {
            html = '<div style="text-align:center;color:var(--text-muted);padding:2rem 1rem;font-size:0.8rem;">No fields available</div>';
        }

        container.innerHTML = html;
    }

    function filterFieldExplorer(val) {
        const select = document.getElementById('explorer-schema-select');
        const schema = getSchemaById(select.value);
        if (!schema) return;
        const container = document.getElementById('fe-container');
        const usedKeys = getUsedFieldKeys();
        buildFieldExplorerTree(schema, container, usedKeys);
    }

    function refreshFieldExplorer() {
        loadFieldExplorer();
    }

    function toggleFeGroup(header) {
        const body = header.nextElementSibling;
        if (!body) return;
        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? 'block' : 'none';
        header.querySelector('.arrow').classList.toggle('collapsed', !isHidden);
    }

    // ── Drag & Drop from Field Explorer to Canvas ─────────
    let feDragData = null;
    function feDragStart(e, key, type, colsStr) {
        feDragData = { key, type, colsStr: colsStr || null };
        e.dataTransfer.setData('text/plain', key);
        e.dataTransfer.effectAllowed = 'copy';
        // Create drag ghost
        const ghost = document.createElement('div');
        ghost.className = 'fe-drag-ghost';
        ghost.textContent = type === 'table' ? '▦ ' + key : 'T ' + key;
        document.body.appendChild(ghost);
        e.dataTransfer.setDragImage(ghost, 40, 12);
        setTimeout(() => ghost.remove(), 0);
        e.target.classList.add('dragging');
    }

    function feDragEnd(e) {
        e.target.classList.remove('dragging');
        document.querySelectorAll('.canvas-drop-highlight').forEach(el => el.classList.remove('canvas-drop-highlight'));
        feDragData = null;
    }

    function feFieldClick(key, type, colsStr) {
        addFieldFromSchema(key, type, colsStr);
    }

    // ── Field Preview Tooltip ──────────────────────────────
    let previewTipTimer = null;
    let previewTipEl = null;

    function feShowPreview(e, key, previewVal) {
        if (!previewVal) return;
        clearTimeout(previewTipTimer);
        previewTipTimer = setTimeout(() => {
            const existing = document.querySelector('.fe-preview-tip');
            if (existing) existing.remove();
            const tip = document.createElement('div');
            tip.className = 'fe-preview-tip';
            tip.innerHTML = `<div class="tip-label">${key}</div><div class="tip-value">${escapeHtml(previewVal)}</div>`;
            tip.style.left = (e.clientX + 12) + 'px';
            tip.style.top = (e.clientY - 10) + 'px';
            document.body.appendChild(tip);
            previewTipEl = tip;
        }, 400);
    }

    function feHidePreview() {
        clearTimeout(previewTipTimer);
        if (previewTipEl) {
            previewTipEl.remove();
            previewTipEl = null;
        }
    }

    // ── Canvas Drop Handler ────────────────────────────────
    function initCanvasDropHandlers() {
        const canvas = document.getElementById('canvas');
        if (!canvas) return;

        canvas.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'copy';
            canvas.classList.add('canvas-drop-highlight');
        });

        canvas.addEventListener('dragleave', (e) => {
            canvas.classList.remove('canvas-drop-highlight');
        });

        canvas.addEventListener('drop', (e) => {
            e.preventDefault();
            canvas.classList.remove('canvas-drop-highlight');
            if (!feDragData) return;

            const rect = canvas.getBoundingClientRect();
            const scrollLeft = canvas.parentElement?.scrollLeft || 0;
            const scrollTop = canvas.parentElement?.scrollTop || 0;
            const x = ((e.clientX - rect.left + scrollLeft) / (BASE_SCALE * zoomLevel));
            const y = ((e.clientY - rect.top + scrollTop) / (BASE_SCALE * zoomLevel));

            // Drop at cursor position
            pushHistory();
            const el = {
                id: 'el_' + Date.now(),
                type: feDragData.type,
                key: feDragData.key,
                x: Math.max(0, Math.round(x * 10) / 10),
                y: Math.max(0, Math.round(y * 10) / 10),
                width: feDragData.type === 'table' ? 60 : 40,
                height: feDragData.type === 'table' ? 20 : 5,
                font_size: 10,
                bold: false,
                border: false,
                align: 'L',
                locked: false,
                hidden: false
            };

            if (feDragData.type === 'table' && feDragData.colsStr) {
                try {
                    const colsDict = JSON.parse(decodeURIComponent(feDragData.colsStr));
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

            // Determine section from Y position
            const sectionKey = getSectionAtY(el.y);
            const sectionOffset = getSectionOffset(sectionKey);
            el.y = Math.max(0, el.y - sectionOffset);
            if (!sections[sectionKey].elements) sections[sectionKey].elements = [];
            sections[sectionKey].elements.push(el);
            elements = flattenSections();

            activeIds = [el.id]; activeId = el.id;
            renderElements();
            updateInspector();
            updateLayersList();
            // Refresh explorer to update binding status
            loadFieldExplorer();
            feDragData = null;
        });
    }

    // ── Schema Sync Detection ──────────────────────────────
    function checkSchemaSync(schema) {
        const banner = document.getElementById('schema-outdated-banner');
        const explorerBanner = document.getElementById('schema-outdated-banner-explorer');
        if (!schema) {
            if (banner) banner.style.display = 'none';
            if (explorerBanner) explorerBanner.style.display = 'none';
            return;
        }

        const latestVersion = typeof schema.latest_version !== 'undefined' ? schema.latest_version : schema.version;
        const currentVersion = schema.version;

        if (latestVersion > currentVersion) {
            // Fetch diff from server
            fetch(`/api/v1/schemas/${encodeURIComponent(schema.schema_name)}/diff?from_version=${currentVersion}&to_version=${latestVersion}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const diff = data.diff;
                    renderSchemaDiffBanner(banner, diff, schema.schema_name, latestVersion, currentVersion);
                    renderSchemaDiffBanner(explorerBanner, diff, schema.schema_name, latestVersion, currentVersion);
                }
            })
            .catch(() => {
                // Fallback to client-side diff
                const diff = computeClientSideDiff(schema, currentVersion, latestVersion);
                renderSchemaDiffBanner(banner, diff, schema.schema_name, latestVersion, currentVersion);
                renderSchemaDiffBanner(explorerBanner, diff, schema.schema_name, latestVersion, currentVersion);
            });
        } else {
            if (banner) banner.style.display = 'none';
            if (explorerBanner) explorerBanner.style.display = 'none';
        }
    }

    function renderSchemaDiffBanner(bannerEl, diff, schemaName, latestVer, currentVer) {
        if (!bannerEl) return;

        let html = `<div style="padding:8px;background:#fef3c7;border:1px solid #f59e0b;border-radius:4px;margin-bottom:8px;font-size:11px;">
            <strong>⚠️ Schema Update Available</strong><br>
            <span style="color:#92400e;">Version ${currentVer} → ${latestVer}</span>
            <div style="margin-top:4px;">`;

        if (diff.added?.length) {
            html += `<div style="color:#16a34a;">+ ${diff.added.length} field(s) added:</div>
                <div style="font-size:10px;padding-left:12px;">${diff.added.slice(0,10).map(f => '✓ ' + f).join('<br>')}${diff.added.length > 10 ? '<br>... and ' + (diff.added.length - 10) + ' more' : ''}</div>`;
        }
        if (diff.removed?.length) {
            html += `<div style="color:#dc2626;margin-top:4px;">- ${diff.removed.length} field(s) removed:</div>
                <div style="font-size:10px;padding-left:12px;">${diff.removed.slice(0,10).map(f => '✗ ' + f).join('<br>')}${diff.removed.length > 10 ? '<br>... and ' + (diff.removed.length - 10) + ' more' : ''}</div>`;
        }
        if (diff.changed?.length) {
            html += `<div style="color:#f59e0b;margin-top:4px;">~ ${diff.changed.length} field(s) type changed</div>`;
        }

        html += `</div>
            <button onclick="updateToLatestSchema(${latestVer})" style="margin-top:6px;padding:4px 10px;background:#f59e0b;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:11px;">
                Update to v${latestVer}
            </button>
        </div>`;

        bannerEl.innerHTML = html;
        bannerEl.style.display = 'block';
    }

    /**
     * Client-side fallback: compute diff between two schema versions.
     * Used when the server endpoint is unreachable.
     */
    function computeClientSideDiff(schema, currentVersion, latestVersion) {
        // Try to find the latest schema version from availableSchemas
        const latestSchema = (window.availableSchemas || [])
            .filter(s => s.schema_name === schema.schema_name)
            .sort((a, b) => b.version - a.version)[0];

        const oldFields = Object.keys(schema.fields || {});
        const newFields = latestSchema ? Object.keys(latestSchema.fields || {}) : oldFields;

        const added = newFields.filter(f => !oldFields.includes(f));
        const removed = oldFields.filter(f => !newFields.includes(f));
        const changed = [];

        return {
            added: added,
            removed: removed,
            changed: changed,
        };
    }

    function updateToLatestSchema(schemaId) {
        if (!confirm('Update schema to latest version? Existing field bindings will be preserved.')) return;
        document.getElementById('explorer-schema-select').value = schemaId;
        document.getElementById('data-schema-select').value = schemaId;
        loadFieldExplorer();
        loadSelectedSchema();
    }

    // ── Multi-Schema Management ────────────────────────────

    /**
     * Load and display the list of additional schemas attached to this template.
     */
    function loadAdditionalSchemas() {
        const list = document.getElementById('additional-schemas-list');
        if (!list) return;

        const attached = templateSchemas || [];
        // Filter out the primary schema
        const primaryId = document.getElementById('data-schema-select')?.value;
        const additional = attached.filter(s => s.id != primaryId);

        if (additional.length === 0) {
            list.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px 0;">No additional schemas attached.</div>';
            return;
        }

        let html = '';
        additional.forEach(s => {
            const alias = s.pivot?.alias || s.client_app_name || s.schema_name;
            const fieldCount = Object.keys(s.fields || {}).length;
            html += `<div style="display:flex; align-items:center; justify-content:space-between; padding:4px 6px; margin-bottom:4px; background:var(--bg); border-radius:4px; border:1px solid var(--border); font-size:11px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        ${alias}
                        ${s.client_app_name ? `<span style="font-weight:normal;color:var(--text-muted);font-size:9px;"> · ${s.client_app_name}</span>` : ''}
                    </div>
                    <div style="font-size:9px; color:var(--text-muted);">
                        ${s.label || s.schema_name} (v${s.version}) · ${fieldCount} fields
                    </div>
                </div>
                <div style="display:flex; gap:4px; flex-shrink:0; margin-left:8px;">
                    <input type="text" class="alias-input" value="${alias}"
                        onchange="updateSchemaAlias(${s.id}, this.value)"
                        placeholder="Alias"
                        style="width:70px; padding:2px 4px; font-size:10px; border:1px solid var(--border); border-radius:3px; background:var(--surface); color:var(--text);">
                    <button onclick="removeSchemaFromTemplate(${s.id})" class="action-btn" style="padding:2px 6px; font-size:10px; color:#ef4444;" title="Remove">✕</button>
                </div>
            </div>`;
        });
        list.innerHTML = html;
    }

    /**
     * Add a schema to the template's pivot table via AJAX.
     */
    function addSchemaToTemplate() {
        const select = document.getElementById('add-schema-select');
        const schemaId = select.value;
        if (!schemaId) return;

        // Check if already attached
        if (templateSchemas.some(s => s.id == schemaId)) {
            alert('This schema is already attached.');
            return;
        }

        const alias = prompt('Enter an alias for this schema (e.g. "CRM", "Accounting"):');
        if (alias === null) return; // cancelled

        // Optimistically add to local state
        const schema = availableSchemas.find(s => s.id == schemaId);
        if (!schema) return;

        const newEntry = {
            ...schema,
            pivot: { alias: alias || null },
            client_app_name: schema.client_app_name || null,
        };
        templateSchemas.push(newEntry);

        // Disable this option in the add dropdown
        select.querySelector(`option[value="${schemaId}"]`).disabled = true;
        select.value = '';

        loadAdditionalSchemas();
        loadFieldExplorer();
        updateMultiSchemaSelect();

        // Persist via AJAX
        saveTemplateSchemas();
    }

    /**
     * Remove a schema from the template's pivot table.
     */
    function removeSchemaFromTemplate(schemaId) {
        if (!confirm('Remove this schema from the template?')) return;

        const idx = templateSchemas.findIndex(s => s.id == schemaId);
        if (idx === -1) return;
        templateSchemas.splice(idx, 1);

        // Re-enable in add dropdown
        const addSelect = document.getElementById('add-schema-select');
        if (addSelect) {
            addSelect.querySelector(`option[value="${schemaId}"]`).disabled = false;
        }

        loadAdditionalSchemas();
        loadFieldExplorer();
        updateMultiSchemaSelect();

        // Persist via AJAX
        saveTemplateSchemas();
    }

    /**
     * Update the alias for a schema in the pivot table.
     */
    function updateSchemaAlias(schemaId, newAlias) {
        const entry = templateSchemas.find(s => s.id == schemaId);
        if (entry) {
            if (!entry.pivot) entry.pivot = {};
            entry.pivot.alias = newAlias || null;
        }
        saveTemplateSchemas();
    }

    /**
     * Persist the current set of attached schemas to the server via AJAX.
     * Sends the full list of schema IDs with aliases.
     */
    function saveTemplateSchemas() {
        if (!templateId) return;

        const schemasData = templateSchemas.map(s => ({
            id: s.id,
            alias: s.pivot?.alias || null,
        }));

        fetch(`/admin/templates/${templateId}/schemas`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ schemas: schemasData }),
        }).catch(err => console.error('Failed to save schemas:', err));
    }

    /**
     * Update the multi-schema select options in the explorer to show client app names.
     */
    function updateMultiSchemaSelect() {
        // Refresh the explorer select options to show attached schemas status
        const attached = templateSchemas || [];
        const multiIndicator = document.getElementById('multi-schema-indicator');
        if (multiIndicator && attached.length > 1) {
            multiIndicator.style.display = 'block';
            multiIndicator.innerHTML = `📦 ${attached.length} schemas attached`;
        } else if (multiIndicator) {
            multiIndicator.style.display = 'none';
        }
    }

    // ── Autocomplete for Field Key in Inspector ────────────
    function getAutocompleteFieldKeys() {
        const schemaId = document.getElementById('data-schema-select')?.value;
        if (!schemaId) return [];
        const schema = availableSchemas.find(s => s.id == schemaId);
        if (!schema || !schema.fields) return [];
        // Also include fields from additional schemas
        const keys = Object.keys(schema.fields);
        if (templateSchemas && templateSchemas.length > 1) {
            templateSchemas.forEach(s => {
                if (s.id == schemaId) return;
                if (s.fields) {
                    const prefix = (s.pivot?.alias || s.client_app_name || s.schema_name || '').toLowerCase();
                    Object.keys(s.fields).forEach(k => {
                        if (prefix) keys.push(prefix + '.' + k);
                        else keys.push(k);
                    });
                }
            });
        }
        return keys;
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
        const cw = document.getElementById('canvas-wrapper');
        if (!c) { console.error('[Designer] updateCanvasSize: canvas not found'); return; }
        
        const rawW = w * BASE_SCALE;
        const rawH = canvasH * BASE_SCALE;
        
        c.style.width = rawW + 'px';
        c.style.height = rawH + 'px';
        c.style.transform = `scale(${zoomLevel})`;
        
        if (cw) {
            cw.style.width = (rawW * zoomLevel) + 'px';
            cw.style.height = (rawH * zoomLevel) + 'px';
        }

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
        if (!sections.detail.elements) sections.detail.elements = [];
        sections.detail.elements.push(el);
        elements = flattenSections();
        renderElements(); selectElements([id]);
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
            const sourceKey = findElementSection(orig.id) || 'detail';
            if (!sections[sourceKey].elements) sections[sourceKey].elements = [];
            sections[sourceKey].elements.push(copy);
            newIds.push(copy.id);
        });
        elements = flattenSections();
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
        // Also validate against additional schemas' fields
        if (templateSchemas && templateSchemas.length > 1) {
            templateSchemas.forEach(s => {
                if (s.id == activeSchemaId) return;
                if (s.fields) {
                    const prefix = (s.pivot?.alias || s.client_app_name || '').toLowerCase();
                    Object.keys(s.fields).forEach(k => {
                        validKeys.push(prefix ? prefix + '.' + k : k);
                    });
                }
                if (s.tables) {
                    Object.keys(s.tables).forEach(k => validTables.push(k));
                }
            });
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

                // ── Binding status indicator ──────────────
                const bindInd = document.createElement('div');
                bindInd.className = 'bind-indicator';

                const fieldKey = displayEl.key;
                if (fieldKey && activeSchema) {
                    const keyExists = validKeys.includes(fieldKey) || validTables.includes(fieldKey);
                    if (keyExists) {
                        bindInd.className += ' bind-bound';
                        bindInd.textContent = '✓';
                        bindInd.title = 'Bound to schema field: ' + fieldKey;
                    } else {
                        bindInd.className += ' bind-unbound';
                        bindInd.textContent = '✗';
                        bindInd.title = 'Field "' + fieldKey + '" not found in schema';
                    }
                } else if (fieldKey && !activeSchema) {
                    bindInd.className += ' bind-unresolved';
                    bindInd.textContent = '?';
                    bindInd.title = 'No schema loaded for this field key';
                } else {
                    // No field key — not a data-bound element (static text, image, etc.)
                    bindInd.style.display = 'none';
                }
                div.appendChild(bindInd);

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
                <div class="prop-item"><div class="prop-key">${el.type==='label'?'Text':'Key'}</div><div class="prop-val"><input type="text" value="${el.type==='label'?(el.text||''):(el.key||'')}" oninput="updateElProps('${el.type==='label'?'text':'key'}', this.value)"${el.type==='field'?' list="field-key-list"':''}></div></div>
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
            html += `
            <div class="fe-container" id="fe-container">
                <div class="fe-tabs">
                    <div class="fe-tab active" data-fe-tab="rt" onclick="feSwitchTab('rt', 'running_total')">
                        <span class="fe-tab-icon">📊</span> RT Builder
                    </div>
                    <div class="fe-tab" data-fe-tab="fn-ref" onclick="feSwitchTab('fn-ref', 'running_total')">
                        <span class="fe-tab-icon">📖</span> Functions
                    </div>
                </div>
                <div class="fe-panel active" id="fe-panel-rt">
                    <div class="rt-container">
                        <div class="rt-row">
                            <label>Operation</label>
                            <select onchange="rtUpdateConfig(el,'operation',this.value);rtUpdatePreview(el)">
                                <option value="sum" ${(el.operation||'sum')==='sum'?'selected':''}>Sum</option>
                                <option value="count" ${el.operation==='count'?'selected':''}>Count</option>
                                <option value="average" ${el.operation==='average'?'selected':''}>Average</option>
                                <option value="min" ${el.operation==='min'?'selected':''}>Min</option>
                                <option value="max" ${el.operation==='max'?'selected':''}>Max</option>
                            </select>
                        </div>
                        <div class="rt-row">
                            <label>Field</label>
                            <select class="fe-field-dropdown" onchange="rtUpdateConfig(el,'field',this.value);rtUpdatePreview(el)">
                                <option value="">— Select field —</option>
                            </select>
                        </div>
                        <div class="rt-field-group">
                            <div class="rt-field-group-title">⚙️ Evaluate Condition</div>
                            <div class="rt-toggle-row">
                                <input type="checkbox" id="rt-eval-enabled" ${el.evaluateCondition?.enabled?'checked':''} onchange="rtToggleEvalField(el,this.checked)">
                                <label for="rt-eval-enabled">Enable condition</label>
                                <span class="rt-toggle-hint">(evaluate only when condition matches)</span>
                            </div>
                            <div id="rt-eval-fields" style="${el.evaluateCondition?.enabled?'':'display:none;'}margin-top:4px;">
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Field</label>
                                    <select class="fe-field-dropdown" onchange="rtUpdateConfig(el,'evaluateCondition',{field:this.value,operator:el.evaluateCondition?.operator||'==',value:el.evaluateCondition?.value||''});rtUpdatePreview(el)">
                                        <option value="">— Select —</option>
                                    </select>
                                </div>
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Op</label>
                                    <select onchange="rtUpdateConfig(el,'evaluateCondition',{field:el.evaluateCondition?.field||'',operator:this.value,value:el.evaluateCondition?.value||''});rtUpdatePreview(el)">
                                        <option value="==" ${el.evaluateCondition?.operator==='=='?'selected':''}>=</option>
                                        <option value="!=" ${el.evaluateCondition?.operator==='!='?'selected':''}>≠</option>
                                        <option value=">" ${el.evaluateCondition?.operator==='>'?'selected':''}>></option>
                                        <option value=">=" ${el.evaluateCondition?.operator==='>='?'selected':''}>≥</option>
                                        <option value="<" ${el.evaluateCondition?.operator==='<'?'selected':''}><</option>
                                        <option value="<=" ${el.evaluateCondition?.operator==='<='?'selected':''}>≤</option>
                                    </select>
                                </div>
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Value</label>
                                    <input type="text" value="${escapeHtml(el.evaluateCondition?.value||'')}" onchange="rtUpdateConfig(el,'evaluateCondition',{field:el.evaluateCondition?.field||'',operator:el.evaluateCondition?.operator||'==',value:this.value});rtUpdatePreview(el)" placeholder="value">
                                </div>
                            </div>
                        </div>
                        <div class="rt-field-group">
                            <div class="rt-field-group-title">🔄 Reset Condition</div>
                            <div class="rt-toggle-row">
                                <input type="checkbox" id="rt-reset-enabled" ${el.resetCondition?.enabled?'checked':''} onchange="rtToggleResetField(el,this.checked)">
                                <label for="rt-reset-enabled">Enable condition</label>
                                <span class="rt-toggle-hint">(reset when condition matches)</span>
                            </div>
                            <div id="rt-reset-fields" style="${el.resetCondition?.enabled?'':'display:none;'}margin-top:4px;">
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Field</label>
                                    <select class="fe-field-dropdown" onchange="rtUpdateConfig(el,'resetCondition',{field:this.value,operator:el.resetCondition?.operator||'==',value:el.resetCondition?.value||''});rtUpdatePreview(el)">
                                        <option value="">— Select —</option>
                                    </select>
                                </div>
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Op</label>
                                    <select onchange="rtUpdateConfig(el,'resetCondition',{field:el.resetCondition?.field||'',operator:this.value,value:el.resetCondition?.value||''});rtUpdatePreview(el)">
                                        <option value="==" ${el.resetCondition?.operator==='=='?'selected':''}>=</option>
                                        <option value="!=" ${el.resetCondition?.operator==='!='?'selected':''}>≠</option>
                                        <option value=">" ${el.resetCondition?.operator==='>'?'selected':''}>></option>
                                        <option value=">=" ${el.resetCondition?.operator==='>='?'selected':''}>≥</option>
                                        <option value="<" ${el.resetCondition?.operator==='<'?'selected':''}><</option>
                                        <option value="<=" ${el.resetCondition?.operator==='<='?'selected':''}>≤</option>
                                    </select>
                                </div>
                                <div class="rt-row" style="margin-bottom:2px;">
                                    <label style="min-width:50px;">Value</label>
                                    <input type="text" value="${escapeHtml(el.resetCondition?.value||'')}" onchange="rtUpdateConfig(el,'resetCondition',{field:el.resetCondition?.field||'',operator:el.resetCondition?.operator||'==',value:this.value});rtUpdatePreview(el)" placeholder="value">
                                </div>
                            </div>
                        </div>
                        <div class="rt-preview" id="rt-preview"></div>
                    </div>
                    <div id="rt-config-json" style="display:none;"></div>
                </div>
                <div class="fe-panel" id="fe-panel-fn-ref">
                    <div class="fe-functions-list" id="fe-functions-list-rt"></div>
                </div>
            </div>`;
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
                <div class="prop-item"><div class="prop-key">Border</div><div class="prop-val"><select onchange="updateElProps('border',this.value)">
                    <option value="none" ${(!el.border||el.border==='none'||el.border===false)?'selected':''}>None</option>
                    <option value="solid" ${el.border==='solid'||el.border===true||el.border===1?'selected':''}>Solid</option>
                    <option value="dashed" ${el.border==='dashed'?'selected':''}>Dashed</option>
                    <option value="dotted" ${el.border==='dotted'?'selected':''}>Dotted</option>
                </select></div></div>
                <div class="prop-item"><div class="prop-key">Rotation</div><div class="prop-val"><input type="number" min="0" max="360" step="1" value="${el.rotation||0}" oninput="updateElProps('rotation',parseInt(this.value)||0)" title="Rotation angle 0-360°" style="width:60px;">°</div></div>
                <div class="prop-item"><div class="prop-key">Opacity</div><div class="prop-val"><input type="range" min="0" max="100" step="5" value="${el.opacity!==undefined?el.opacity:100}" oninput="updateElProps('opacity',parseInt(this.value)); this.nextElementSibling.textContent=this.value+'%'" style="width:60px;vertical-align:middle;"> <span style="font-size:10px;color:var(--text-muted);">${el.opacity!==undefined?el.opacity:100}%</span></div></div>
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
            <div class="cf-editor" id="cf-editor-container" style="display:none;">
                <label style="font-size:11px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">
                    🎨 Conditional Formatting
                </label>
                <div id="cf-rules-list"></div>
                <button class="cf-add-rule" onclick="cfAddRule()">+ Add Rule</button>
            </div>
            <div id="cf-hidden-json" style="display:none;"></div>`;

            // ── Visual Formula Editor ──────────────────────────
            html += `
            <div class="fe-container" id="fe-container" style="display:none;">
                <div class="fe-tabs">
                    <div class="fe-tab active" data-fe-tab="editor" onclick="feSwitchTab('editor')">
                        <span class="fe-tab-icon">✏️</span> Formula
                    </div>
                    <div class="fe-tab" data-fe-tab="fn-ref" onclick="feSwitchTab('fn-ref')">
                        <span class="fe-tab-icon">📖</span> Functions
                    </div>
                </div>
                <div class="fe-panel active" id="fe-panel-editor">
                    <div class="fe-editor-toolbar">
                        <button onclick="feInsertField(el)" title="Insert data field">📋 Field</button>
                        <button onclick="feShowPicker(el)" title="Pick from schema fields">🔍 Pick</button>
                        <button class="fe-btn-primary" onclick="feValidateFormula(el)" title="Validate formula">✓ Validate</button>
                    </div>
                    <div class="fe-editor-area">
                        <textarea class="fe-editor-textarea" id="fe-formula-input"
                            placeholder="Enter formula...&#10;e.g.: SUM(items.amount)&#10;e.g.: CONCAT(first_name, ' ', last_name)"
                            oninput="feValidateFormula(el, true)">${escapeHtml(el.formula || '')}</textarea>
                    </div>
                    <div class="fe-validation pending" id="fe-validation">Enter a formula expression</div>
                    <div style="margin-top:6px;">
                        <select class="fe-field-dropdown" id="fe-field-picker" onchange="feInsertSelectedField(el, this)">
                            <option value="">— Insert data field —</option>
                        </select>
                    </div>
                </div>
                <div class="fe-panel" id="fe-panel-fn-ref">
                    <div class="fe-functions-list" id="fe-functions-list"></div>
                </div>
            </div>`;

            // ── Advanced Properties ──────────────────────────────
            const pad = el.padding || {};
            const paddingJson = JSON.stringify(pad).replace(/"/g, '"');
            html += `
            <div class="props-section"><div class="props-label" style="cursor:pointer;" onclick="const s=this.nextElementSibling; s.style.display=s.style.display==='none'?'':'none'; this.querySelector('.adv-toggle').textContent=s.style.display==='none'?'▶':'▼';">Advanced <span class="adv-toggle" style="font-size:9px;margin-left:4px;">▼</span></div>
            <div class="prop-table">
                <div class="prop-item"><div class="prop-key">Tooltip</div><div class="prop-val"><input type="text" value="${escapeHtml(el.tooltip||'')}" oninput="updateElProps('tooltip',this.value)" placeholder="Hover text"></div></div>
                ${el.type === 'field' || el.type === 'label' ? `
                <div class="prop-item"><div class="prop-key">Print When</div><div class="prop-val"><input type="text" value="${escapeHtml(el.print_when||'')}" oninput="updateElProps('print_when',this.value)" placeholder='e.g. {amount} > 0' style="font-family:monospace;font-size:10px;"></div></div>
                ` : ''}
                ${el.type === 'field' ? `
                <div class="prop-item"><div class="prop-key">Suppress Duplicate</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.suppress_if_duplicate?'checked':''} onchange="updateElProps('suppress_if_duplicate',this.checked)"></div></div>
                ` : ''}
                <div class="prop-item"><div class="prop-key">Can Grow</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.can_grow?'checked':''} onchange="updateElProps('can_grow',this.checked)" title="Allow element to expand vertically to fit content"></div></div>
                <div class="prop-item"><div class="prop-key">Keep Together</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.keep_together?'checked':''} onchange="updateElProps('keep_together',this.checked)" title="Prevent page break within this element"></div></div>
                ${el.type !== 'table' && el.type !== 'line' ? `
                <div class="prop-item" style="flex-direction:column;align-items:stretch;border-bottom:none;">
                    <div style="display:flex;justify-content:space-between;padding:4px 8px;background:rgba(59,130,246,0.08);border-radius:3px;">
                        <span style="font-size:10px;font-weight:600;color:var(--primary);">🔗 Hyperlink</span>
                    </div>
                    <div style="padding:4px;">
                        <div style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                            <span style="font-size:9px;color:var(--text-muted);width:36px;">Type</span>
                            <select onchange="updateElProps('linkType',this.value)" style="flex:1;">
                                <option value="none" ${(!el.linkType||el.linkType==='none')?'selected':''}>None</option>
                                <option value="url" ${el.linkType==='url'?'selected':''}>URL</option>
                                <option value="email" ${el.linkType==='email'?'selected':''}>Email</option>
                            </select>
                        </div>
                        ${el.linkType && el.linkType !== 'none' ? `
                        <div style="display:flex;align-items:center;gap:4px;">
                            <span style="font-size:9px;color:var(--text-muted);width:36px;">URL</span>
                            <input type="text" value="${escapeHtml(el.linkUrl||'')}" oninput="updateElProps('linkUrl',this.value)" placeholder="https://... or @{{field}}" style="flex:1;font-family:monospace;font-size:10px;">
                        </div>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                <div class="prop-item" style="flex-direction:column;align-items:stretch;">
                    <div style="display:flex;justify-content:space-between;padding:4px 8px;background:rgba(255,255,255,0.03);">
                        <span style="font-size:10px;font-weight:600;color:var(--text-muted);">Padding</span>
                        <button onclick="const p=this.parentElement.nextElementSibling; p.style.display=p.style.display==='none'?'grid':'none'; this.textContent=p.style.display==='none'?'✚':'✕';" style="background:none;border:none;color:var(--primary);cursor:pointer;font-size:11px;">✚</button>
                    </div>
                    <div class="padding-grid" style="display:none;grid-template-columns:1fr 1fr;gap:2px;padding:4px;">
                        <div style="display:flex;align-items:center;gap:4px;"><span style="font-size:9px;width:24px;">Top</span><input type="number" min="0" max="20" step="0.5" value="${pad.top||0}" oninput="const p=Object.assign({},el.padding||{}); p.top=parseFloat(this.value)||0; updateElProps('padding',p)" style="width:50px;"></div>
                        <div style="display:flex;align-items:center;gap:4px;"><span style="font-size:9px;width:24px;">Right</span><input type="number" min="0" max="20" step="0.5" value="${pad.right||0}" oninput="const p=Object.assign({},el.padding||{}); p.right=parseFloat(this.value)||0; updateElProps('padding',p)" style="width:50px;"></div>
                        <div style="display:flex;align-items:center;gap:4px;"><span style="font-size:9px;width:24px;">Bottom</span><input type="number" min="0" max="20" step="0.5" value="${pad.bottom||0}" oninput="const p=Object.assign({},el.padding||{}); p.bottom=parseFloat(this.value)||0; updateElProps('padding',p)" style="width:50px;"></div>
                        <div style="display:flex;align-items:center;gap:4px;"><span style="font-size:9px;width:24px;">Left</span><input type="number" min="0" max="20" step="0.5" value="${pad.left||0}" oninput="const p=Object.assign({},el.padding||{}); p.left=parseFloat(this.value)||0; updateElProps('padding',p)" style="width:50px;"></div>
                    </div>
                </div>
            </div></div>`;
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
        html += '<datalist id="field-key-list"></datalist>';
        cont.innerHTML = html;
        // Populate field key autocomplete datalist
        const datalist = document.getElementById('field-key-list');
        if (datalist && typeof getAutocompleteFieldKeys === 'function') {
            const keys = getAutocompleteFieldKeys();
            datalist.innerHTML = keys.map(k => `<option value="${k}">`).join('');
        }
        // Initialize conditional formatting editor
        if (el.type === 'field' || el.type === 'label') {
            cfInitEditor(el);
        } else {
            const container = document.getElementById('cf-editor-container');
            if (container) container.style.display = 'none';
        }
        // Initialize visual formula editor for field/label elements
        if (el.type === 'field' || el.type === 'label') {
            feInit(el);
        } else {
            const feContainer = document.getElementById('fe-container');
            if (feContainer) feContainer.style.display = 'none';
        }
        // Initialize running total builder
        if (el.type === 'running_total') {
            rtLoadConfig(el);
            // Populate field dropdowns for the RT builder
            fePopulateFieldDropdowns(document.querySelector('#fe-container .fe-field-dropdown') || document.querySelector('.rt-container .fe-field-dropdown'));
        }
    }

    function updateCol(idx, prop, val) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns[idx]){el.columns[idx][prop]=val;renderElements();if(prop==='format_type')updateInspector();} }
    function addCol() { const el=elements.find(e=>e.id===activeId); if(el&&el.type==='table'){if(!el.columns)el.columns=[];el.columns.push({label:'Col',key:'key',width:30,align:'L'});updateInspector();} }
    function deleteCol(idx) { const el=elements.find(e=>e.id===activeId); if(el&&el.columns.length>1){el.columns.splice(idx,1);updateInspector();renderElements();} }
    function moveColUp(idx) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el&&idx>0){const c=el.columns.splice(idx,1)[0]; el.columns.splice(idx-1,0,c); updateInspector(); renderElements(); } }
    function moveColDown(idx) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el&&idx<el.columns.length-1){const c=el.columns.splice(idx,1)[0]; el.columns.splice(idx+1,0,c); updateInspector(); renderElements(); } }
    function updateElProps(prop,val) { pushHistory(); const el=elements.find(e=>e.id===activeId); if(el){el[prop]=val;renderElements();} }
    function deleteActive() { 
        if(!confirm('Delete selected element(s)?'))return; 
        pushHistory(); 
        SECTION_ORDER.forEach(key => {
            if (sections[key] && sections[key].elements) {
                sections[key].elements = sections[key].elements.filter(el => !activeIds.includes(el.id));
            }
        });
        elements = flattenSections();
        activeIds=[];
        activeId=null; 
        renderElements();
        updateInspector(); 
    }

    // ── Conditional Formatting Editor ─────────────────────────

    let cfCurrentElement = null;

    function cfInitEditor(el) {
        cfCurrentElement = el;
        const container = document.getElementById('cf-editor-container');
        if (!container) return;
        
        // Parse existing rules from element
        let rules = [];
        try {
            if (el.conditional_format) {
                rules = typeof el.conditional_format === 'string'
                    ? JSON.parse(el.conditional_format)
                    : el.conditional_format;
            }
        } catch(e) { rules = []; }
        
        if (!Array.isArray(rules)) rules = [];
        
        container.style.display = 'block';
        cfRenderRules(rules);
    }

    function cfRenderRules(rules) {
        const list = document.getElementById('cf-rules-list');
        if (!list) return;
        
        if (!rules.length) {
            list.innerHTML = '<div style="font-size:11px;color:var(--text-muted);padding:8px 0;text-align:center;">No conditional formatting rules.</div>';
            return;
        }
        
        list.innerHTML = rules.map((rule, i) => {
            const condition = rule.condition || {};
            const style = rule.style || {};
            
            return `
                <div class="cf-rule" data-index="${i}">
                    <div class="cf-rule-header">
                        <span class="cf-rule-title">Rule #${i + 1}</span>
                        <button class="cf-rule-remove" onclick="cfRemoveRule(${i})">✕</button>
                    </div>
                    <div class="cf-condition-row">
                        <span style="font-size:11px;color:var(--text-muted);">IF</span>
                        <select class="cf-field-picker" onchange="cfUpdateRule(${i},'condition','field',this.value)">
                            ${cfGetFieldOptions(condition.field || '')}
                        </select>
                        <select class="cf-operator" onchange="cfUpdateRule(${i},'condition','operator',this.value)">
                            ${cfGetOperatorOptions(condition.operator || '==')}
                        </select>
                        <input class="cf-value-input" type="text" value="${cfEsc(condition.value || '')}"
                            placeholder="Value" onchange="cfUpdateRule(${i},'condition','value',this.value)">
                    </div>
                    <div class="cf-style-preview">
                        <label>Text:</label>
                        <input type="color" value="${style.color || '#000000'}"
                            onchange="cfUpdateRule(${i},'style','color',this.value)">
                        <label>Bg:</label>
                        <input type="color" value="${style.background || '#ffffff'}"
                            onchange="cfUpdateRule(${i},'style','background',this.value)">
                        <label><input type="checkbox" ${style.bold ? 'checked' : ''}
                            onchange="cfUpdateRule(${i},'style','bold',this.checked)"> Bold</label>
                        <label><input type="checkbox" ${style.italic ? 'checked' : ''}
                            onchange="cfUpdateRule(${i},'style','italic',this.checked)"> Italic</label>
                        <label><input type="checkbox" ${style.underline ? 'checked' : ''}
                            onchange="cfUpdateRule(${i},'style','underline',this.checked)"> Underline</label>
                    </div>
                    <div class="cf-preview-box" style="color:${style.color || '#000'};background:${style.background || '#fff'};font-weight:${style.bold ? 'bold' : 'normal'};font-style:${style.italic ? 'italic' : 'normal'};text-decoration:${style.underline ? 'underline' : 'none'};">
                        Preview: "Sample Text"
                    </div>
                </div>
            `;
        }).join('');
        
        cfSaveToElement(rules);
    }

    function cfGetFieldOptions(selected) {
        // Get field keys from the current schema
        let fields = [];
        if (typeof getAutocompleteFieldKeys === 'function') {
            fields = getAutocompleteFieldKeys();
        } else if (window.currentSchemaData?.fields) {
            fields = window.currentSchemaData.fields.map(f => f.key || f.name);
        }
        
        let opts = '<option value="">— Select field —</option>';
        fields.forEach(f => {
            opts += `<option value="${cfEsc(f)}" ${f === selected ? 'selected' : ''}>${cfEsc(f)}</option>`;
        });
        return opts;
    }

    function cfGetOperatorOptions(selected) {
        const ops = [
            { val: '==', label: 'equals' },
            { val: '!=', label: 'not equals' },
            { val: '>', label: 'greater than' },
            { val: '>=', label: 'greater or equal' },
            { val: '<', label: 'less than' },
            { val: '<=', label: 'less or equal' },
            { val: 'contains', label: 'contains' },
            { val: 'starts_with', label: 'starts with' },
            { val: 'ends_with', label: 'ends with' },
            { val: 'is_empty', label: 'is empty' },
            { val: 'not_empty', label: 'is not empty' },
        ];
        return ops.map(o => `<option value="${o.val}" ${o.val === selected ? 'selected' : ''}>${o.label}</option>`).join('');
    }

    function cfUpdateRule(index, section, field, value) {
        const container = document.getElementById('cf-rules-list');
        if (!container) return;
        
        // Get current rules from the hidden storage
        let rules = cfGetCurrentRules();
        
        if (!rules[index]) rules[index] = { condition: {}, style: {} };
        if (!rules[index][section]) rules[index][section] = {};
        
        rules[index][section][field] = value;
        
        // Re-render to update preview
        cfRenderRules(rules);
    }

    function cfAddRule() {
        let rules = cfGetCurrentRules();
        rules.push({
            condition: { field: '', operator: '==', value: '' },
            style: { color: '#000000', background: '#ffffff', bold: false, italic: false, underline: false }
        });
        cfRenderRules(rules);
    }

    function cfRemoveRule(index) {
        let rules = cfGetCurrentRules();
        rules.splice(index, 1);
        cfRenderRules(rules);
    }

    function cfGetCurrentRules() {
        try {
            const json = document.getElementById('cf-hidden-json')?.textContent;
            return json ? JSON.parse(json) : [];
        } catch(e) { return []; }
    }

    function cfSaveToElement(rules) {
        // Store in hidden JSON div
        const hidden = document.getElementById('cf-hidden-json');
        if (hidden) hidden.textContent = JSON.stringify(rules);
        
        // Also update the current element's data
        if (cfCurrentElement) {
            cfCurrentElement.conditional_format = rules.length ? rules : null;
        }
    }

    function cfEsc(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&').replace(/</g,'<').replace(/>/g,'>').replace(/"/g,'"');
    }

    // ── Conditional Style Evaluation (Canvas Preview) ───────

    function getConditionalStyle(el, data) {
        const styles = [];
        if (!el.conditional_format || !data) return styles;

        let rules = [];
        try {
            rules = typeof el.conditional_format === 'string'
                ? JSON.parse(el.conditional_format)
                : el.conditional_format;
        } catch(e) { return styles; }

        if (!Array.isArray(rules)) return styles;

        for (const rule of rules) {
            const condition = rule.condition || {};
            const fieldValue = resolveDataValue(condition.field, data);
            const compareValue = condition.value;
            const operator = condition.operator || '==';
            let match = false;

            switch (operator) {
                case '==': match = fieldValue == compareValue; break;
                case '!=': match = fieldValue != compareValue; break;
                case '>': match = parseFloat(fieldValue) > parseFloat(compareValue); break;
                case '>=': match = parseFloat(fieldValue) >= parseFloat(compareValue); break;
                case '<': match = parseFloat(fieldValue) < parseFloat(compareValue); break;
                case '<=': match = parseFloat(fieldValue) <= parseFloat(compareValue); break;
                case 'contains': match = String(fieldValue).includes(compareValue); break;
                case 'starts_with': match = String(fieldValue).startsWith(compareValue); break;
                case 'ends_with': match = String(fieldValue).endsWith(compareValue); break;
                case 'is_empty': match = fieldValue === null || fieldValue === undefined || fieldValue === ''; break;
                case 'not_empty': match = fieldValue !== null && fieldValue !== undefined && fieldValue !== ''; break;
                default: match = false;
            }

            if (match) {
                const s = rule.style || {};
                // Map new style format (background) to old format (backgroundColor) for backward compat
                styles.push({
                    color: s.color || '#000000',
                    backgroundColor: s.background || '#FFFFFF',
                    bold: s.bold || false,
                    italic: s.italic || false,
                    underline: s.underline || false,
                });
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
        
        // Save conditional formatting data from hidden JSON to active element
        const cfHidden = document.getElementById('cf-hidden-json');
        if (cfHidden && cfHidden.textContent && cfCurrentElement) {
            try {
                const rules = JSON.parse(cfHidden.textContent);
                cfCurrentElement.conditional_format = rules.length ? rules : null;
            } catch(e) {}
        }
        
        // Capture formula data from visual editor to active element
        const formulaInput = document.getElementById('fe-formula-input');
        if (formulaInput && cfCurrentElement) {
            const val = formulaInput.value.trim();
            cfCurrentElement.formula = val || null;
        }
        // Capture running total config from RT builder
        const rtConfigJson = document.getElementById('rt-config-json');
        if (rtConfigJson && rtConfigJson.textContent && cfCurrentElement) {
            try {
                const rtData = JSON.parse(rtConfigJson.textContent);
                if (rtData) {
                    Object.assign(cfCurrentElement, rtData);
                }
            } catch(e) {}
        }
        
        const allElements = flattenSections();
        const payload={name,paper_width_mm:parseFloat(document.getElementById('paper-w').value),paper_height_mm:parseFloat(document.getElementById('paper-h').value),background_image_path:document.getElementById('bg-path').value,elements:{sections:sections,elements:allElements},styles:globalStyles,background_config:backgroundConfig,parameters:templateParams,data_options:dataOptions,_token:'{{ csrf_token() }}'};
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
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
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

    // Wire up the Fetch Live Data button
    function domReadyWire() {
        wireFetchLiveBtn();
    }
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        domReadyWire();
    } else {
        document.addEventListener('DOMContentLoaded', domReadyWire);
    }

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

    // ── Parameter Editor ────────────────────────────────────
    function renderParameters() {
        const container = document.getElementById('parameters-list');
        if (!container) return;
        if (!templateParams || templateParams.length === 0) {
            container.innerHTML = '<div style="font-size:10px; color:var(--text-muted); padding:4px 0;">No parameters defined yet.</div>';
            return;
        }
        container.innerHTML = templateParams.map((p, i) => `
            <div style="display:flex; align-items:center; gap:4px; padding:6px 8px; margin-bottom:4px; background:var(--bg); border:1px solid var(--border); border-radius:6px;">
                <div style="flex:1; min-width:0;">
                    <div style="font-size:11px; font-weight:600; color:var(--text);">${escapeHtml(p.name || '')}</div>
                    <div style="font-size:10px; color:var(--text-muted);">
                        ${escapeHtml(p.label || '')}
                        <span style="color:var(--primary);">(${p.type || 'text'})</span>
                        ${p.required ? '<span style="color:var(--danger);">*</span>' : ''}
                    </div>
                </div>
                <button onclick="editParameter(${i})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:12px; padding:2px 6px;" title="Edit">✏️</button>
                <button onclick="removeParameter(${i})" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:14px; padding:2px 6px;" title="Remove">×</button>
            </div>
        `).join('');
    }

    function addParameter() {
        const nameEl = document.getElementById('param-name');
        const labelEl = document.getElementById('param-label');
        const typeEl = document.getElementById('param-type');
        const defaultEl = document.getElementById('param-default');
        const requiredEl = document.getElementById('param-required');
        const optionsEl = document.getElementById('param-options');
        const name = (nameEl.value || '').trim();
        if (!name) { showToast('⚠️ Parameter name is required.', 'warning'); return; }
        if (templateParams.some(p => p.name === name)) {
            showToast('⚠️ A parameter with this name already exists.', 'warning');
            return;
        }
        const type = typeEl.value;
        let options = [];
        if (type === 'select') {
            const raw = (optionsEl.value || '').trim();
            options = raw ? raw.split(',').map(s => s.trim()).filter(Boolean) : [];
        }
        templateParams.push({
            name,
            label: (labelEl.value || '').trim(),
            type,
            default: defaultEl.value || null,
            required: requiredEl.checked,
            options: type === 'select' ? options : undefined
        });
        nameEl.value = '';
        labelEl.value = '';
        typeEl.value = 'text';
        defaultEl.value = '';
        requiredEl.checked = false;
        optionsEl.value = '';
        document.getElementById('param-select-options-row').style.display = 'none';
        renderParameters();
        showToast('✅ Parameter added.', 'success');
    }

    function editParameter(index) {
        const p = templateParams[index];
        if (!p) return;
        document.getElementById('param-name').value = p.name || '';
        document.getElementById('param-label').value = p.label || '';
        document.getElementById('param-type').value = p.type || 'text';
        document.getElementById('param-default').value = p.default ?? '';
        document.getElementById('param-required').checked = !!p.required;
        if (p.type === 'select' && Array.isArray(p.options)) {
            document.getElementById('param-options').value = p.options.join(', ');
            document.getElementById('param-select-options-row').style.display = 'flex';
        } else {
            document.getElementById('param-options').value = '';
            document.getElementById('param-select-options-row').style.display = 'none';
        }
        templateParams.splice(index, 1);
        renderParameters();
        showToast('✏️ Edit the fields above and click "+ Add" to update.', 'info');
    }

    function removeParameter(index) {
        if (!confirm('Remove this parameter?')) return;
        templateParams.splice(index, 1);
        renderParameters();
    }

    // Show/hide options row when "select" type is chosen
    document.addEventListener('DOMContentLoaded', () => {
        const typeEl = document.getElementById('param-type');
        if (typeEl) {
            typeEl.addEventListener('change', function() {
                const row = document.getElementById('param-select-options-row');
                if (row) row.style.display = this.value === 'select' ? 'flex' : 'none';
            });
        }
    });

    // ── Visual Formula Editor ────────────────────────────────────
    let feCurrentElement = null;

    function feInit(el) {
        feCurrentElement = el;
        const container = document.getElementById('fe-container');
        if (!container) return;

        // Show container for field/label elements
        if (el.type === 'field' || el.type === 'label') {
            container.style.display = 'block';
            // Load formula from element
            const textarea = document.getElementById('fe-formula-input');
            if (textarea) {
                textarea.value = el.formula || '';
            }
            // Populate field dropdown
            fePopulateFieldDropdowns(document.getElementById('fe-field-picker'));
            // Load functions reference
            feLoadFunctions('fe-functions-list');
            // Run initial validation
            feValidateFormula(el, true);
        } else {
            container.style.display = 'none';
        }
    }

    function feSwitchTab(tabId, type) {
        const container = document.getElementById('fe-container');
        if (!container) return;
        // Deactivate all tabs and panels
        container.querySelectorAll('.fe-tab').forEach(t => t.classList.remove('active'));
        container.querySelectorAll('.fe-panel').forEach(p => p.classList.remove('active'));
        // Activate selected
        const tab = container.querySelector(`[data-fe-tab="${tabId}"]`);
        const panel = document.getElementById(`fe-panel-${tabId}`);
        if (tab) tab.classList.add('active');
        if (panel) panel.classList.add('active');
        // Load functions when switching to fn-ref tab
        if (tabId === 'fn-ref') {
            const listId = type === 'running_total' ? 'fe-functions-list-rt' : 'fe-functions-list';
            feLoadFunctions(listId);
        }
    }

    function feInsertField(el) {
        const textarea = document.getElementById('fe-formula-input');
        if (!textarea) return;
        const fieldName = prompt('Enter field name (e.g., total_amount):');
        if (fieldName && fieldName.trim()) {
            feInsertAtCursor(textarea, fieldName.trim());
            textarea.focus();
            feValidateFormula(el, true);
        }
    }

    function feInsertAtCursor(textarea, text) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const before = textarea.value.substring(0, start);
        const after = textarea.value.substring(end);
        textarea.value = before + text + after;
        // Move cursor after inserted text
        const newPos = start + text.length;
        textarea.selectionStart = textarea.selectionEnd = newPos;
        // Trigger input event
        const event = new Event('input', { bubbles: true });
        textarea.dispatchEvent(event);
    }

    function feValidateFormula(el, silent) {
        const textarea = document.getElementById('fe-formula-input');
        const validation = document.getElementById('fe-validation');
        if (!textarea || !validation) return;

        const expr = textarea.value.trim();
        if (!expr) {
            validation.textContent = 'Enter a formula expression';
            validation.className = 'fe-validation pending';
            // Update element
            if (el) el.formula = null;
            return;
        }

        // Update element's formula
        if (el) el.formula = expr;

        // Client-side pre-validation
        let hasError = false;
        let errorMsg = '';

        // Check unmatched opening braces
        const openBraces = (expr.match(/\{/g) || []).length;
        const closeBraces = (expr.match(/\}/g) || []).length;
        if (openBraces !== closeBraces) {
            hasError = true;
            errorMsg = `Unmatched braces: ${openBraces} opening vs ${closeBraces} closing`;
        }

        // Check unmatched parentheses
        let parenCount = 0;
        for (const ch of expr) {
            if (ch === '(') parenCount++;
            else if (ch === ')') parenCount--;
            if (parenCount < 0) { hasError = true; errorMsg = 'Unmatched closing parenthesis'; break; }
        }
        if (!hasError && parenCount > 0) {
            hasError = true;
            errorMsg = `Missing ${parenCount} closing parenthesis(es)`;
        }

        if (hasError) {
            validation.textContent = '❌ ' + errorMsg;
            validation.className = 'fe-validation error';
            return;
        }

        // Check for empty field references {}
        if (/\{\s*\}/.test(expr)) {
            validation.textContent = '⚠️ Empty field reference {} found';
            validation.className = 'fe-validation error';
            return;
        }

        // If silent mode and no errors, show pending
        if (silent) {
            validation.textContent = '✓ Formula syntax looks valid';
            validation.className = 'fe-validation valid';
            return;
        }

        // Server-side validation when not silent
        validation.textContent = '⏳ Validating...';
        validation.className = 'fe-validation pending';

        fetch('/api/v1/formula/validate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ expression: expr })
        })
        .then(r => r.json())
        .then(data => {
            if (data.valid) {
                validation.textContent = '✅ Formula is valid';
                validation.className = 'fe-validation valid';
            } else {
                validation.textContent = '❌ ' + (data.error || 'Invalid formula');
                validation.className = 'fe-validation error';
            }
        })
        .catch(() => {
            validation.textContent = '⚠️ Validation unavailable (offline)';
            validation.className = 'fe-validation pending';
        });
    }

    function feLoadFunctions(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        container.innerHTML = '<div style="font-size:10px;color:var(--text-muted);padding:4px;">Loading functions...</div>';

        fetch('/api/v1/formula/functions')
            .then(r => r.json())
            .then(functions => {
                if (!functions || functions.length === 0) {
                    container.innerHTML = '<div style="font-size:10px;color:var(--text-muted);padding:4px;">No functions available</div>';
                    return;
                }

                const categories = {};
                functions.forEach(f => {
                    if (!categories[f.category]) categories[f.category] = [];
                    categories[f.category].push(f);
                });

                const categoryIcons = {
                    'Math': '🔢', 'String': '📝', 'Date': '📅',
                    'Logical': '🔗', 'Conversion': '🔄', 'Other': '📦'
                };

                container.innerHTML = Object.entries(categories).map(([cat, funcs]) => `
                    <div class="fe-fn-group">
                        <div class="fe-fn-group-title">
                            ${categoryIcons[cat] || '📦'} ${cat}
                            <span style="font-weight:400;font-size:9px;">(${funcs.length})</span>
                        </div>
                        ${funcs.map(f => `
                            <div class="fe-fn-item" onclick="feInsertFunction('${f.name}', '${containerId}')" title="${(f.description||'')}">
                                <span class="fe-fn-name">${f.name}</span>
                                <span class="fe-fn-params">${f.syntax || f.params || ''}</span>
                                <span class="fe-fn-desc">${f.description || ''}</span>
                            </div>
                        `).join('')}
                    </div>
                `).join('');
            })
            .catch(err => {
                console.error('Failed to load functions:', err);
                container.innerHTML = '<div style="font-size:10px;color:var(--danger);padding:4px;">Failed to load functions</div>';
            });
    }

    function feInsertFunction(funcName, containerId) {
        // Find the formula textarea - could be in fe-container or running total container
        const textarea = document.getElementById('fe-formula-input');
        if (textarea) {
            feInsertAtCursor(textarea, funcName + '()');
            // Place cursor inside parentheses
            const openParen = textarea.value.lastIndexOf('(', textarea.selectionStart);
            if (openParen !== -1) {
                textarea.selectionStart = textarea.selectionEnd = openParen + 1;
            }
            textarea.focus();
            if (feCurrentElement) feValidateFormula(feCurrentElement, true);
        }
    }

    function fePopulateFieldDropdowns(selectEl) {
        if (!selectEl) return;
        // Get field keys
        let fields = [];
        if (typeof getAutocompleteFieldKeys === 'function') {
            fields = getAutocompleteFieldKeys();
        }

        const currentValue = selectEl.value;
        selectEl.innerHTML = '<option value="">' + (selectEl.id === 'fe-field-picker' ? '— Insert data field —' : '— Select field —') + '</option>';

        if (fields.length === 0) {
            // Try getting from schema
            if (typeof getSchemaFieldKeys === 'function') {
                fields = getSchemaFieldKeys();
            }
        }

        fields.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f;
            opt.textContent = f;
            selectEl.appendChild(opt);
        });

        if (currentValue) selectEl.value = currentValue;
    }

    function feShowPicker(el) {
        const picker = document.getElementById('fe-field-picker');
        if (!picker) return;
        // Re-populate and focus
        fePopulateFieldDropdowns(picker);
        picker.focus();
        picker.size = Math.min(picker.options.length, 10);
        picker.style.height = 'auto';
    }

    function feInsertSelectedField(el, select) {
        if (!select || !select.value) return;
        const textarea = document.getElementById('fe-formula-input');
        if (textarea) {
            feInsertAtCursor(textarea, select.value);
            textarea.focus();
            feValidateFormula(el, true);
        }
        select.value = '';
    }

    // ── Running Total Builder ────────────────────────────────────

    function rtLoadConfig(el) {
        if (!el) return;
        const container = document.getElementById('fe-container');
        if (!container) return;

        container.style.display = 'block';

        // Populate all field dropdowns in the RT builder
        const selects = container.querySelectorAll('.fe-field-dropdown');
        selects.forEach(s => fePopulateFieldDropdowns(s));

        // Set current values from element config
        const opSelect = container.querySelector('.rt-row select[onchange*="rtUpdateConfig.*operation"]');
        if (opSelect && el.operation) opSelect.value = el.operation;

        // Set field value
        const fieldSelects = container.querySelectorAll('.rt-row select.fe-field-dropdown');
        if (fieldSelects[0] && el.field) fieldSelects[0].value = el.field;

        // Set evaluate condition
        const evalEnabled = document.getElementById('rt-eval-enabled');
        if (evalEnabled) {
            evalEnabled.checked = !!(el.evaluateCondition && el.evaluateCondition.enabled);
            rtToggleEvalField(el, evalEnabled.checked);
            if (el.evaluateCondition && el.evaluateCondition.enabled) {
                const evalField = document.querySelector('#rt-eval-fields .fe-field-dropdown');
                const evalOp = document.querySelectorAll('#rt-eval-fields select')[1];
                const evalVal = document.querySelector('#rt-eval-fields input[type="text"]');
                if (evalField && el.evaluateCondition.field) evalField.value = el.evaluateCondition.field;
                if (evalOp && el.evaluateCondition.operator) evalOp.value = el.evaluateCondition.operator;
                if (evalVal && el.evaluateCondition.value) evalVal.value = el.evaluateCondition.value;
            }
        }

        // Set reset condition
        const resetEnabled = document.getElementById('rt-reset-enabled');
        if (resetEnabled) {
            resetEnabled.checked = !!(el.resetCondition && el.resetCondition.enabled);
            rtToggleResetField(el, resetEnabled.checked);
            if (el.resetCondition && el.resetCondition.enabled) {
                const resetField = document.querySelector('#rt-reset-fields .fe-field-dropdown');
                const resetOp = document.querySelectorAll('#rt-reset-fields select')[1];
                const resetVal = document.querySelector('#rt-reset-fields input[type="text"]');
                if (resetField && el.resetCondition.field) resetField.value = el.resetCondition.field;
                if (resetOp && el.resetCondition.operator) resetOp.value = el.resetCondition.operator;
                if (resetVal && el.resetCondition.value) resetVal.value = el.resetCondition.value;
            }
        }

        // Load functions reference
        feLoadFunctions('fe-functions-list-rt');

        // Update preview
        rtUpdatePreview(el);
    }

    function rtToggleEvalField(el, enabled) {
        const fields = document.getElementById('rt-eval-fields');
        if (fields) fields.style.display = enabled ? 'block' : 'none';
        if (!el.evaluateCondition) el.evaluateCondition = {};
        el.evaluateCondition.enabled = enabled;
        rtUpdatePreview(el);
    }

    function rtToggleResetField(el, enabled) {
        const fields = document.getElementById('rt-reset-fields');
        if (fields) fields.style.display = enabled ? 'block' : 'none';
        if (!el.resetCondition) el.resetCondition = {};
        el.resetCondition.enabled = enabled;
        rtUpdatePreview(el);
    }

    function rtUpdateConfig(el, prop, value) {
        if (!el) return;
        // For nested properties like evaluateCondition, resetCondition
        if (typeof prop === 'string' && (prop === 'evaluateCondition' || prop === 'resetCondition')) {
            el[prop] = value;
        } else {
            el[prop] = value;
        }
        // Update hidden JSON storage
        const jsonEl = document.getElementById('rt-config-json');
        if (jsonEl) {
            const config = {
                operation: el.operation || 'sum',
                field: el.field || '',
                evaluateCondition: el.evaluateCondition || null,
                resetCondition: el.resetCondition || null
            };
            jsonEl.textContent = JSON.stringify(config);
        }
        // Also directly update via updateElProps for saveTemplate compatibility
        if (prop === 'operation' || prop === 'field') {
            updateElProps(prop, value);
        }
    }

    function rtUpdatePreview(el) {
        const preview = document.getElementById('rt-preview');
        if (!preview || !el) return;

        const op = el.operation || 'sum';
        const field = el.field || '?';
        const opSymbol = { sum: 'Σ', count: 'COUNT', average: 'AVG', min: 'MIN', max: 'MAX' };

        let parts = [`${opSymbol[op] || op.toUpperCase()}(${field})`];

        if (el.evaluateCondition && el.evaluateCondition.enabled) {
            const cond = el.evaluateCondition;
            parts.push(` IF ${cond.field || '?'} ${cond.operator || '=='} ${cond.value || '?'}`);
        }
        if (el.resetCondition && el.resetCondition.enabled) {
            const cond = el.resetCondition;
            parts.push(` RESET ON ${cond.field || '?'} ${cond.operator || '=='} ${cond.value || '?'}`);
        }

        preview.textContent = parts.join('');
    }

    // ── Test Scenarios ─────────────────────────────────────────

    function loadScenarios() {
        if (!templateId) return;

        fetch(`/admin/templates/${templateId}/scenarios`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(scenarios => {
            renderScenarios(scenarios);
        })
        .catch(() => {});
    }

    function renderScenarios(scenarios) {
        const list = document.getElementById('scenarios-list');
        if (!list) return;

        if (!scenarios.length) {
            list.innerHTML = '<div style="font-size:11px;color:var(--text-muted);padding:4px 0;">No test scenarios. Create one to test with different data.</div>';
            return;
        }

        list.innerHTML = scenarios.map(s => `
            <div class="scenario-item" style="display:flex;align-items:center;gap:4px;padding:4px 6px;margin-bottom:4px;background:var(--bg);border-radius:4px;font-size:11px;${s.is_default ? 'border-left:3px solid var(--primary);' : ''}">
                <span style="flex:1;cursor:pointer;" onclick="useScenario(${s.id})" title="Preview with this scenario">
                    ${s.is_default ? '★ ' : ''}${escapeHtml(s.name)}
                </span>
                <span style="font-size:10px;color:var(--text-muted);">${Object.keys(s.data || {}).length} fields</span>
                <button onclick="setDefaultScenario(${s.id})" style="padding:1px 4px;border:none;background:transparent;cursor:pointer;font-size:12px;" title="Set as default">⭐</button>
                <button onclick="editScenario(${s.id})" style="padding:1px 4px;border:none;background:transparent;cursor:pointer;font-size:12px;" title="Edit data">✏️</button>
                <button onclick="deleteScenario(${s.id})" style="padding:1px 4px;border:none;background:transparent;cursor:pointer;color:#ef4444;font-size:12px;" title="Delete">✕</button>
            </div>
        `).join('');
    }

    function createScenario() {
        const input = document.getElementById('new-scenario-name');
        const name = input?.value?.trim();
        if (!name) return;

        if (!templateId) return;

        // Use the current sample data or current canvas data as the scenario data
        const data = window.currentSchemaData?.data || {};

        fetch(`/admin/templates/${templateId}/scenarios`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ name, data: JSON.stringify(data), description: '' })
        })
        .then(r => r.json())
        .then(() => {
            input.value = '';
            loadScenarios();
            showToast('Scenario "' + name + '" created', 'success');
        })
        .catch(err => showToast('Failed: ' + err.message, 'error'));
    }

    function useScenario(scenarioId) {
        // Preview with this scenario
        if (templateId) {
            window.open(`/admin/templates/${templateId}/preview?scenario_id=${scenarioId}`, '_blank');
        }
    }

    function setDefaultScenario(scenarioId) {
        if (!templateId) return;

        fetch(`/admin/templates/${templateId}/scenarios/${scenarioId}/set-default`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(() => loadScenarios())
        .catch(err => showToast('Failed: ' + err.message, 'error'));
    }

    function editScenario(scenarioId) {
        // Open a modal/dialog to edit the scenario data
        const data = prompt('Enter scenario data as JSON:');
        if (!data) return;

        if (!templateId) return;

        fetch(`/admin/templates/${templateId}/scenarios/${scenarioId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ data })
        })
        .then(r => r.json())
        .then(() => {
            loadScenarios();
            showToast('Scenario updated', 'success');
        })
        .catch(err => showToast('Failed: ' + err.message, 'error'));
    }

    function deleteScenario(scenarioId) {
        if (!confirm('Delete this test scenario?')) return;

        if (!templateId) return;

        fetch(`/admin/templates/${templateId}/scenarios/${scenarioId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(r => r.json())
        .then(() => {
            loadScenarios();
            showToast('Scenario deleted', 'success');
        })
        .catch(err => showToast('Failed: ' + err.message, 'error'));
    }
</script>
@endsection
