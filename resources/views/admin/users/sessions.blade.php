@extends('admin.layout')
@section('title', 'Active Sessions')

@section('content')
<div class="page-header">
    <h1>Active Sessions</h1>
    <p>Monitor and manage active browser sessions across the system</p>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>IP Address</th>
                <th>Device / Browser</th>
                <th>Last Activity</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sessions as $session)
            @php
                $isCurrent = $session->id === session()->getId();
            @endphp
            <tr style="{{ $isCurrent ? 'background: rgba(14, 165, 233, 0.05);' : '' }}">
                <td>
                    <b>{{ $session->user->name ?? 'Unknown' }}</b><br>
                    <small style="color:var(--text-muted)">{{ $session->user->email ?? '' }}</small>
                </td>
                <td><code class="mono">{{ $session->ip_address }}</code></td>
                <td style="max-width:300px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $session->user_agent }}">
                    {{ \Illuminate\Support\Str::limit($session->user_agent, 40) }}
                </td>
                <td>{{ \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</td>
                <td>
                    @if($isCurrent)
                        <span class="badge" style="background:#0ea5e9;color:#fff">Current</span>
                    @else
                        <span class="badge badge-success">Active</span>
                    @endif
                </td>
                <td>
                    @if(!$isCurrent)
                        <form action="{{ route('admin.sessions.destroy', $session->id) }}" method="POST" onsubmit="return confirm('Revoke this session? The user will be logged out immediately.');">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-sm" style="color:var(--danger)">Revoke</button>
                        </form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">No active sessions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
