@extends('admin.layout')
@section('title', 'Scheduled Jobs')

@section('content')
<x-breadcrumb :items="[['label' => 'Scheduled & Recurring Jobs']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Scheduled & Recurring Print Automations</h2>
        <p class="text-xs text-slate-400">Automate end-of-day reports, weekly manifests, and scheduled batch jobs</p>
    </div>
    <a href="{{ route('admin.scheduled-jobs.create') }}" class="btn-primary btn-sm">
        <x-icon name="plus" size="13" />
        <span>Schedule New Job</span>
    </a>
</div>

{{-- Filters Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xs">
    <form action="{{ route('admin.scheduled-jobs.index') }}" method="GET" class="flex flex-wrap items-center gap-3">
        <select name="status" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Statuses</option>
            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>Queued</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>Failed</option>
        </select>

        <select name="recurrence" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Recurrences</option>
            <option value="none" {{ request('recurrence') === 'none' ? 'selected' : '' }}>One-Time</option>
            <option value="daily" {{ request('recurrence') === 'daily' ? 'selected' : '' }}>Daily</option>
            <option value="weekly" {{ request('recurrence') === 'weekly' ? 'selected' : '' }}>Weekly</option>
            <option value="monthly" {{ request('recurrence') === 'monthly' ? 'selected' : '' }}>Monthly</option>
        </select>

        <div class="relative flex-1 min-w-[200px]">
            <x-icon name="search" size="14" class="text-slate-500 absolute left-3 top-2.5" />
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ID or template..."
                class="w-full pl-9 pr-4 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
        </div>

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.scheduled-jobs.index') }}" class="btn-secondary btn-sm">Reset</a>
    </form>
</div>

{{-- Scheduled Jobs Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Scheduled: <span class="text-white font-mono font-bold">{{ $scheduledJobs->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Job ID</th>
                    <th class="px-5 py-3.5">Template / Ref</th>
                    <th class="px-5 py-3.5">Destination</th>
                    <th class="px-5 py-3.5">Recurrence</th>
                    <th class="px-5 py-3.5">Scheduled Execution</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($scheduledJobs as $job)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $job->job_id }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="font-bold text-white">{{ $job->template_name ?? 'Raw Document' }}</span>
                        @if($job->reference_id)
                            <span class="block text-[10px] text-slate-500 font-mono">ref: {{ $job->reference_id }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-300">
                        <span>{{ $job->agent->name ?? 'Any Online' }}</span>
                        <span class="block text-[10px] text-slate-500 font-mono">{{ $job->printer_name ?? 'Default' }}</span>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($job->recurrence && $job->recurrence !== 'none')
                            <span class="badge badge-info uppercase text-[10px]">
                                🔄 {{ $job->recurrence }}
                            </span>
                        @else
                            <span class="badge badge-info text-[10px]">One-Time</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono whitespace-nowrap">
                        {{ $job->scheduled_at ? $job->scheduled_at->format('d M Y H:i') : '—' }}
                    </td>
                    <td class="px-5 py-3.5">
                        @php
                            $badge = match($job->status) {
                                'scheduled' => 'badge-info',
                                'queued' => 'badge-warning',
                                'processing' => 'badge-info',
                                'success' => 'badge-success',
                                'failed' => 'badge-danger',
                                default => 'badge-info',
                            };
                        @endphp
                        <span class="badge {{ $badge }} uppercase text-[10px]">
                            {{ $job->status }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <form action="{{ route('admin.scheduled-jobs.destroy', $job) }}" method="POST" onsubmit="return confirm('Cancel this schedule?')" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-danger btn-sm">Cancel</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <x-empty-state icon="📅" title="No recurring jobs configured" description="Automate recurring reports or delayed document prints." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($scheduledJobs->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $scheduledJobs->links() }}
    </div>
    @endif
</div>
@endsection
