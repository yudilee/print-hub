@extends('admin.layout')
@section('title', 'Agents')

@section('content')
<div class="page-header">
    <h1>Print Agents</h1>
    <p>Manage the tray applications installed on each workstation</p>
</div>

{{-- Add Agent Form --}}
<div class="card">
    <div class="card-header">
        <h2>Register New Agent</h2>
    </div>
    <form action="{{ route('admin.agents.store') }}" method="POST">
        @csrf
        <div class="form-inline">
            <div class="form-group" style="flex:1; margin-bottom:0;">
                <label for="name">Agent Name (e.g. "PC Front Office")</label>
                <input type="text" name="name" id="name" required placeholder="Enter a name for this workstation">
            </div>
            <button type="submit" class="btn btn-primary" style="margin-bottom:0;">+ Add Agent</button>
        </div>
    </form>
</div>

{{-- Agent List --}}
<div class="card">
    <div class="card-header">
        <h2>All Agents ({{ $agents->count() }})</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Agent Key</th>
                <th>Status</th>
                <th>IP Address</th>
                <th>Last Seen</th>
                <th>Jobs</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($agents as $agent)
            <tr>
                <td><strong>{{ $agent->name }}</strong></td>
                <td><code class="mono">{{ $agent->agent_key }}</code></td>
                <td>
                    @if($agent->isOnline())
                        <span class="dot dot-green"></span><span class="badge badge-success">Online</span>
                    @else
                        <span class="dot dot-red"></span><span class="badge badge-danger">Offline</span>
                    @endif
                </td>
                <td style="color: var(--text-muted); font-size: 0.8rem;">{{ $agent->ip_address ?? '—' }}</td>
                <td style="color: var(--text-muted); font-size: 0.8rem;">{{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}</td>
                <td>{{ $agent->jobs_count }}</td>
                <td>
                    <form action="{{ route('admin.agents.destroy', $agent) }}" method="POST" onsubmit="return confirm('Remove this agent?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm">Remove</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="color: var(--text-muted);">No agents registered yet. Add one above!</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($agents->count() > 0)
<div class="card">
    <div class="card-header"><h2>Quick Setup Guide</h2></div>
    <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.6;">
        On the target workstation, open <code class="mono">config.json</code> inside the Trayprint folder and paste:
    </p>
    <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; margin-top: 0.75rem; font-size: 0.8rem; overflow-x: auto; color: var(--text);">{
  "hub_url": "{{ url('/') }}",
  "agent_key": "<span style='color: var(--warning);'>PASTE_AGENT_KEY_HERE</span>"
}</pre>
    <p style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.75rem;">
        Then restart the Trayprint application. It will automatically sync profiles and report jobs.
    </p>
</div>
@endif
@endsection
