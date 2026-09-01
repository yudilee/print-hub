@extends('admin.layout')
@section('title', 'Job History')

@section('content')
<x-breadcrumb :items="[['label' => 'Jobs', 'url' => route('admin.jobs')]]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Print Job Telemetry & History</h2>
        <p class="text-xs text-slate-400">All print tasks dispatched and reported by agents across the network</p>
    </div>
    <div class="flex items-center gap-2">
        <form action="{{ route('admin.jobs.retry-all-failed') }}" method="POST" onsubmit="return confirm('Retry ALL failed jobs? This will re-queue every failed job.')" class="inline">
            @csrf
            <button type="submit" class="btn-warning btn-sm" title="Re-queue all failed jobs">
                <x-icon name="refresh-cw" size="13" />
                <span>Retry All Failed</span>
            </button>
        </form>
        <a href="{{ route('admin.jobs.export', request()->query()) }}" class="btn-primary btn-sm">
            <x-icon name="download" size="13" />
            <span>Export CSV</span>
        </a>
    </div>
</div>

{{-- Filters Bar --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 space-y-3 shadow-xs">
    <form action="{{ route('admin.jobs') }}" method="GET" class="flex flex-wrap items-center gap-3">
        <select name="status" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Statuses</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>✓ Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>✗ Failed</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Pending</option>
            <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>🔄 Processing</option>
            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>📋 Queued</option>
            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>📅 Scheduled</option>
        </select>

        <select name="priority" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Priorities</option>
            <option value="1" {{ request('priority') == 1 ? 'selected' : '' }}>Low Priority</option>
            <option value="2" {{ request('priority') == 2 ? 'selected' : '' }}>Normal Priority</option>
            <option value="3" {{ request('priority') == 3 ? 'selected' : '' }}>High Priority</option>
            <option value="4" {{ request('priority') == 4 ? 'selected' : '' }}>Urgent</option>
        </select>

        <select name="agent_id" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
            @endforeach
        </select>

        <select name="branch_id" class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <option value="">All Branches</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>

        <div class="flex items-center gap-2">
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            <span class="text-xs text-slate-500">to</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                class="bg-slate-950 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
        </div>

        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.jobs') }}" class="btn-secondary btn-sm">Reset</a>

        <div class="ml-auto flex items-center gap-2">
            <label for="per_page" class="text-xs text-slate-400">Rows:</label>
            <select name="per_page" id="per_page" onchange="this.form.submit()" class="bg-slate-950 border border-slate-800 rounded-xl px-2.5 py-1 text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                <option value="10" {{ ($perPage ?? 25) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ ($perPage ?? 25) == 100 ? 'selected' : '' }}>100</option>
            </select>
        </div>
    </form>
</div>

<!-- Jobs Data Table Card -->
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs" x-data="{ expanded: null, selectedJobs: [], selectAll: false }">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Jobs Record: <span class="text-white font-mono font-bold">{{ number_format($jobs->total()) }}</span>
        </h3>
        
        <div x-show="selectedJobs.length > 0" x-cloak class="flex items-center gap-2">
            <span class="text-xs text-slate-400" x-text="selectedJobs.length + ' selected'"></span>
            <form action="{{ route('admin.jobs.bulk-retry') }}" method="POST" class="inline" @submit.prevent="if(confirm('Retry ' + selectedJobs.length + ' selected job(s)?')) $el.submit()">
                @csrf
                <template x-for="jobId in selectedJobs" :key="jobId">
                    <input type="hidden" name="job_ids[]" :value="jobId">
                </template>
                <button type="submit" class="btn-warning btn-sm" x-text="'🔄 Retry Selected (' + selectedJobs.length + ')'"></button>
            </form>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5 w-10">
                        <input type="checkbox" x-model="selectAll" @change="selectedJobs = selectAll ? {{ $jobs->pluck('job_id')->map(fn($id) => "'$id'")->join(',') }} : []" class="rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-0">
                    </th>
                    <th class="px-5 py-3.5">Job ID</th>
                    <th class="px-5 py-3.5">Agent / Location</th>
                    <th class="px-5 py-3.5">Target Printer</th>
                    <th class="px-5 py-3.5">Type & Doc</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Timestamp</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($jobs as $job)
                <tr id="job-row-{{ $job->job_id }}" class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <input type="checkbox" value="{{ $job->job_id }}" x-model="selectedJobs" class="rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-0">
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="font-mono font-bold text-blue-400 text-xs">{{ $job->job_id }}</span>
                        @if($job->reference_id)
                            <span class="block text-[10px] text-slate-500 font-mono">ref: {{ $job->reference_id }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="font-semibold text-white text-xs">{{ $job->agent->name ?? 'Unassigned' }}</span>
                        @if($job->agent?->branch)
                            <span class="block text-[10px] text-slate-500">{{ $job->agent->branch->name }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-300">
                        {{ $job->printer_name ?? 'Default Pool Printer' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info">{{ strtoupper($job->type) }}</span>
                        @if($job->file_path)
                            <a href="{{ route('admin.jobs.download', $job) }}" class="block text-[10px] text-blue-400 hover:underline mt-0.5" target="_blank">View PDF</a>
                        @endif
                    </td>
                    <td class="px-5 py-3.5" id="job-status-{{ $job->job_id }}">
                        @if($job->status === 'success')
                            <span class="badge badge-success"><span class="dot dot-green"></span> Success</span>
                        @elseif($job->status === 'failed')
                            <span class="badge badge-danger"><span class="dot dot-red"></span> Failed</span>
                            <form action="{{ route('admin.jobs.retry', $job) }}" method="POST" class="inline ml-1" onsubmit="return confirm('Retry this job?')">
                                @csrf
                                <button type="submit" class="text-[10px] text-blue-400 hover:underline">Retry</button>
                            </form>
                        @elseif($job->status === 'scheduled' || $job->status === 'queued')
                            <span class="badge badge-info">{{ ucfirst($job->status) }}</span>
                        @else
                            <span class="badge badge-warning">{{ ucfirst($job->status) }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $job->created_at->format('M j, H:i') }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button type="button" @click="expanded = expanded === '{{ $job->job_id }}' ? null : '{{ $job->job_id }}'"
                                class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold transition">
                                <span x-text="expanded === '{{ $job->job_id }}' ? 'Hide' : 'Info'">Info</span>
                            </button>
                            <button type="button" onclick="openDependencyModal('{{ $job->job_id }}')"
                                class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-[11px] font-semibold transition" title="Dependencies">
                                Deps
                            </button>
                        </div>
                    </td>
                </tr>

                {{-- Expanded Metadata Drawer --}}
                <tr x-show="expanded === '{{ $job->job_id }}'" x-cloak class="bg-slate-950/60">
                    <td colspan="8" class="px-6 py-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-1.5">
                                <div class="font-bold text-white pb-1 border-b border-slate-800">Job Metadata & Options</div>
                                <div><span class="text-slate-500">Template:</span> <span class="text-slate-200">{{ $job->template_name ?? '—' }}</span></div>
                                <div><span class="text-slate-500">Copies:</span> <span class="text-slate-200">{{ $job->options['copies'] ?? 1 }}</span></div>
                                <div><span class="text-slate-500">Priority:</span> <span class="text-slate-200">{{ $job->priority ?? '2 (Normal)' }}</span></div>
                                <div><span class="text-slate-500">Duplex:</span> <span class="text-slate-200">{{ $job->options['duplex'] ?? 'None' }}</span></div>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-900 border border-slate-800 space-y-1.5">
                                <div class="font-bold text-white pb-1 border-b border-slate-800">Timestamps & Callbacks</div>
                                <div><span class="text-slate-500">Created:</span> <span class="font-mono text-slate-300">{{ $job->created_at->format('Y-m-d H:i:s') }}</span></div>
                                <div><span class="text-slate-500">Dispatched:</span> <span class="font-mono text-slate-300">{{ $job->dispatched_at ? $job->dispatched_at->format('Y-m-d H:i:s') : '—' }}</span></div>
                                <div><span class="text-slate-500">Completed:</span> <span class="font-mono text-slate-300">{{ $job->agent_completed_at ? $job->agent_completed_at->format('Y-m-d H:i:s') : '—' }}</span></div>
                                @if($job->webhook_url)
                                <div><span class="text-slate-500">Webhook:</span> <span class="font-mono text-blue-400 truncate block text-[10px]">{{ $job->webhook_url }}</span></div>
                                @endif
                                @if($job->error)
                                <div class="mt-2 p-2 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 font-mono text-[11px]">
                                    {{ $job->error }}
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Visual Pipeline Waterfall Trace --}}
                        <div class="mt-4 p-3.5 rounded-xl bg-slate-900 border border-slate-800">
                            <div class="font-bold text-white pb-2 mb-3 border-b border-slate-800 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                                    <span>Pipeline Execution Trace</span>
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">UUID: {{ $job->job_id }}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5 text-center text-xs">
                                {{-- Step 1: Ingestion --}}
                                <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 flex flex-col items-center">
                                    <div class="w-6 h-6 rounded-full bg-blue-500/20 text-blue-400 font-bold flex items-center justify-center text-[10px] mb-1">1</div>
                                    <div class="font-semibold text-slate-200 text-[11px]">API Ingestion</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $job->created_at->format('H:i:s') }}</div>
                                    <span class="badge badge-success text-[9px] mt-1.5">Validated</span>
                                </div>

                                {{-- Step 2: Render & Orchestration --}}
                                <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 flex flex-col items-center">
                                    <div class="w-6 h-6 rounded-full bg-indigo-500/20 text-indigo-400 font-bold flex items-center justify-center text-[10px] mb-1">2</div>
                                    <div class="font-semibold text-slate-200 text-[11px]">Document Render</div>
                                    <div class="text-[10px] text-slate-500 mt-0.5">{{ strtoupper($job->type) }} Document</div>
                                    <span class="badge badge-success text-[9px] mt-1.5">Ready</span>
                                </div>

                                {{-- Step 3: Workstation Dispatch --}}
                                <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 flex flex-col items-center">
                                    <div class="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-bold flex items-center justify-center text-[10px] mb-1">3</div>
                                    <div class="font-semibold text-slate-200 text-[11px]">Agent Workstation</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[120px]" title="{{ $job->agent->name ?? 'Unassigned' }}">{{ $job->agent->name ?? 'Unassigned' }}</div>
                                    @if($job->status === 'success')
                                        <span class="badge badge-success text-[9px] mt-1.5">Dispatched</span>
                                    @elseif($job->dispatched_at)
                                        <span class="badge badge-info text-[9px] mt-1.5">Leased</span>
                                    @else
                                        <span class="badge badge-warning text-[9px] mt-1.5">Queued</span>
                                    @endif
                                </div>

                                {{-- Step 4: Spooler Execution --}}
                                <div class="p-2.5 rounded-lg bg-slate-950 border border-slate-800 flex flex-col items-center">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400 font-bold flex items-center justify-center text-[10px] mb-1">4</div>
                                    <div class="font-semibold text-slate-200 text-[11px]">Physical Spool</div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-[120px]" title="{{ $job->printer_name ?? 'Default' }}">{{ $job->printer_name ?? 'Default' }}</div>
                                    @if($job->status === 'success')
                                        <span class="badge badge-success text-[9px] mt-1.5">Printed</span>
                                    @elseif($job->status === 'failed')
                                        <span class="badge badge-danger text-[9px] mt-1.5">Spool Failed</span>
                                    @else
                                        <span class="badge badge-secondary text-[9px] mt-1.5">Pending</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state icon="📋" title="No print jobs found" description="Jobs will appear here as soon as documents or test prints are queued." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($jobs->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $jobs->links() }}
    </div>
    @endif
</div>

{{-- Dependency Detail Modal --}}
<div id="dependency-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <div>
                <h3 class="text-base font-bold text-white">Job Dependencies & Sequence</h3>
                <p class="text-xs text-slate-400">Upstream and downstream print execution chains</p>
            </div>
            <button onclick="closeDependencyModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <div id="dependency-loading" class="text-center py-8 text-xs text-slate-400">Loading dependencies...</div>

        <div id="dependency-content" class="space-y-4 hidden text-xs">
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                <div>
                    <span class="text-slate-500 block text-[10px] uppercase font-bold tracking-wider mb-0.5">Current Job</span>
                    <span class="font-mono font-bold text-blue-400 text-xs" id="dep-job-id"></span>
                </div>
                <span id="dep-job-status" class="badge"></span>
            </div>

            {{-- Upstream / Parent Dependency --}}
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                <h4 class="font-bold text-amber-400 text-xs uppercase tracking-wider flex items-center gap-1.5">
                    <span>⬆ Parent / Prerequisite Job</span>
                </h4>
                <div id="dep-parent-container" class="text-slate-300"></div>
            </div>

            {{-- Downstream / Dependent Jobs --}}
            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                <h4 class="font-bold text-emerald-400 text-xs uppercase tracking-wider flex items-center gap-1.5">
                    <span>⬇ Dependent / Child Jobs</span>
                </h4>
                <div id="dep-children-container" class="text-slate-300"></div>
            </div>

            <div class="pt-3 border-t border-slate-800 flex justify-end">
                <button type="button" class="btn-secondary btn-sm" onclick="closeDependencyModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.openDependencyModal = function(jobId) {
        const modal = document.getElementById('dependency-modal');
        const loading = document.getElementById('dependency-loading');
        const content = document.getElementById('dependency-content');

        modal.classList.remove('hidden');
        loading.classList.remove('hidden');
        content.classList.add('hidden');

        fetch(`/jobs/${jobId}/dependencies`, {
            headers: { 'Accept': 'application/json' }
        })
            .then(r => {
                if (!r.ok) throw new Error('Network response error ' + r.status);
                return r.json();
            })
            .then(data => {
                loading.classList.add('hidden');
                content.classList.remove('hidden');

                if (data.success && data.job) {
                    document.getElementById('dep-job-id').textContent = data.job.job_id;
                    const statusBadge = document.getElementById('dep-job-status');
                    statusBadge.textContent = data.job.status;
                    statusBadge.className = 'badge ' + (data.job.status === 'success' ? 'badge-success' : (data.job.status === 'failed' ? 'badge-danger' : 'badge-warning'));

                    // Parent chain
                    const parentContainer = document.getElementById('dep-parent-container');
                    if (data.parent_chain && data.parent_chain.length > 0) {
                        parentContainer.innerHTML = data.parent_chain.map(p => `
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800 mt-1">
                                <span class="font-mono text-xs text-blue-300 font-bold">${p.job_id}</span>
                                <span class="badge ${p.status === 'success' ? 'badge-success' : (p.status === 'failed' ? 'badge-danger' : 'badge-info')} text-[10px]">${p.status}</span>
                            </div>
                        `).join('');
                    } else if (data.job.depends_on_job_id) {
                        parentContainer.innerHTML = `
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800">
                                <span class="font-mono text-xs text-blue-300 font-bold">${data.job.depends_on_job_id}</span>
                                <span class="badge badge-info text-[10px]">${data.job.dependency_type || 'Required'}</span>
                            </div>
                        `;
                    } else {
                        parentContainer.innerHTML = `<span class="text-slate-500 italic text-xs">No prerequisite parent job (Independent execution)</span>`;
                    }

                    // Children / Dependents chain
                    const childrenContainer = document.getElementById('dep-children-container');
                    if (data.child_chain && data.child_chain.length > 0) {
                        childrenContainer.innerHTML = data.child_chain.map(c => `
                            <div class="flex items-center justify-between p-2 rounded-lg bg-slate-900 border border-slate-800 mt-1">
                                <span class="font-mono text-xs text-emerald-300 font-bold">${c.job_id}</span>
                                <span class="badge ${c.status === 'success' ? 'badge-success' : (c.status === 'failed' ? 'badge-danger' : 'badge-info')} text-[10px]">${c.status}</span>
                            </div>
                        `).join('');
                    } else {
                        childrenContainer.innerHTML = `<span class="text-slate-500 italic text-xs">No dependent child jobs waiting on this print</span>`;
                    }
                }
            })
            .catch(err => {
                loading.textContent = 'Failed to load dependencies: ' + err.message;
            });
    };

    window.closeDependencyModal = function() {
        document.getElementById('dependency-modal').classList.add('hidden');
    };
</script>
@endsection
