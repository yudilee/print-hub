@extends('admin.layout')
@section('title', 'Notifications')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Notifications']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Notification Center</h1>
        <p>System notifications and alerts</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <form action="{{ route('admin.notifications.mark-all-read') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">Mark All as Read</button>
        </form>
        <a href="{{ route('admin.notifications') }}" class="btn btn-secondary btn-sm">All</a>
        <a href="{{ route('admin.notifications', ['unread' => 1]) }}" class="btn btn-secondary btn-sm">Unread Only</a>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Notifications ({{ $notifications->total() }})</h2>
    </div>

    @if($notifications->count() === 0)
        <x-empty-state icon="🔔" title="No notifications" description="You're all caught up! No notifications to display." />
    @else
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($notifications as $notification)
                @php
                    $icon = match($notification->type) {
                        'job_completed' => '✅',
                        'job_failed' => '❌',
                        'agent_offline' => '🔴',
                        'key_expiring' => '⚠️',
                        'approval_needed' => '📋',
                        'sla_breach' => '🚨',
                        default => '🔔',
                    };
                    $data = $notification->data ?? [];
                @endphp
                <div style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 1rem; border-radius: 8px; background: {{ $notification->isRead() ? 'transparent' : 'rgba(99, 102, 241, 0.06)' }}; border: 1px solid var(--border); {{ $notification->isRead() ? '' : 'border-left: 3px solid var(--primary);' }}">
                    <div style="font-size: 1.3rem; line-height: 1;">{{ $icon }}</div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 0.9rem;">
                            {{ $data['title'] ?? ucfirst(str_replace('_', ' ', $notification->type)) }}
                        </div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">
                            {{ $data['message'] ?? '' }}
                        </div>
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.4rem;">
                            {{ $notification->created_at->diffForHumans() }}
                            @if($notification->user_id === null)
                                <span class="badge badge-info" style="margin-left: 0.5rem;">Broadcast</span>
                            @endif
                        </div>
                    </div>
                    <div style="flex-shrink: 0;">
                        @if(!$notification->isRead())
                            <form action="{{ route('admin.notifications.mark-read', $notification) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-secondary" title="Mark as read">✓</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if($notifications->hasPages())
        <div class="pagination" style="margin-top: 1rem;">
            @if($notifications->onFirstPage())
                <span>← Prev</span>
            @else
                <a href="{{ $notifications->previousPageUrl() }}">← Prev</a>
            @endif

            @foreach($notifications->getUrlRange(1, $notifications->lastPage()) as $page => $url)
                @if($page == $notifications->currentPage())
                    <span class="active">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if($notifications->hasMorePages())
                <a href="{{ $notifications->nextPageUrl() }}">Next →</a>
            @else
                <span>Next →</span>
            @endif
        </div>
        @endif
    @endif
</div>
@endsection
