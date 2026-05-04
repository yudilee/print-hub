@extends('admin.layout')

@section('title', 'IP Whitelist Settings')

@section('content')
<div class="page-header">
    <h1>IP Whitelist Settings</h1>
    <p>Restrict API access to specific IP addresses or CIDR ranges.</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Current Configuration</h2>
    </div>

    @php
        $whitelistRaw = config('app.api_ip_whitelist', '');
        $whitelistEntries = $whitelistRaw ? explode(',', $whitelistRaw) : [];
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
</div>

<div class="card">
    <div class="card-header">
        <h2>How to Configure</h2>
    </div>
    <div style="font-size: 0.875rem; line-height: 1.7; color: var(--text-muted);">
        <p>The IP whitelist is managed via the <code class="mono">API_IP_WHITELIST</code> environment variable in your <code class="mono">.env</code> file.</p>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Format</h3>
        <p>Provide a comma-separated list of IP addresses or CIDR ranges:</p>
        <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; margin: 0.5rem 0;">API_IP_WHITELIST=192.168.1.100,10.0.0.0/24,203.0.113.5</pre>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Examples</h3>
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
            <strong>Note:</strong> When the whitelist is empty, all IPs are allowed. Restricting access is recommended for production environments where the API should only be accessible from your internal network.
        </div>
    </div>
</div>
@endsection
