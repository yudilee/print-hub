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
        justify-content: center; cursor: pointer; transition: all 0.2s;
    }
    .tool-btn:hover { background: var(--surface-hover); border-color: var(--primary); color: var(--primary); }
    .action-btn {
        background: var(--surface-hover); border: 1px solid var(--border); color: var(--text);
        padding: 4px 12px; border-radius: 6px; font-size: 0.8rem; cursor: pointer;
    }
    .action-btn:hover { background: var(--border); border-color: var(--primary); }
    .action-group { display: flex; gap: 0.5rem; align-items: center; }
</style>

<div class="designer-container">
    <div class="designer-top-bar">
        <div class="action-group">
            <button onclick="saveTemplate()" id="save-btn" class="btn btn-primary btn-sm">Save Template</button>
            <button onclick="window.location.href='{{ route('admin.templates') }}'" class="btn btn-secondary btn-sm">Discard</button>
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <button onclick="changeZoom(-0.1)" class="action-btn">-</button>
            <span id="zoom-val" style="font-size: 0.8rem; font-weight: 500; min-width: 40px; text-align: center;">100%</span>
            <button onclick="changeZoom(0.1)" class="action-btn">+</button>
        </div>
        <div class="action-group" id="align-tools" style="display:none;">
            <button onclick="alignElements('left')" class="action-btn" title="Align Left">⇤</button>
            <button onclick="alignElements('right')" class="action-btn" title="Align Right">⇥</button>
            <button onclick="alignElements('top')" class="action-btn" title="Align Top">⤒</button>
            <button onclick="alignElements('bottom')" class="action-btn" title="Align Bottom">⤓</button>
            <div style="border-left: 1px solid var(--border); height: 20px; margin: 0 5px;"></div>
            <button onclick="groupElements()" class="action-btn" title="Group (Ctrl+G)">📦</button>
        </div>
        <div style="border-left: 1px solid var(--border); height: 20px;"></div>
        <div class="action-group">
            <span style="font-size: 0.75rem; color: var(--text-muted);">Name:</span>
            <input type="text" id="tpl-name" value="{{ $template->name }}" style="padding: 2px 8px; font-size:0.8rem; width:150px; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:4px;">
        </div>
    </div>

    <div class="designer-main">
        <div class="designer-left-toolbar">
            <button onclick="addElement('field')" class="tool-btn" title="Add Text Field">T</button>
            <button onclick="addElement('table')" class="tool-btn" title="Add Data Table">▦</button>
            <label class="tool-btn" title="Upload Background Trace" style="cursor:pointer">
                🖼️<input type="file" id="bg-upload" style="display:none" onchange="uploadBg()">
            </label>
            <div style="margin-top:auto">
                <button onclick="changeZoom(0.1, true)" class="tool-btn" title="Reset Zoom">⟃⟄</button>
            </div>
        </div>

        <div class="designer-workspace">
            <div id="ruler-top" class="ruler ruler-top"></div>
            <div id="ruler-left" class="ruler ruler-left"></div>
            
            <div id="canvas-wrapper">
                <div id="canvas">
                    <img id="canvas-bg-img" src="{{ $template->background_image_path ? asset($template->background_image_path) : '' }}" 
                         style="{{ $template->background_image_path ? '' : 'display:none' }}">
                </div>
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
                        </div>
                    </div>
                </div>
            </div>

            <div id="tab-layers" class="tab-panel">
                <div class="props-header">Layers List</div>
                <div id="layers-list" style="padding:0.5rem;"></div>
            </div>

            <div id="tab-data" class="tab-panel">
                <div class="props-header">Global Styles</div>
                <div id="styles-list" style="padding:1rem; border-bottom:1px solid var(--border);">
                    <button onclick="addStyle()" class="btn btn-secondary btn-sm" style="width:100%">+ New Style</button>
                    <div id="styles-container" style="margin-top:0.5rem;"></div>
                </div>

                <div class="props-header">Sample JSON Explorer</div>
                <div style="padding:1rem;">
                    <textarea id="json-input" placeholder="Paste Sample JSON here..." style="width:100%; height:100px; background:var(--bg); border:1px solid var(--border); color:var(--text); font-family:monospace; font-size:10px; padding:8px; border-radius:4px;"></textarea>
                    <button onclick="parseJSON()" class="btn btn-secondary btn-sm" style="width:100%; margin-top:0.5rem;">Parse JSON</button>
                    <div id="json-tree" style="margin-top:1rem; font-size:0.75rem; font-family:monospace;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE_SCALE = 4;
    let zoomLevel = 1.0;
    let elements = @json($template->elements ?? []);
    let activeId = null;
    let activeIds = [];
    let draggingEl = null, resizingEl = null, resizeHandle = null;
    let startX, startY, startW, startH, startMouseX, startMouseY;
    let globalStyles = [];

    function init() {
        elements.forEach((el, idx) => { if (!el.id) el.id = 'el_' + Date.now() + '_' + idx; });
        updateCanvasSize(); renderElements();
    }

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

    function changeZoom(delta, reset = false) {
        zoomLevel = reset ? 1.0 : Math.max(0.2, Math.min(3.0, zoomLevel + delta));
        document.getElementById('zoom-val').textContent = Math.round(zoomLevel * 100) + '%';
        updateCanvasSize(); renderElements();
    }

    function updateCanvasSize() {
        const w = parseFloat(document.getElementById('paper-w').value) || 215.9;
        const h = parseFloat(document.getElementById('paper-h').value) || 139.7;
        const c = document.getElementById('canvas');
        c.style.width = (w * BASE_SCALE) + 'px'; c.style.height = (h * BASE_SCALE) + 'px'; c.style.transform = `scale(${zoomLevel})`;
    }

    function addElement(type) {
        const id = 'el_new_' + Date.now();
        const el = { id, type, key: type==='field'?'key':'items', x:10, y:10, width:type==='table'?180:50, height:10, font_size:10, bold:false, border:false, align:'L' };
        if (type === 'table') el.columns = [{ label: 'Item', key: 'name', width: 100 }, { label: 'Qty', key: 'qty', width: 20 }];
        elements.push(el); renderElements(); selectElements([id]);
    }

    window.addEventListener('mousemove', (e) => {
        const dx = (e.clientX-startMouseX)/(BASE_SCALE*zoomLevel), dy = (e.clientY-startMouseY)/(BASE_SCALE*zoomLevel);
        if (draggingEl) {
            activeIds.forEach(id => { 
                const el = elements.find(i=>i.id===id); 
                if(el){ 
                    el.x = parseFloat((el.origX+dx).toFixed(2)); el.y = parseFloat((el.origY+dy).toFixed(2)); 
                    const div = document.querySelector(`.design-element[data-id="${el.id}"]`);
                    if (div) { div.style.left = (el.x * BASE_SCALE) + 'px'; div.style.top = (el.y * BASE_SCALE) + 'px'; }
                } 
            });
            updateInspector();
        } else if (resizingEl) {
            if (resizeHandle.includes('e')) resizingEl.width = Math.max(5, startW + dx);
            if (resizeHandle.includes('s')) resizingEl.height = Math.max(5, startH + dy);
            if (resizeHandle.includes('w')) { const nw = Math.max(5, startW-dx); if(nw>5){ resizingEl.x = startX+dx; resizingEl.width = nw; } }
            if (resizeHandle.includes('n')) { const nh = Math.max(5, startH-dy); if(nh>5){ resizingEl.y = startY+dy; resizingEl.height = nh; } }
            
            const div = document.querySelector(`.design-element[data-id="${resizingEl.id}"]`);
            if (div) {
                div.style.left = (resizingEl.x * BASE_SCALE) + 'px'; div.style.top = (resizingEl.y * BASE_SCALE) + 'px';
                div.style.width = (resizingEl.width * BASE_SCALE) + 'px'; div.style.height = (resizingEl.height * BASE_SCALE) + 'px';
            }
            updateInspector();
        }
    });

    window.addEventListener('mouseup', () => { if (draggingEl || resizingEl) renderElements(); draggingEl = null; resizingEl = null; });

    function renderElements() {
        const c = document.getElementById('canvas'); c.querySelectorAll('.design-element').forEach(el => el.remove());
        elements.forEach(el => {
            if (el.styleIdx !== undefined && globalStyles[el.styleIdx]) { 
                const s = globalStyles[el.styleIdx]; el.font_size = s.font_size; el.bold = s.bold; el.border = s.border;
            }
            const div = document.createElement('div'); div.className = 'design-element ' + (el.type === 'table' ? 'table-element' : '');
            div.setAttribute('data-id', el.id); if (activeIds.includes(el.id)) div.classList.add('active');
            div.style.left = (el.x * BASE_SCALE) + 'px'; div.style.top = (el.y * BASE_SCALE) + 'px';
            div.style.width = (el.width * BASE_SCALE) + 'px'; div.style.height = (el.height * BASE_SCALE) + 'px';
            
            if (el.border) { div.style.border = '1px solid #1e293b'; }
            
            let h = `<div class="el-inner" style="font-size:${el.font_size*0.8}px; color:#1e293b; padding:2px; height:100%; overflow:hidden; font-weight:${el.bold?'bold':'normal'}; text-align:${el.align==='C'?'center':(el.align==='R'?'right':'left')}">@{{ ${el.key} }}</div>`;
            div.innerHTML = h;

            if (activeIds.length === 1 && activeIds[0] === el.id) {
                ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].forEach(hdl => {
                    const handle = document.createElement('div');
                    handle.className = `handle res-${hdl}`;
                    handle.setAttribute('data-handle', hdl);
                    handle.style.zIndex = '999';
                    handle.onmousedown = (e) => {
                        e.stopPropagation(); e.preventDefault();
                        resizingEl = el; resizeHandle = hdl;
                        const currX = parseFloat(el.x) || 0, currY = parseFloat(el.y) || 0;
                        const currW = parseFloat(el.width) || 0, currH = parseFloat(el.height) || 0;
                        startMouseX = e.clientX; startMouseY = e.clientY; 
                        startX = currX; startY = currY; startW = currW; startH = currH;
                    };
                    div.appendChild(handle);
                });
            }
            
            div.onmousedown = (e) => {
                if (e.target.classList.contains('handle')) return;
                e.stopPropagation();
                draggingEl = el; 
                let tIds = [el.id]; if (el.groupId) tIds = elements.filter(i => i.groupId === el.groupId).map(i => i.id);
                
                if (e.shiftKey) { 
                    tIds.forEach(id => { if (activeIds.includes(id)) activeIds = activeIds.filter(aid => aid !== id); else activeIds.push(id); });
                    selectElements(activeIds);
                } else if (!activeIds.includes(el.id)) {
                    activeIds = tIds;
                    selectElements(activeIds);
                }
                activeIds.forEach(id => { 
                    const t = elements.find(x => x.id === id); 
                    if (t) { t.origX = parseFloat(t.x) || 0; t.origY = parseFloat(t.y) || 0; } 
                });
                startMouseX = e.clientX; startMouseY = e.clientY;
            };
            c.appendChild(div);
        });
        document.getElementById('align-tools').style.display = activeIds.length > 1 ? 'flex' : 'none';
        drawRulers(); updateLayersList();
    }

    function selectElements(ids) {
        activeIds = ids; activeId = ids.length === 1 ? ids[0] : null;
        renderElements(); updateInspector();
    }

    function alignElements(type) {
        if (activeIds.length < 2) return; const sel = elements.filter(el => activeIds.includes(el.id));
        if (type === 'left') { const mx = Math.min(...sel.map(e => e.x)); sel.forEach(e => e.x = mx); }
        if (type === 'right') { const mx = Math.max(...sel.map(e => e.x + e.width)); sel.forEach(e => e.x = mx - e.width); }
        if (type === 'top') { const my = Math.min(...sel.map(e => e.y)); sel.forEach(e => e.y = my); }
        if (type === 'bottom') { const my = Math.max(...sel.map(e => e.y + e.height)); sel.forEach(e => e.y = my - e.height); }
        renderElements();
    }

    function groupElements() {
        if (activeIds.length < 2) return; const gId = 'group_' + Date.now();
        elements.forEach(el => { if (activeIds.includes(el.id)) el.groupId = gId; });
        renderElements(); selectElements(activeIds);
    }

    function ungroupElements() {
        elements.forEach(el => { if (activeIds.includes(el.id)) delete el.groupId; });
        renderElements();
    }

    function addStyle() { globalStyles.push({ name: 'New Style', font_size: 10, bold: false }); renderStyles(); }
    function renderStyles() {
        const cont = document.getElementById('styles-container'); if (!cont) return; cont.innerHTML = '';
        globalStyles.forEach((s, i) => {
            const d = document.createElement('div'); d.style.padding='5px'; d.style.border='1px solid var(--border)'; d.style.marginBottom='5px';
            d.innerHTML = `<input type="text" value="${s.name}" onchange="globalStyles[${i}].name=this.value" style="background:none; border:none; color:var(--primary); font-size:11px; width:100%"><br><input type="number" value="${s.font_size}" onchange="globalStyles[${i}].font_size=parseInt(this.value); renderElements();" style="width:40px; background:none; color:white; border:1px solid var(--border); font-size:10px;"> <label style="font-size:10px"><input type="checkbox" ${s.bold?'checked':''} onchange="globalStyles[${i}].bold=this.checked; renderElements();"> B</label>`;
            cont.appendChild(d);
        });
    }

    function updateInspector() {
        const el = elements.find(e => e.id === activeId), cont = document.getElementById('inspector-content');
        if (!el && activeIds.length > 1) {
            cont.innerHTML = `<div style="text-align:center; padding:2rem 1rem;"><p style="color:var(--primary); font-weight:bold;">${activeIds.length} items</p><div style="display:grid; gap:0.5rem; margin-top:1rem;"><button onclick="groupElements()" class="btn btn-primary btn-sm">Group Selection</button><button onclick="alignElements('left')" class="btn btn-secondary btn-sm">Align Left</button><button onclick="deleteActive()" class="btn btn-danger btn-sm">Delete All</button></div></div>`;
            return;
        }
        if (!el) { cont.innerHTML = `<div style="text-align:center; padding:3rem 1rem; color:var(--text-muted); font-size:0.8rem;">Select an object</div>`; return; }

        let html = `
            <div class="props-section"><div class="props-label">Identity</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Name</div><div class="prop-val"><input type="text" value="${el.key}" oninput="updateElProps('key', this.value)"></div></div>
                <div class="prop-item"><div class="prop-key">Group</div><div class="prop-val" style="padding-left:10px;">${el.groupId ? `<span style="color:var(--primary); font-size:10px;">${el.groupId}</span> <button onclick="ungroupElements()" style="background:none; border:none; color:var(--danger); cursor:pointer;">[X]</button>` : 'None'}</div></div>
            </div></div>
            <div class="props-section"><div class="props-label">Global Style</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Link Style</div><div class="prop-val"><select onchange="updateElProps('styleIdx', this.value==='none'?undefined:parseInt(this.value))" style="color:var(--primary)"><option value="none">Manual</option>${globalStyles.map((s, i) => `<option value="${i}" ${el.styleIdx===i?'selected':''}>${s.name}</option>`).join('')}</select></div></div>
            </div></div>
            <div class="props-section"><div class="props-label">Appearance</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">FontSize</div><div class="prop-val"><input type="number" value="${el.font_size}" oninput="updateElProps('font_size', parseInt(this.value))" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">TextAlign</div><div class="prop-val"><select onchange="updateElProps('align', this.value)"><option value="L" ${el.align==='L'?'selected':''}>Left</option><option value="C" ${el.align==='C'?'selected':''}>Center</option><option value="R" ${el.align==='R'?'selected':''}>Right</option></select></div></div>
                <div class="prop-item"><div class="prop-key">Bold</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.bold?'checked':''} onchange="updateElProps('bold', this.checked)" ${el.styleIdx!==undefined?'disabled':''}></div></div>
                <div class="prop-item"><div class="prop-key">Border</div><div class="prop-val" style="padding-left:10px;"><input type="checkbox" ${el.border?'checked':''} onchange="updateElProps('border', this.checked)"></div></div>
            </div></div>
            <div class="props-section"><div class="props-label">Layout (mm)</div><div class="prop-table">
                <div class="prop-item"><div class="prop-key">Top (Y)</div><div class="prop-val"><input type="number" step="0.1" value="${el.y||0}" oninput="updateElProps('y', parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Left (X)</div><div class="prop-val"><input type="number" step="0.1" value="${el.x||0}" oninput="updateElProps('x', parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Width</div><div class="prop-val"><input type="number" step="0.1" value="${el.width||0}" oninput="updateElProps('width', parseFloat(this.value))"></div></div>
                <div class="prop-item"><div class="prop-key">Height</div><div class="prop-val"><input type="number" step="0.1" value="${el.height||0}" oninput="updateElProps('height', parseFloat(this.value))"></div></div>
            </div></div>
        `;

        if (el.type === 'table' && el.columns) {
            html += `<div class="props-section"><div class="props-label">Table Columns</div><div class="prop-table">`;
            el.columns.forEach((col, idx) => {
                html += `
                    <div class="prop-item" style="background:rgba(255,255,255,0.03); font-weight:bold;"><div class="prop-key">Col ${idx+1}</div><div class="prop-val"><button onclick="deleteCol(${idx})" style="color:var(--danger); background:none; border:none; cursor:pointer; font-size:10px;">[Delete]</button></div></div>
                    <div class="prop-item"><div class="prop-key">Label</div><div class="prop-val"><input type="text" value="${col.label}" oninput="updateCol(${idx}, 'label', this.value)"></div></div>
                    <div class="prop-item"><div class="prop-key">Key</div><div class="prop-val"><input type="text" value="${col.key}" oninput="updateCol(${idx}, 'key', this.value)"></div></div>
                    <div class="prop-item"><div class="prop-key">Width</div><div class="prop-val"><input type="number" value="${col.width}" oninput="updateCol(${idx}, 'width', parseFloat(this.value))"></div></div>
                `;
            });
            html += `</div><div style="padding:0.5rem"><button onclick="addCol()" class="btn btn-secondary btn-sm" style="width:100%">+ Add Column</button></div></div>`;
        }

        html += `
            <div style="padding:1rem;">
                <button onclick="deleteActive()" class="btn btn-danger btn-sm" style="width:100%">Delete Object</button>
            </div>
        `;
        cont.innerHTML = html;
    }

    function updateCol(idx, prop, val) {
        const el = elements.find(e => e.id === activeId);
        if (el && el.columns[idx]) { el.columns[idx][prop] = val; renderElements(); }
    }
    function addCol() {
        const el = elements.find(e => e.id === activeId);
        if (el && el.type === 'table') { if(!el.columns) el.columns=[]; el.columns.push({ label: 'New Col', key: 'key', width: 30 }); updateInspector(); }
    }
    function deleteCol(idx) {
        const el = elements.find(e => e.id === activeId);
        if (el && el.columns.length > 1) { el.columns.splice(idx, 1); updateInspector(); renderElements(); }
    }

    function updateElProps(prop, val) { const el = elements.find(e => e.id === activeId); if (el) { el[prop] = val; renderElements(); } }
    function deleteActive() { if (!confirm('Delete?')) return; elements = elements.filter(el => !activeIds.includes(el.id)); activeIds = []; activeId = null; renderElements(); updateInspector(); }

    function drawRulers() {
        const rt = document.getElementById('ruler-top'), rl = document.getElementById('ruler-left');
        const w = (document.getElementById('paper-w').value * BASE_SCALE) * zoomLevel, h = (document.getElementById('paper-h').value * BASE_SCALE) * zoomLevel;
        let tH = ''; for (let i = 0; i < (w / (BASE_SCALE * zoomLevel)); i += 10) tH += `<div style="position:absolute; left:${i*BASE_SCALE*zoomLevel}px; font-size:9px; border-left:1px solid #475569; height:10px; padding-left:2px; color:#94a3b8">${i}</div>`;
        rt.innerHTML = tH;
        let lH = ''; for (let i = 0; i < (h / (BASE_SCALE * zoomLevel)); i += 10) lH += `<div style="position:absolute; top:${i*BASE_SCALE*zoomLevel}px; font-size:9px; border-top:1px solid #475569; width:10px; padding-top:2px; color:#94a3b8">${i}</div>`;
        rl.innerHTML = lH;
    }

    function uploadBg() {
        const fI = document.getElementById('bg-upload'); if (!fI.files[0]) return;
        const fD = new FormData(); fD.append('image', fI.files[0]); fD.append('_token', '{{ csrf_token() }}');
        fetch("{{ route('admin.templates.upload-bg') }}", { method: 'POST', body: fD }).then(r => r.json()).then(data => { if (data.status === 'ok') { const img = document.getElementById('canvas-bg-img'); img.src = data.url; img.style.display='block'; document.getElementById('bg-path').value = data.url; } });
    }

    function saveTemplate() {
        const name = document.getElementById('tpl-name').value; if (!name) return alert('Name required');
        const payload = { name, paper_width_mm: parseFloat(document.getElementById('paper-w').value), paper_height_mm: parseFloat(document.getElementById('paper-h').value), background_image_path: document.getElementById('bg-path').value, elements, _token: '{{ csrf_token() }}' };
        fetch("{{ $template->id ? route('admin.templates.update', $template) : route('admin.templates.store') }}", { method: "{{ $template->id ? 'PUT' : 'POST' }}", headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) }).then(r => r.json()).then(data => { if (data.status === 'ok') window.location.href = "{{ route('admin.templates') }}"; });
    }

    window.addEventListener('keydown', (e) => {
        if (activeIds.length === 0) return; if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
        const stp = e.ctrlKey || e.metaKey ? 0.1 : 1.0; let mvd = false;
        if (e.key === 'ArrowUp') { activeIds.forEach(id => { const el = elements.find(i=>i.id===id); if(el) el.y = parseFloat((el.y-stp).toFixed(2)); }); mvd = true; }
        if (e.key === 'ArrowDown') { activeIds.forEach(id => { const el = elements.find(i=>i.id===id); if(el) el.y = parseFloat((el.y+stp).toFixed(2)); }); mvd = true; }
        if (e.key === 'ArrowLeft') { activeIds.forEach(id => { const el = elements.find(i=>i.id===id); if(el) el.x = parseFloat((el.x-stp).toFixed(2)); }); mvd = true; }
        if (e.key === 'ArrowRight') { activeIds.forEach(id => { const el = elements.find(i=>i.id===id); if(el) el.x = parseFloat((el.x+stp).toFixed(2)); }); mvd = true; }
        if (e.key === 'Delete' || (e.key === 'Backspace' && e.target.tagName !== 'INPUT')) { deleteActive(); e.preventDefault(); return; }
        if (e.ctrlKey && e.key === 'g') { e.preventDefault(); groupElements(); return; }
        if (mvd) { e.preventDefault(); renderElements(); updateInspector(); }
    });

    init();
</script>
@endsection
