@extends('admin.layout')
@section('title', 'Activity Log')

@section('content')
<x-breadcrumb :items="[['label' => 'Activity Log']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">System Audit Trail & Activity Logs</h2>
        <p class="text-xs text-slate-400">Complete immutable record of user logins, profile changes, and job creations</p>
    </div>
    <a href="{{ route('admin.activity-logs.export', request()->query()) }}" class="btn-primary btn-sm">
        <x-icon name="download" size="13" />
        <span>Export CSV</span>
    </a>
</div>

{{-- Filters Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xs">
    <form method="GET" class="flex flex-wrap items-center gap-3">
        <select name="loggable_type" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Entities</option>
            @foreach($entityTypes as $type)
                <option value="App\Models\{{ $type }}" {{ request('loggable_type') == "App\Models\\{$type}" ? 'selected' : '' }}>
                    {{ $type }}
                </option>
            @endforeach
        </select>

        <select name="user_id" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Users</option>
            @foreach($users as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
            @endforeach
        </select>

        <select name="branch_id" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Branches</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                    {{ $branch->company->code ?? '' }} / {{ $branch->name }}
                </option>
            @endforeach
        </select>

        <div class="flex items-center gap-2">
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200">
            <span class="text-xs text-slate-500">to</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200">
        </div>

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.activity-logs') }}" class="btn-secondary btn-sm">Reset</a>
    </form>
</div>

{{-- Log Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Audit Records: <span class="text-white font-mono font-bold">{{ $logs->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Timestamp</th>
                    <th class="px-5 py-3.5">User</th>
                    <th class="px-5 py-3.5">Branch</th>
                    <th class="px-5 py-3.5">Action Executed</th>
                    <th class="px-5 py-3.5">Entity / Target</th>
                    <th class="px-5 py-3.5">Audit Summary</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono whitespace-nowrap">
                        {{ $log->created_at->format('M d, H:i:s') }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($log->user)
                            <span class="font-bold text-white">{{ $log->user->name }}</span>
                        @else
                            <span class="text-slate-500 italic">System Daemon</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($log->branch)
                            <span class="badge badge-info">{{ $log->branch->name }}</span>
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono text-xs text-blue-400">
                        {{ $log->action }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($log->subject_type)
                            <span class="badge badge-info">{{ class_basename($log->subject_type) }}</span>
                            @if($log->subject_id)
                                <span class="text-slate-400 font-mono text-[10px]">#{{ $log->subject_id }}</span>
                            @endif
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 max-w-xs truncate font-mono">
                        @if($log->properties && is_array($log->properties))
                            {{ json_encode($log->properties) }}
                        @else
                            —
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="📜" title="No activity recorded" description="Actions performed across the hub will appear here automatically." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $logs->appends(request()->query())->links() }}
    </div>
    @endif
</div>
@endsection
