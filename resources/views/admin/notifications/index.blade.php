@extends('admin.layout')
@section('title', 'Notifications')

@section('content')
<x-breadcrumb :items="[['label' => 'Notification Center']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">System Alerts & Notifications</h2>
        <p class="text-xs text-slate-400">Agent connectivity alerts, approval requests, and print SLA breaches</p>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('admin.notifications.mark-all-read') }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary btn-sm">Mark All Read</button>
        </form>
        <a href="{{ route('admin.notifications') }}" class="btn-secondary btn-sm {{ !request('unread') ? 'bg-slate-800 text-white' : '' }}">All</a>
        <a href="{{ route('admin.notifications', ['unread' => 1]) }}" class="btn-secondary btn-sm {{ request('unread') ? 'bg-slate-800 text-white' : '' }}">Unread Only</a>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Notifications: <span class="text-white font-mono font-bold">{{ $notifications->total() }}</span>
        </h3>
    </div>

    @if($notifications->count() === 0)
        <x-empty-state icon="🔔" title="No notifications pending" description="You are fully caught up with no active alerts." />
    @else
        <div class="space-y-3">
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
                <div class="p-4 rounded-xl border transition flex items-start justify-between gap-4 {{ $notification->isRead() ? 'bg-slate-950/60 border-slate-800/80 text-slate-400' : 'bg-slate-950 border-blue-500/30 text-slate-200' }}">
                    <div class="flex items-start gap-3">
                        <span class="text-xl leading-none mt-0.5">{{ $icon }}</span>
                        <div>
                            <span class="text-xs font-bold block {{ $notification->isRead() ? 'text-slate-300' : 'text-white' }}">
                                {{ $data['title'] ?? ucfirst(str_replace('_', ' ', $notification->type)) }}
                            </span>
                            <p class="text-xs text-slate-400 mt-0.5">{{ $data['message'] ?? '' }}</p>
                            <div class="flex items-center gap-2 mt-2 text-[10px] text-slate-500 font-mono">
                                <span>{{ $notification->created_at->diffForHumans() }}</span>
                                @if($notification->user_id === null)
                                    <span class="badge badge-info text-[9px] py-0 px-1.5">Broadcast</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(!$notification->isRead())
                        <form action="{{ route('admin.notifications.mark-read', $notification) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-secondary btn-sm" title="Mark Read">✓</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>

        @if($notifications->hasPages())
        <div class="p-4 border-t border-slate-800 mt-4">
            {{ $notifications->links() }}
        </div>
        @endif
    @endif
</div>
@endsection
