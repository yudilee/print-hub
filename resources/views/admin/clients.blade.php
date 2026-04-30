@extends('admin.layout')

@section('title', 'Client Apps')

@section('content')
<div style="max-width: 900px;">

    {{-- Header --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:2rem;">
        <div>
            <h1 style="font-size:1.5rem; font-weight:700;">🔑 Client Apps</h1>
            <p style="color:var(--text-muted); font-size:0.875rem; margin-top:0.25rem;">
                Register external apps that can submit print jobs via the API.
            </p>
        </div>
        <button onclick="document.getElementById('register-modal').style.display='flex'"
                class="btn btn-primary">+ Register App</button>
    </div>

    {{-- API Quick Reference --}}
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:2rem;">
        <div style="font-weight:600; font-size:0.875rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">
            📡 API Quick Reference
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; font-size:0.8rem; font-family:monospace;">
            <div style="background:var(--bg); padding:0.75rem; border-radius:8px; border:1px solid var(--border);">
                <span style="color:var(--success)">GET</span>
                <span style="color:var(--text-muted)"> /api/v1/</span><span style="color:var(--primary)">templates</span>
                <div style="color:var(--text-muted); font-size:0.75rem; margin-top:0.25rem;">List all templates + field schema</div>
            </div>
            <div style="background:var(--bg); padding:0.75rem; border-radius:8px; border:1px solid var(--border);">
                <span style="color:var(--success)">GET</span>
                <span style="color:var(--text-muted)"> /api/v1/</span><span style="color:var(--primary)">templates/{name}</span>
                <div style="color:var(--text-muted); font-size:0.75rem; margin-top:0.25rem;">Single template field detail</div>
            </div>
            <div style="background:var(--bg); padding:0.75rem; border-radius:8px; border:1px solid var(--border);">
                <span style="color:var(--warning)">POST</span>
                <span style="color:var(--text-muted)"> /api/v1/</span><span style="color:var(--primary)">print</span>
                <div style="color:var(--text-muted); font-size:0.75rem; margin-top:0.25rem;">Unified print (template or raw PDF)</div>
            </div>
            <div style="background:var(--bg); padding:0.75rem; border-radius:8px; border:1px solid var(--border);">
                <span style="color:var(--success)">GET</span>
                <span style="color:var(--text-muted)"> /api/v1/</span><span style="color:var(--primary)">jobs/{id}</span>
                <div style="color:var(--text-muted); font-size:0.75rem; margin-top:0.25rem;">Check job status</div>
            </div>
        </div>
        <div style="margin-top:1rem; padding:0.75rem; background:var(--bg); border-radius:8px; border:1px solid var(--border); font-size:0.8rem; font-family:monospace;">
            <span style="color:var(--text-muted)">Header required:</span>
            <span style="color:var(--warning)"> X-API-Key</span><span style="color:var(--text-muted)">: </span><span style="color:var(--success)">your-api-key-here</span>
        </div>
        <div style="margin-top:0.75rem;">
            <a href="{{ route('admin.clients.sdk') }}" target="_blank"
               style="color:var(--primary); font-size:0.8rem; text-decoration:none;">
                📦 Download PHP SDK (PrintHubClient.php) →
            </a>
        </div>
    </div>

    {{-- Registered Apps Table --}}
    @if($clients->isEmpty())
        <div style="text-align:center; padding:4rem; background:var(--surface); border:1px solid var(--border); border-radius:12px; color:var(--text-muted);">
            No client apps registered yet. Register your first app above.
        </div>
    @else
        <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:rgba(0,0,0,0.2); border-bottom:1px solid var(--border);">
                        <th style="padding:0.875rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">App Name</th>
                        <th style="padding:0.875rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">API Key</th>
                        <th style="padding:0.875rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Status</th>
                        <th style="padding:0.875rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Last Used</th>
                        <th style="padding:0.875rem 1rem; text-align:left; font-size:0.75rem; font-weight:600; color:var(--text-muted); text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                    <tr style="border-bottom:1px solid var(--border);">
                        <td style="padding:1rem; font-weight:500;">{{ $client->name }}</td>
                        <td style="padding:1rem;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <code style="background:var(--bg); padding:4px 8px; border-radius:6px; font-size:0.75rem; color:var(--primary); border:1px solid var(--border); max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $client->api_key }}
                                </code>
                                <button onclick="navigator.clipboard.writeText('{{ $client->api_key }}'); this.textContent='✓'; setTimeout(()=>this.textContent='📋',1500)"
                                        style="background:none; border:none; cursor:pointer; font-size:0.9rem;" title="Copy key">📋</button>
                            </div>
                        </td>
                        <td style="padding:1rem;">
                            @if($client->is_active)
                                <span style="background:rgba(34,197,94,0.15); color:var(--success); padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:500;">Active</span>
                            @else
                                <span style="background:rgba(239,68,68,0.15); color:var(--danger); padding:3px 10px; border-radius:20px; font-size:0.75rem; font-weight:500;">Revoked</span>
                            @endif
                        </td>
                        <td style="padding:1rem; color:var(--text-muted); font-size:0.85rem;">
                            {{ $client->last_used_at ? $client->last_used_at->diffForHumans() : 'Never' }}
                        </td>
                        <td style="padding:1rem;">
                            <form method="POST" action="{{ route('admin.clients.destroy', $client) }}"
                                  onsubmit="return confirm('Revoke API key for {{ $client->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Example Usage --}}
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-top:2rem;">
        <div style="font-weight:600; font-size:0.875rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:1rem;">
            🔧 Example Usage
        </div>
        <div style="display:grid; gap:1rem;">
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">Print with template (curl)</div>
                <pre style="background:var(--bg); padding:1rem; border-radius:8px; font-size:0.75rem; overflow-x:auto; color:var(--text); border:1px solid var(--border);">curl -X POST {{ url('/api/v1/print') }} \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "template": "invoice-rental",
    "data": { "customer": "PT Contoh", "total": 5000000 },
    "reference_id": "INV-2026-001"
  }'</pre>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">Print raw PDF (curl)</div>
                <pre style="background:var(--bg); padding:1rem; border-radius:8px; font-size:0.75rem; overflow-x:auto; color:var(--text); border:1px solid var(--border);">curl -X POST {{ url('/api/v1/print') }} \
  -H "X-API-Key: YOUR_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "document_base64": "JVBERi0xLjQK...",
    "reference_id": "DOC-001"
  }'</pre>
            </div>
            <div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">PHP SDK usage</div>
                <pre style="background:var(--bg); padding:1rem; border-radius:8px; font-size:0.75rem; overflow-x:auto; color:var(--text); border:1px solid var(--border);">$hub = new PrintHubClient('{{ url('/') }}', 'YOUR_KEY');

