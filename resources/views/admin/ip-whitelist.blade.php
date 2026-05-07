@extends('admin.layout')

@section('title', 'IP Whitelist Settings')

@section('content')
<div class="page-header">
    <h1>IP Whitelist Settings</h1>
    <p>Restrict API access to specific IP addresses or CIDR ranges at global, client app, and agent levels.</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Global IP Whitelist --}}
<div class="card">
    <div class="card-header">
        <h2>🌐 Global IP Whitelist</h2>
        <span class="badge badge-info">Applies to all API routes</span>
    </div>

    @php
        $whitelistEntries = $globalWhitelist ?? [];
    @endphp

    <table>
        <thead>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Status</td>
                <td>
                    @if(!empty($whitelistEntries))
                        <span class="badge badge-success">Restricted</span>
                        <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.5rem;">
                            ({{ count($whitelistEntries) }} entr{{ count($whitelistEntries) === 1 ? 'y' : 'ies' }})
                        </span>
                    @else
                        <span class="badge badge-warning">Open (All IPs Allowed)</span>
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    @if(!empty($whitelistEntries))
        <h3 style="margin: 1.5rem 0 0.75rem; font-size: 0.9rem; font-weight: 600;">Allowed IPs / CIDR Ranges</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Entry</th>
                    <th>Type</th>
                </tr>
            </thead>
            <tbody>
                @foreach($whitelistEntries as $index => $entry)
                    @php
                        $entry = trim($entry);
                        $type = str_contains($entry, '/') ? 'CIDR Range' : (filter_var($entry, FILTER_VALIDATE_IP) ? 'IP Address' : 'Invalid');
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td><span class="mono">{{ $entry }}</span></td>
                        <td><span class="badge badge-info">{{ $type }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
        <p>The global whitelist is managed via the <code class="mono">API_IP_WHITELIST</code> environment variable in your <code class="mono">.env</code> file.</p>
        <pre style="background: var(--bg); padding: 0.75rem; border-radius: 6px; overflow-x: auto; font-size: 0.75rem; margin: 0.5rem 0;">API_IP_WHITELIST=192.168.1.100,10.0.0.0/24,203.0.113.5</pre>
    </div>
</div>

{{-- Per-Client-App IP Whitelists --}}
<div class="card">
    <div class="card-header">
        <h2>🔑 Per-Client-App IP Whitelists</h2>
        <span class="badge badge-info">Restrict which IPs can use each API key</span>
    </div>

    @if($clientApps->isEmpty())
        <x-empty-state icon="🔌" title="No client apps registered" description="Register client apps first to manage their IP whitelists." />
    @else
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            @foreach($clientApps as $clientApp)
                @php
                    $appIps = is_array($clientApp->allowed_ips) ? $clientApp->allowed_ips : [];
                @endphp
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1rem; background: var(--bg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <div>
                            <strong style="font-size: 0.9rem;">{{ $clientApp->name }}</strong>
                            @if(!empty($appIps))
                                <span class="badge badge-success" style="margin-left: 0.5rem;">{{ count($appIps) }} IP{{ count($appIps) !== 1 ? 's' : '' }}</span>
                            @else
                                <span class="badge badge-warning" style="margin-left: 0.5rem;">All IPs Allowed</span>
                            @endif
                        </div>
                    </div>

                    @if(!empty($appIps))
                        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 0.75rem;">
                            @foreach($appIps as $ip)
                                <code class="mono" style="font-size: 0.7rem;">{{ $ip }}</code>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.ip-whitelist.client-app', $clientApp) }}">
                        @csrf
                        <div class="form-group">
                            <label for="client_ips_{{ $clientApp->id }}" style="font-size: 0.75rem; color: var(--text-muted);">
                                Allowed IPs (one per line — supports CIDR notation)
                            </label>
                            <textarea name="allowed_ips" id="client_ips_{{ $clientApp->id }}" rows="3"
                                      style="width: 100%; font-family: monospace; font-size: 0.8rem; padding: 0.5rem; background: var(--surface); border: 1px solid var(--border); color: var(--text); border-radius: 6px;"
                                      placeholder="192.168.1.100&#10;10.0.0.0/24&#10;203.0.113.5">{{ implode("\n", $appIps) }}</textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button type="submit" class="btn btn-primary btn-sm">💾 Save</button>
                            @if(!empty($appIps))
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="clearIps('client', {{ $clientApp->id }})">🗑️ Clear All</button>
                            @endif
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Per-Agent IP Whitelists --}}
<div class="card">
    <div class="card-header">
        <h2>🖥️ Per-Agent IP Whitelists</h2>
        <span class="badge badge-info">Restrict which IPs each agent can connect from</span>
    </div>

    @if($agents->isEmpty())
        <x-empty-state icon="🖥️" title="No agents registered" description="Register agents first to manage their IP whitelists." />
    @else
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            @foreach($agents as $agent)
                @php
                    $agentIps = $agent->allowed_ips ? explode("\n", $agent->allowed_ips) : [];
                    $agentIps = array_filter($agentIps, fn($ip) => !empty(trim($ip)));
                @endphp
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1rem; background: var(--bg);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <div>
                            <strong style="font-size: 0.9rem;">{{ $agent->name }}</strong>
                            @if($agent->ip_address)
                                <code class="mono" style="font-size: 0.7rem; margin-left: 0.5rem;">Current: {{ $agent->ip_address }}</code>
                            @endif
                            @if(!empty($agentIps))
                                <span class="badge badge-success" style="margin-left: 0.5rem;">{{ count($agentIps) }} IP{{ count($agentIps) !== 1 ? 's' : '' }}</span>
                            @else
                                <span class="badge badge-warning" style="margin-left: 0.5rem;">All IPs Allowed</span>
                            @endif
                        </div>
                    </div>

                    @if(!empty($agentIps))
                        <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 0.75rem;">
                            @foreach($agentIps as $ip)
                                <code class="mono" style="font-size: 0.7rem;">{{ trim($ip) }}</code>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.ip-whitelist.agent', $agent) }}">
                        @csrf
                        <div class="form-group">
                            <label for="agent_ips_{{ $agent->id }}" style="font-size: 0.75rem; color: var(--text-muted);">
                                Allowed IPs (one per line — supports CIDR notation)
                            </label>
                            <textarea name="allowed_ips" id="agent_ips_{{ $agent->id }}" rows="3"
                                      style="width: 100%; font-family: monospace; font-size: 0.8rem; padding: 0.5rem; background: var(--surface); border: 1px solid var(--border); color: var(--text); border-radius: 6px;"
                                      placeholder="192.168.1.100&#10;10.0.0.0/24">{{ implode("\n", $agentIps) }}</textarea>
                        </div>
                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                            <button type="submit" class="btn btn-primary btn-sm">💾 Save</button>
                            @if(!empty($agentIps))
                                <button type="button" class="btn btn-danger btn-sm"
                                        onclick="clearIps('agent', {{ $agent->id }})">🗑️ Clear All</button>
                            @endif
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- How to Configure --}}
<div class="card">
    <div class="card-header">
        <h2>📖 How to Configure</h2>
    </div>
    <div style="font-size: 0.875rem; line-height: 1.7; color: var(--text-muted);">
        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Whitelist Hierarchy</h3>
        <p>IP restrictions are evaluated in this order:</p>
        <ol style="margin-left: 1.5rem; margin-bottom: 1rem;">
            <li><strong>Global whitelist</strong> — Applies to all API routes via <code class="mono">API_IP_WHITELIST</code> env var</li>
            <li><strong>Per-client-app whitelist</strong> — Restricts which IPs can use a specific API key</li>
            <li><strong>Per-agent whitelist</strong> — Restricts which IPs a print agent can connect from</li>
        </ol>
        <p>If a whitelist is empty, all IPs are allowed at that level. All levels must pass for access to be granted.</p>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">CIDR Notation Examples</h3>
        <table>
            <thead>
                <tr>
                    <th>Entry</th>
                    <th>Matches</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="mono">192.168.1.100</span></td>
                    <td>Single IP address</td>
                </tr>
                <tr>
                    <td><span class="mono">10.0.0.0/24</span></td>
                    <td>Range 10.0.0.0 – 10.0.0.255</td>
                </tr>
                <tr>
                    <td><span class="mono">172.16.0.0/12</span></td>
                    <td>Range 172.16.0.0 – 172.31.255.255</td>
                </tr>
            </tbody>
        </table>

        <div class="alert alert-info" style="margin-top: 1rem; background: rgba(59, 130, 246, 0.1); color: var(--info); border: 1px solid rgba(59, 130, 246, 0.2); padding: 0.75rem 1rem; border-radius: 6px;">
            <strong>Note:</strong> When the global whitelist is empty, all IPs are allowed. Restricting access is recommended for production environments where the API should only be accessible from your internal network.
        </div>
    </div>
</div>

<script>
function clearIps(type, id) {
    if (!confirm('Clear all IP entries for this ' + type + '?')) return;
    const textareaId = type === 'client' ? 'client_ips_' : 'agent_ips_';
    document.getElementById(textareaId + id).value = '';
    // Submit the parent form
    document.getElementById(textareaId + id).closest('form').submit();
}
</script>
@endsection
