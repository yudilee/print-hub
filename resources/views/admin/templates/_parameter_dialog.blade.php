<div style="background:var(--surface); border-radius:12px; max-width:480px; margin:0 auto; overflow:hidden; border:1px solid var(--border);">
    <div style="padding:16px 20px; border-bottom:1px solid var(--border); background:rgba(0,0,0,0.1);">
        <h3 style="margin:0; font-size:15px; color:var(--text); display:flex; align-items:center; gap:8px;">
            ⚙️ Runtime Parameters
        </h3>
        <p style="margin:4px 0 0; font-size:11px; color:var(--text-muted);">
            This template requires input parameters before previewing.
        </p>
    </div>
    <div style="padding:16px 20px;">
        @foreach($parameters as $param)
            @php
                $name  = $param['name'] ?? '';
                $label = $param['label'] ?? ($name ? ucfirst(str_replace('_', ' ', $name)) : '');
                $type  = $param['type'] ?? 'text';
                $default = $param['default'] ?? '';
                $required = !empty($param['required']);
                $options = $param['options'] ?? [];
                $inputId = 'param-' . $name;
            @endphp
            <div style="margin-bottom:14px;">
                <label for="{{ $inputId }}" style="display:block; font-size:12px; font-weight:600; color:var(--text); margin-bottom:4px;">
                    {{ $label }}
                    @if($required)
                        <span style="color:var(--danger);">*</span>
                    @endif
                </label>
                @if($type === 'boolean')
                    <select id="{{ $inputId }}" class="param-input" data-name="{{ $name }}" style="width:100%; padding:8px 10px; font-size:13px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text);">
                        <option value="0" {{ $default ? '' : 'selected' }}>No</option>
                        <option value="1" {{ $default ? 'selected' : '' }}>Yes</option>
                    </select>
                @elseif($type === 'select' && !empty($options))
                    <select id="{{ $inputId }}" class="param-input" data-name="{{ $name }}" style="width:100%; padding:8px 10px; font-size:13px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text);">
                        @foreach($options as $opt)
                            <option value="{{ $opt }}" {{ (string)$default === (string)$opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                @elseif($type === 'date')
                    <input type="date" id="{{ $inputId }}" class="param-input" data-name="{{ $name }}"
                           value="{{ $default ? date('Y-m-d', strtotime($default)) : '' }}"
                           style="width:100%; padding:8px 10px; font-size:13px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); box-sizing:border-box;">
                @elseif($type === 'number')
                    <input type="number" id="{{ $inputId }}" class="param-input" data-name="{{ $name }}"
                           value="{{ $default }}" placeholder="{{ $label }}"
                           @if($required) required @endif
                           style="width:100%; padding:8px 10px; font-size:13px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); box-sizing:border-box;">
                @else
                    <input type="text" id="{{ $inputId }}" class="param-input" data-name="{{ $name }}"
                           value="{{ $default }}" placeholder="{{ $label }}"
                           @if($required) required @endif
                           style="width:100%; padding:8px 10px; font-size:13px; background:var(--bg); border:1px solid var(--border); border-radius:6px; color:var(--text); box-sizing:border-box;">
                @endif
            </div>
        @endforeach
    </div>
    <div style="padding:12px 20px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:8px; background:rgba(0,0,0,0.05);">
        <button onclick="document.getElementById('parameter-dialog').style.display='none'" style="padding:8px 16px; background:var(--surface-hover); border:1px solid var(--border); border-radius:6px; color:var(--text); font-size:12px; cursor:pointer;">Cancel</button>
        <button onclick="submitParameters()" style="padding:8px 20px; background:var(--primary); border:none; border-radius:6px; color:#fff; font-size:12px; font-weight:600; cursor:pointer;">Preview</button>
    </div>
</div>
