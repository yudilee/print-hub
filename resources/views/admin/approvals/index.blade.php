@extends('admin.layout')
@section('title', 'Pending Approvals')

@section('content')
<x-breadcrumb :items="[['label' => 'Approvals']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Authorization & Approvals</h2>
        <p class="text-xs text-slate-400">Restricted print jobs awaiting manual authorization by administrators</p>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Pending Review: <span class="text-white font-mono font-bold">{{ $pendingJobs->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Job ID</th>
                    <th class="px-5 py-3.5">Agent / Branch</th>
                    <th class="px-5 py-3.5">Target Printer</th>
                    <th class="px-5 py-3.5">Type & Ref</th>
                    <th class="px-5 py-3.5">Submitted At</th>
                    <th class="px-5 py-3.5 text-right">Decision</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($pendingJobs as $job)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $job->job_id }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="font-semibold text-white">{{ $job->agent?->name ?? '—' }}</span>
                        @if($job->agent?->branch)
                            <span class="block text-[10px] text-slate-500">{{ $job->agent->branch->name }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-300">
                        {{ $job->printer_name }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="badge badge-info">{{ strtoupper($job->type) }}</span>
                        @if($job->reference_id)
                            <span class="block text-[10px] text-slate-500 font-mono">ref: {{ $job->reference_id }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $job->created_at->format('d M H:i') }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.approvals.approve', $job->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-primary btn-sm" onclick="return confirm('Authorize and release this print job?')">
                                    Approve
                                </button>
                            </form>
                            <button type="button" class="btn-danger btn-sm" onclick="showRejectModal({{ $job->id }}, '{{ $job->job_id }}')">
                                Reject
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="✅" title="No jobs awaiting approval" description="All print queues are operating in automatic or pre-authorized mode." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($pendingJobs->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $pendingJobs->links() }}
    </div>
    @endif
</div>

{{-- Reject Modal --}}
<div id="reject-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Reject Print Job</h3>
            <button onclick="document.getElementById('reject-modal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <p class="text-xs text-slate-400 mb-4" id="reject-job-info">Job ID: —</p>

        <form method="POST" action="" id="reject-form" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Rejection Reason</label>
                <textarea name="reason" rows="3" placeholder="Provide explanation for rejection..."
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('reject-modal').classList.add('hidden')" class="btn-secondary btn-sm">Cancel</button>
                <button type="submit" class="btn-danger btn-sm">Reject & Cancel Job</button>
            </div>
        </form>
    </div>
</div>

<script>
    function showRejectModal(jobId, jobIdStr) {
        document.getElementById('reject-job-info').textContent = 'Job Identifier: ' + jobIdStr;
        document.getElementById('reject-form').action = '{{ url('/admin/approvals') }}/' + jobId + '/reject';
        document.getElementById('reject-modal').classList.remove('hidden');
    }
</script>
@endsection
