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

{{-- Session Expiry Info --}}
<div class="card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <strong>Session Expiry:</strong>
        @if(isset($sessionExpiryMinutes))
            <span class="badge badge-info">{{ $sessionExpiryMinutes }} minutes</span>
            <span style="font-size: 0.8rem; color: var(--text-muted); margin-left: 0.5rem;">
                (configured in System Settings)
            </span>
        @else
            <span class="badge badge-warning">Not configured</span>
        @endif
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <form action="{{ route('admin.sessions.force-logout-all') }}" method="POST"
              onsubmit="return confirm('This will terminate ALL sessions across the system (except your current one). Continue?');">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">🔓 Force Logout All</button>
        </form>
    </div>
</div>

<div class="card">
    <table>
        <thead>
            <tr>
                <th>User</th>
                <th>IP Address</th>
                <th>Device / Browser</th>
                <th>Platform</th>
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
                <td style="max-width:250px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $session->user_agent }}">
                    @if(!empty($session->device_type))
                        @php
                            $deviceIcons = ['desktop' => '🖥️', 'mobile' => '📱', 'tablet' => '📟'];
                        @endphp
                        <span title="{{ ucfirst($session->device_type) }}">{{ $deviceIcons[$session->device_type] ?? '🖥️' }}</span>
                    @endif
                    <span style="font-weight: 500;">{{ $session->browser ?? 'Unknown' }}</span>
                    <br>
                    <small style="color: var(--text-muted); font-size: 0.7rem;">
                        {{ \Illuminate\Support\Str::limit($session->user_agent, 60) }}
                    </small>
                </td>
                <td>
                    @if(!empty($session->platform))
                        <span class="badge badge-info">{{ $session->platform }}</span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">—</span>
                    @endif
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
                    <div style="display: flex; gap: 4px;">
                        @if(!$isCurrent)
                            <form action="{{ route('admin.sessions.destroy', $session->id) }}" method="POST" onsubmit="return confirm('Revoke this session? The user will be logged out immediately.');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm" style="color:var(--danger)">Revoke</button>
                            </form>
                        @endif
                        @if($session->user)
                            <form action="{{ route('admin.sessions.force-logout-user', $session->user_id) }}" method="POST"
                                  onsubmit="return confirm('Terminate ALL sessions for {{ $session->user->name }}?');">
                                @csrf
                                <button class="btn btn-sm btn-warning" title="Force logout all sessions for this user">🔓</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No active sessions found.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