// Print with template
$result = $hub->printWithTemplate('invoice-rental', $data, 'INV-001');

// List available templates
$templates = $hub->getTemplates();

// Check job status
$status = $hub->jobStatus($result['job_id']);</pre>
            </div>
        </div>
    </div>
</div>

{{-- Register Modal --}}
<div id="register-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:2rem; width:400px; max-width:90vw;">
        <h2 style="font-size:1.1rem; font-weight:600; margin-bottom:0.5rem;">Register Client App</h2>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.5rem;">An API key will be auto-generated.</p>
        <form method="POST" action="{{ route('admin.clients.store') }}">
            @csrf
            <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.5rem;">App Name</label>
                <input type="text" name="name" placeholder="e.g. Invoice Printer App" required
                       style="width:100%; padding:0.75rem; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:8px; font-size:0.875rem;">
            </div>
            <div style="margin-bottom:1rem;">
                <label style="display:block; font-size:0.85rem; font-weight:500; margin-bottom:0.5rem;">Allowed Origins (CORS) <span style="font-weight:normal; color:var(--text-muted); font-size:0.75rem;">Optional</span></label>
                <input type="text" name="allowed_origins" placeholder="e.g. https://app.example.com, http://localhost:8080"
                       style="width:100%; padding:0.75rem; background:var(--bg); border:1px solid var(--border); color:var(--text); border-radius:8px; font-size:0.875rem;">
                <div style="color:var(--text-muted); font-size:0.75rem; margin-top:0.3rem;">Comma separated list of URLs that can print directly to Trayprint via this app.</div>
            </div>
            <div style="display:flex; gap:0.75rem; justify-content:flex-end; margin-top:1.5rem;">
                <button type="button" onclick="document.getElementById('register-modal').style.display='none'"
                        style="padding:0.6rem 1.25rem; background:transparent; border:1px solid var(--border); color:var(--text); border-radius:8px; cursor:pointer;">Cancel</button>
                <button type="submit" class="btn btn-primary">Register</button>
            </div>
        </form>
    </div>
</div>
@endsection
