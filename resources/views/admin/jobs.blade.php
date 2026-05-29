@extends('admin.layout')
@section('title', 'Job History')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Jobs']]" />

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1>Print Job History</h1>
        <p>All print jobs reported by agents across your organization</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <form action="{{ route('admin.jobs.retry-all-failed') }}" method="POST" onsubmit="return confirm('Retry ALL failed jobs? This will re-queue every failed job for processing.')" style="display: inline;">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm" title="Re-queue all failed jobs">
                🔄 Retry All Failed
            </button>
        </form>
        <a href="{{ route('admin.jobs.export', request()->query()) }}" class="btn btn-primary btn-sm">
            ⬇ Export CSV
        </a>
    </div>
</div>

{{-- Filters --}}
<div class="filter-bar" x-data="{ statusFilter: '', scheduleFilter: '', dateFrom: '', dateTo: '', perPage: '{{ $perPage ?? 25 }}' }">
    <form action="{{ route('admin.jobs') }}" method="GET" style="display:flex; gap:0.75rem; align-items:center; width:100%; flex-wrap: wrap;">
        <select name="status" x-model="statusFilter">
            <option value="">All Statuses</option>
            <option value="success" {{ request('status') === 'success' ? 'selected' : '' }}>✓ Success</option>
            <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>✗ Failed</option>
            <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>⏳ Pending</option>
            <option value="processing" {{ request('status') === 'processing' ? 'selected' : '' }}>🔄 Processing</option>
            <option value="queued" {{ request('status') === 'queued' ? 'selected' : '' }}>📋 Queued</option>
            <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>📅 Scheduled</option>
        </select>
        <select name="priority">
            <option value="">All Priorities</option>
            <option value="1" {{ request('priority') == 1 ? 'selected' : '' }}>🔴 Low</option>
            <option value="2" {{ request('priority') == 2 ? 'selected' : '' }}>🟡 Normal</option>
            <option value="3" {{ request('priority') == 3 ? 'selected' : '' }}>🟠 High</option>
            <option value="4" {{ request('priority') == 4 ? 'selected' : '' }}>🔴 Urgent</option>
        </select>
        <select name="scheduled_filter">
            <option value="">All Jobs</option>
            <option value="scheduled" {{ request('scheduled_filter') === 'scheduled' ? 'selected' : '' }}>📅 Scheduled Only</option>
            <option value="recurring" {{ request('scheduled_filter') === 'recurring' ? 'selected' : '' }}>🔄 Recurring Only</option>
        </select>
        <select name="agent_id">
            <option value="">All Agents</option>
            @foreach($agents as $agent)
                <option value="{{ $agent->id }}" {{ request('agent_id') == $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
            @endforeach
        </select>
        <select name="branch_id">
            <option value="">All Branches</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
            @endforeach
        </select>
        <input type="date" name="date_from" x-model="dateFrom" value="{{ request('date_from') }}"
               style="padding: 6px 10px; font-size: 0.8rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 6px;">
        <span style="color: var(--text-muted); font-size: 0.75rem;">to</span>
        <input type="date" name="date_to" x-model="dateTo" value="{{ request('date_to') }}"
               style="padding: 6px 10px; font-size: 0.8rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 6px;">
        <button class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('admin.jobs') }}" class="btn btn-sm" style="color: var(--text-muted);">Clear Filters</a>
        <div style="display: flex; align-items: center; gap: 0.4rem; margin-left: auto;">
            <label for="per_page" style="font-size: 0.75rem; color: var(--text-muted);">Per page:</label>
            <select name="per_page" id="per_page" x-model="perPage" onchange="this.form.submit()" style="padding: 4px 8px; font-size: 0.75rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; width: auto;">
                <option value="10" {{ ($perPage ?? 25) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ ($perPage ?? 25) == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ ($perPage ?? 25) == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ ($perPage ?? 25) == 100 ? 'selected' : '' }}>100</option>
            </select>
        </div>
    </form>
    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;" x-data="{ quickFilter: '' }" role="tablist" aria-label="Filter jobs by status" @keydown.left.prevent="navigateQuickFilter(-1, $el)" @keydown.right.prevent="navigateQuickFilter(1, $el)" @keydown.escape.prevent="clearQuickFilter($el)">
        <button @click="quickFilter = ''; $el.closest('.filter-bar').querySelector('[name=status]').value = ''; updateActiveTab($el)"
                class="btn btn-sm" :class="quickFilter === '' ? 'btn-primary' : 'btn-secondary'"
                role="tab" tabindex="0" aria-selected="true">All</button>
        <button @click="quickFilter = 'success'; $el.closest('.filter-bar').querySelector('[name=status]').value = 'success'; updateActiveTab($el)"
                class="btn btn-sm" :class="quickFilter === 'success' ? 'btn-success' : 'btn-secondary'"
                role="tab" tabindex="-1" aria-selected="false">✓ Success</button>
        <button @click="quickFilter = 'failed'; $el.closest('.filter-bar').querySelector('[name=status]').value = 'failed'; updateActiveTab($el)"
                class="btn btn-sm" :class="quickFilter === 'failed' ? 'btn-danger' : 'btn-secondary'"
                role="tab" tabindex="-1" aria-selected="false">✗ Failed</button>
        <button @click="quickFilter = 'pending'; $el.closest('.filter-bar').querySelector('[name=status]').value = 'pending'; updateActiveTab($el)"
                class="btn btn-sm" :class="quickFilter === 'pending' ? 'btn-warning' : 'btn-secondary'"
                role="tab" tabindex="-1" aria-selected="false">⏳ Pending</button>
        <button @click="quickFilter = 'scheduled'; $el.closest('.filter-bar').querySelector('[name=status]').value = 'scheduled'; $el.closest('.filter-bar').querySelector('[name=scheduled_filter]').value = 'scheduled'; updateActiveTab($el)"
                class="btn btn-sm" :class="quickFilter === 'scheduled' ? 'btn-info' : 'btn-secondary'"
                role="tab" tabindex="-1" aria-selected="false">📅 Scheduled</button>
    </div>
</div>

<div class="card" x-data="{ expanded: null, selectedJobs: [], selectAll: false }">
    <div class="card-header">
        <h2>Jobs ({{ $jobs->total() }})</h2>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span x-show="selectedJobs.length > 0" x-cloak style="font-size: 0.8rem; color: var(--text-muted);" x-text="selectedJobs.length + ' selected'"></span>
            <form x-show="selectedJobs.length > 0" x-cloak action="{{ route('admin.jobs.bulk-retry') }}" method="POST" style="display: inline;" @submit.prevent="if(confirm('Retry ' + selectedJobs.length + ' selected job(s)?')) $el.submit()">
                @csrf
                <template x-for="jobId in selectedJobs" :key="jobId">
                    <input type="hidden" name="job_ids[]" :value="jobId">
                </template>
                <button type="submit" class="btn btn-warning btn-sm" x-text="'🔄 Retry Selected (' + selectedJobs.length + ')'"></button>
            </form>
        </div>
    </div>
    <table role="table">
        <caption class="sr-only">Print job history</caption>
        <thead>
            <tr>
                <th scope="col" style="width: 36px;">
                    <input type="checkbox" x-model="selectAll" @change="selectedJobs = selectAll ? {{ $jobs->pluck('job_id')->map(fn($id) => "'$id'")->join(',') }} : []" style="width: 16px; height: 16px; cursor: pointer;" aria-label="Select all jobs">
                </th>
                <th scope="col" aria-sort="descending" style="cursor: pointer;" onclick="window.location.href='{{ request()->fullUrlWithQuery(['sort' => 'job_id', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}'">Job ID ↕</th>
                <th scope="col" style="cursor: pointer;" onclick="window.location.href='{{ request()->fullUrlWithQuery(['sort' => 'agent', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc']) }}'">Agent ↕</th>
                <th scope="col">Printer</th>
                <th scope="col">Type</th>
                <th scope="col">Status</th>
                <th scope="col">Time</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($jobs as $job)
            <tr id="job-row-{{ $job->job_id }}" style="transition: background-color 0.5s;" :class="{ 'expanded-row': expanded === '{{ $job->job_id }}' }">
                <td>
                    <input type="checkbox" value="{{ $job->job_id }}" x-model="selectedJobs" style="width: 16px; height: 16px; cursor: pointer;" :aria-label="'Select job {{ $job->job_id }}'">
                </td>
                <td>
                    <code class="mono">{{ $job->job_id }}</code>
                </td>
                <td>{{ $job->agent->name ?? '—' }}</td>
                <td style="font-size: 0.8rem;">{{ $job->printer_name }}</td>
                <td>
                    <span class="badge badge-info">{{ strtoupper($job->type) }}</span>
                    @if($job->file_path)
                        <br><a href="{{ route('admin.jobs.download', $job) }}" style="font-size: 0.7rem; color: var(--primary); text-decoration: underline;" target="_blank">View PDF</a>
                    @endif
                    @if(in_array($job->status, ['queued', 'pending', 'scheduled', 'processing']))
                        <br><button onclick="previewJob('{{ $job->job_id }}')" style="font-size: 0.7rem; color: var(--info); background: none; border: none; cursor: pointer; text-decoration: underline; padding: 0;" title="Preview this job's PDF">👁 Preview</button>
                    @endif
                </td>
                <td id="job-status-{{ $job->job_id }}">
                    @if($job->status === 'success')
                        <span class="badge badge-success">✓ Success</span>
                    @elseif($job->status === 'failed')
                        <span class="badge badge-danger">✗ Failed</span>
                        <form action="{{ route('admin.jobs.retry', $job) }}" method="POST" style="display:inline; margin-left: 5px;" onsubmit="return confirm('Retry this job?')">
                            @csrf
                            <button type="submit" class="btn btn-sm" style="padding: 2px 5px; font-size: 0.65rem; background: var(--primary); color: white; border: none; border-radius: 3px; cursor: pointer;" title="Retry this job">Retry</button>
                        </form>
                    @elseif($job->status === 'scheduled' || $job->status === 'queued')
                        <span class="badge badge-info">{{ $job->status }}</span>
                    @else
                        <span class="badge badge-warning">{{ $job->status }}</span>
                        <form action="{{ route('admin.jobs.status', $job) }}" method="POST" style="display:inline; margin-left: 5px;">
                            @csrf
                            <input type="hidden" name="status" value="success">
                            <button type="submit" class="btn btn-sm" style="padding: 2px 5px; font-size: 0.65rem; background: var(--success); color: white; border: none; border-radius: 3px; cursor: pointer;" title="Manually mark as success">Mark Success</button>
                        </form>
                    @endif
                </td>
                <td style="color: var(--text-muted); font-size: 0.8rem; white-space: nowrap;">
                    {{ $job->created_at->format('d M H:i') }}
                </td>
                <td>
                    <div style="display: flex; gap: 4px;">
                        <button class="btn btn-sm btn-secondary" style="padding: 2px 6px; font-size: 0.65rem;"
                                @click="expanded = expanded === '{{ $job->job_id }}' ? null : '{{ $job->job_id }}'"
                                :aria-expanded="expanded === '{{ $job->job_id }}' ? 'true' : 'false'"
                                :aria-label="expanded === '{{ $job->job_id }}' ? 'Collapse job details' : 'Expand job details'"
                                title="View details">
                            <span x-text="expanded === '{{ $job->job_id }}' ? '▲' : '▼'">▼</span> Details
                        </button>
                        <button class="btn btn-sm btn-secondary" style="padding: 2px 6px; font-size: 0.65rem;"
                                onclick="openDependencyModal('{{ $job->job_id }}')"
                                title="View/Edit Dependencies">🔗 Deps</button>
                        @if(in_array($job->status, ['queued', 'pending', 'scheduled', 'processing']))
                        <button class="btn btn-sm" style="padding: 2px 6px; font-size: 0.65rem; background: var(--info); color: white; border: none; border-radius: 3px; cursor: pointer;"
                                onclick="previewJob('{{ $job->job_id }}')" title="Preview PDF">👁 Preview</button>
                        @endif
                    </div>
                </td>
            </tr>
            {{-- Expandable sub-row for job details (Task 2.2) --}}
            <tr x-show="expanded === '{{ $job->job_id }}'" x-cloak style="background: var(--bg);">
                <td colspan="8" style="padding: 0;">
                    <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.8rem;">
                        <div>
                            <h4 style="font-size: 0.8rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">📋 Job Metadata</h4>
                            <table style="width: auto; font-size: 0.78rem;">
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Reference ID:</td><td>{{ $job->reference_id ?? '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Client:</td><td>{{ $job->client_app_id ?? '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Template:</td><td>{{ $job->template_name ?? '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Copies:</td><td>{{ $job->options['copies'] ?? 1 }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Priority:</td><td>{{ $job->priority ?? '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Duplex:</td><td>{{ $job->options['duplex'] ?? '—' }}</td></tr>
                            </table>
                        </div>
                        <div>
                            <h4 style="font-size: 0.8rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">⏱ Timestamps</h4>
                            <table style="width: auto; font-size: 0.78rem;">
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Created:</td><td>{{ $job->created_at->format('Y-m-d H:i:s') }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Processing:</td><td>{{ $job->agent_created_at ? $job->agent_created_at->format('Y-m-d H:i:s') : '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Completed:</td><td>{{ $job->agent_completed_at ? $job->agent_completed_at->format('Y-m-d H:i:s') : '—' }}</td></tr>
                                <tr><td style="padding: 2px 8px 2px 0; color: var(--text-muted);">Scheduled:</td><td>{{ $job->scheduled_at ? $job->scheduled_at->format('Y-m-d H:i:s') : '—' }}</td></tr>
                            </table>
                        </div>
                        @if($job->error)
                        <div style="grid-column: 1 / -1;">
                            <h4 style="font-size: 0.8rem; font-weight: 600; color: var(--danger); margin-bottom: 0.5rem;">⚠️ Error Message</h4>
                            <pre style="background: rgba(239,68,68,0.05); border: 1px solid rgba(239,68,68,0.2); padding: 0.75rem; border-radius: 6px; font-size: 0.75rem; color: var(--danger); white-space: pre-wrap; word-break: break-word; max-height: 150px; overflow-y: auto;">{{ $job->error }}</pre>
                        </div>
                        @endif
                        @if($job->depends_on_job_id || $job->dependents_count > 0)
                        <div>
                            <h4 style="font-size: 0.8rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">🔗 Dependency Info</h4>
                            <div style="font-size: 0.78rem;">
                                @if($job->depends_on_job_id)
                                    <div>Depends on: <a href="{{ route('admin.jobs', ['job_id' => $job->depends_on_job_id]) }}" style="color: var(--primary); text-decoration: underline;">{{ $job->dependsOn?->job_id ?? '#' . $job->depends_on_job_id }}</a></div>
                                @endif
                                @if($job->dependents_count > 0)
                                    <div>{{ $job->dependents_count }} dependent(s)</div>
                                @endif
                            </div>
                        </div>
                        @endif
                        @if($job->file_path)
                        <div>
                            <h4 style="font-size: 0.8rem; font-weight: 600; color: var(--primary); margin-bottom: 0.5rem;">📄 Document</h4>
                            <a href="{{ route('admin.jobs.download', $job) }}" class="btn btn-sm btn-primary" target="_blank" style="text-decoration: none;">📥 Download / View PDF</a>
                        </div>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="8">
                <x-empty-state icon="📋" title="No jobs found" description="Jobs will appear here when print tasks are submitted through agents or the API." />
            </td></tr>
            @endforelse
        </tbody>
    </table>

    @if($jobs->hasPages())
    <div class="pagination">
        @if($jobs->onFirstPage())
            <span>← Prev</span>
        @else
            <a href="{{ $jobs->previousPageUrl() }}">← Prev</a>
        @endif

        @foreach($jobs->getUrlRange(1, $jobs->lastPage()) as $page => $url)
            @if($page == $jobs->currentPage())
                <span class="active">{{ $page }}</span>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if($jobs->hasMorePages())
            <a href="{{ $jobs->nextPageUrl() }}">Next →</a>
        @else
            <span>Next →</span>
        @endif
    </div>
    @endif
</div>

{{-- Dependency Detail Modal --}}
<div id="dependency-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
    <div style="background:var(--bg); border-radius:12px; max-width:700px; width:90%; max-height:85vh; overflow-y:auto; padding:24px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0;">Job Dependencies</h3>
            <button onclick="closeDependencyModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:var(--text-muted);">&times;</button>
        </div>

        <div id="dependency-loading" style="text-align:center; padding:20px; color:var(--text-muted);">Loading...</div>

        <div id="dependency-content" style="display:none;">
            {{-- Current Job Info --}}
            <div id="dep-current-job" style="margin-bottom:16px; padding:12px; background:var(--bg-secondary); border-radius:8px;">
                <strong>Job:</strong> <code class="mono" id="dep-job-id"></code>
                <span id="dep-job-status" class="badge" style="margin-left:8px;"></span>
                <span id="dep-dependency-type" style="margin-left:8px; font-size:0.8rem; color:var(--text-muted);"></span>
            </div>

            {{-- Dependency Chain Visualization --}}
            <div style="margin-bottom:20px;">
                <h4 style="margin:0 0 8px 0; font-size:0.9rem;">Dependency Chain</h4>
                <div id="dep-chain-tree" style="font-family:monospace; font-size:0.8rem; background:var(--bg-secondary); padding:12px; border-radius:8px; min-height:40px; white-space:pre-wrap;"></div>
            </div>

            {{-- Parent Chain --}}
            <div style="margin-bottom:16px;">
                <h4 style="margin:0 0 8px 0; font-size:0.9rem;">⬆ Parent Chain (what this job depends on)</h4>
                <div id="dep-parent-chain" style="font-size:0.8rem;">
                    <span style="color:var(--text-muted);">No parent dependencies.</span>
                </div>
            </div>

            {{-- Child Chain --}}
            <div style="margin-bottom:20px;">
                <h4 style="margin:0 0 8px 0; font-size:0.9rem;">⬇ Child Chain (jobs that depend on this)</h4>
                <div id="dep-child-chain" style="font-size:0.8rem;">
                    <span style="color:var(--text-muted);">No dependent jobs.</span>
                </div>
            </div>

            {{-- Edit Dependency Form --}}
            <div style="border-top:1px solid var(--border); padding-top:16px;">
                <h4 style="margin:0 0 12px 0; font-size:0.9rem;">✏ Set / Edit Dependency</h4>
                <form id="dep-edit-form" method="POST" onsubmit="return submitDependencyEdit(event)">
                    @csrf
                    <input type="hidden" id="dep-edit-job-id" name="job_id" value="">

                    <div style="margin-bottom:12px;">
                        <label style="display:block; margin-bottom:4px; font-size:0.8rem; color:var(--text-muted);">Parent Job (search by ID or template name)</label>
                        <select id="dep-parent-select" name="depends_on_job_id" style="width:100%; padding:8px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text);">
                            <option value="">— No dependency —</option>
                        </select>
                        <div style="font-size:0.7rem; color:var(--text-muted); margin-top:4px;">
                            Type to search for a job. Jobs that would create a circular dependency are excluded.
                        </div>
                    </div>

                    <div style="margin-bottom:12px;" id="dep-type-group">
                        <label style="display:block; margin-bottom:4px; font-size:0.8rem; color:var(--text-muted);">Dependency Type</label>
                        <div style="display:flex; gap:12px;">
                            <label style="font-size:0.8rem; cursor:pointer;">
                                <input type="radio" name="dependency_type" value="after" checked> After (any completion)
                            </label>
                            <label style="font-size:0.8rem; cursor:pointer;">
                                <input type="radio" name="dependency_type" value="after_success"> After Success
                            </label>
                            <label style="font-size:0.8rem; cursor:pointer;">
                                <input type="radio" name="dependency_type" value="after_failure"> After Failure
                            </label>
                        </div>
                    </div>

                    <div style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="closeDependencyModal()">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary">Save Dependency</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script type="module">
    // ── Dependency Modal ──────────────────────────────────────
    window.openDependencyModal = function (jobId) {
        const modal = document.getElementById('dependency-modal');
        const loading = document.getElementById('dependency-loading');
        const content = document.getElementById('dependency-content');

        modal.style.display = 'flex';
        loading.style.display = 'block';
        content.style.display = 'none';

        // Reset form
        document.getElementById('dep-edit-job-id').value = jobId;
        document.getElementById('dep-parent-select').value = '';
        document.querySelector('input[name="dependency_type"][value="after"]').checked = true;

        // Fetch dependency data
        fetch(`/jobs/${jobId}/dependencies`)
            .then(r => r.json())
            .then(data => {
                loading.style.display = 'none';
                content.style.display = 'block';

                if (!data.success) {
                    document.getElementById('dep-chain-tree').textContent = 'Failed to load dependencies.';
                    return;
                }

                const job = data.job;

                // Current job info
                document.getElementById('dep-job-id').textContent = job.job_id;
                const statusBadge = document.getElementById('dep-job-status');
                statusBadge.textContent = job.status;
                statusBadge.className = 'badge badge-' + (job.status === 'success' ? 'success' : job.status === 'failed' ? 'danger' : 'warning');

                const depTypeEl = document.getElementById('dep-dependency-type');
                if (job.depends_on_job_id) {
                    depTypeEl.textContent = '(' + (job.dependency_type || 'after').replace(/_/g, ' ') + ')';
                } else {
                    depTypeEl.textContent = '(no dependency)';
                }

                // Build chain tree visualization
                const parentChain = data.parent_chain || [];
                const childChain = data.child_chain || [];
                let treeLines = [];

                // Draw the tree
                if (parentChain.length > 0) {
                    // Reverse parent chain so root is at top
                    const reversed = [...parentChain].reverse();
                    reversed.forEach((p, i) => {
                        const prefix = i === reversed.length - 1 ? '⬆ ' : '   ';
                        const arrow = i < reversed.length - 1 ? '│  ' : '   ';
                        const statusIcon = p.status === 'success' ? '✓' : p.status === 'failed' ? '✗' : p.status === 'circular' ? '⚠' : '○';
                        treeLines.push(prefix + ' ' + statusIcon + ' ' + p.job_id + ' (' + p.status + ')');
                        if (i < reversed.length - 1) {
                            treeLines.push(arrow + '│');
                        }
                    });
                    treeLines.push('   │');
                    treeLines.push('   ▼');
                }
                treeLines.push('◉ ' + job.job_id + ' (' + job.status + ')');
                if (childChain.length > 0) {
                    treeLines.push('   ▼');
                    childChain.forEach((c, i) => {
                        const prefix = i < childChain.length - 1 ? '├─ ' : '└─ ';
                        const statusIcon = c.status === 'success' ? '✓' : c.status === 'failed' ? '✗' : c.status === 'circular' ? '⚠' : '○';
                        treeLines.push('   ' + prefix + statusIcon + ' ' + c.job_id + ' (' + c.status + ')');
                    });
                }

                document.getElementById('dep-chain-tree').textContent = treeLines.join('\n');

                // Parent chain list
                const parentEl = document.getElementById('dep-parent-chain');
                if (parentChain.length > 0) {
                    parentEl.innerHTML = parentChain.map(p => {
                        const statusClass = p.status === 'success' ? 'badge-success' : p.status === 'failed' ? 'badge-danger' : p.status === 'circular' ? 'badge-warning' : 'badge-info';
                        return `<div style="padding:4px 0; display:flex; align-items:center; gap:8px;">
                            <span class="badge ${statusClass}" style="font-size:0.65rem;">${p.status}</span>
                            <a href="{{ route('admin.jobs') }}?job_id=${p.job_id}" style="font-family:monospace; color:var(--primary); text-decoration:underline;">${p.job_id}</a>
                        </div>`;
                    }).join('');
                } else {
                    parentEl.innerHTML = '<span style="color:var(--text-muted);">No parent dependencies.</span>';
                }

                // Child chain list
                const childEl = document.getElementById('dep-child-chain');
                if (childChain.length > 0) {
                    childEl.innerHTML = childChain.map(c => {
                        const statusClass = c.status === 'success' ? 'badge-success' : c.status === 'failed' ? 'badge-danger' : c.status === 'circular' ? 'badge-warning' : 'badge-info';
                        return `<div style="padding:4px 0; display:flex; align-items:center; gap:8px;">
                            <span class="badge ${statusClass}" style="font-size:0.65rem;">${c.status}</span>
                            <a href="{{ route('admin.jobs') }}?job_id=${c.job_id}" style="font-family:monospace; color:var(--primary); text-decoration:underline;">${c.job_id}</a>
                        </div>`;
                    }).join('');
                } else {
                    childEl.innerHTML = '<span style="color:var(--text-muted);">No dependent jobs.</span>';
                }

                // Initialize parent job search (Select2-like)
                initParentSearch(jobId, job.depends_on_job_id);
            })
            .catch(err => {
                loading.textContent = 'Error loading dependencies: ' + err.message;
            });
    };

    window.closeDependencyModal = function () {
        document.getElementById('dependency-modal').style.display = 'none';
    };

    // Close modal on overlay click
    document.addEventListener('click', function (e) {
        const modal = document.getElementById('dependency-modal');
        if (e.target === modal) {
            closeDependencyModal();
        }
    });

    // ── Parent Job Search ─────────────────────────────────────
    function initParentSearch(currentJobId, currentDependsOn) {
        const select = document.getElementById('dep-parent-select');

        // Clear existing options (keep the first empty one)
        while (select.options.length > 1) {
            select.remove(1);
        }

        // Fetch initial results
        fetch(`/jobs/search-parents?q=&exclude_job_id=${currentJobId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    data.results.forEach(job => {
                        const opt = document.createElement('option');
                        opt.value = job.id;
                        opt.textContent = job.text;
                        if (job.id === currentDependsOn) {
                            opt.selected = true;
                        }
                        select.appendChild(opt);
                    });
                }
            });

        // Re-fetch on input (simple search-as-you-type)
        let searchTimeout;
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search jobs...';
        searchInput.style.cssText = 'width:100%; padding:8px; border-radius:6px; border:1px solid var(--border); background:var(--bg); color:var(--text); margin-bottom:8px; box-sizing:border-box;';

        select.parentNode.insertBefore(searchInput, select);

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const q = this.value;
                fetch(`/jobs/search-parents?q=${encodeURIComponent(q)}&exclude_job_id=${currentJobId}`)
                    .then(r => r.json())
                    .then(data => {
                        // Clear existing options (keep the first empty one)
                        while (select.options.length > 1) {
                            select.remove(1);
                        }
                        if (data.success) {
                            data.results.forEach(job => {
                                const opt = document.createElement('option');
                                opt.value = job.id;
                                opt.textContent = job.text;
                                if (job.id === currentDependsOn) {
                                    opt.selected = true;
                                }
                                select.appendChild(opt);
                            });
                        }
                    });
            }, 300);
        });
    }

    // ── Submit Dependency Edit ────────────────────────────────
    window.submitDependencyEdit = function (event) {
        event.preventDefault();
        const form = event.target;
        const jobId = document.getElementById('dep-edit-job-id').value;
        const dependsOnJobId = document.getElementById('dep-parent-select').value;
        const dependencyType = document.querySelector('input[name="dependency_type"]:checked').value;

        // Validate: if setting a dependency, check for circular reference first
        if (dependsOnJobId) {
            fetch('{{ route("admin.jobs.validate-dependency") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                },
                body: JSON.stringify({
                    job_id: jobId,
                    depends_on_job_id: dependsOnJobId,
                }),
            })
            .then(r => r.json())
            .then(data => {
                if (!data.valid) {
                    alert(data.error || 'Circular dependency detected!');
                    return;
                }
                // Proceed with update
                submitDependencyUpdate(jobId, dependsOnJobId, dependencyType);
            })
            .catch(() => {
                // If validation endpoint fails, proceed anyway
                submitDependencyUpdate(jobId, dependsOnJobId, dependencyType);
            });
        } else {
            // Removing dependency
            submitDependencyUpdate(jobId, '', '');
        }

        return false;
    };

    function submitDependencyUpdate(jobId, dependsOnJobId, dependencyType) {
        const form = document.getElementById('dep-edit-form');
        const formData = new FormData();
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        formData.append('depends_on_job_id', dependsOnJobId);
        formData.append('dependency_type', dependencyType);

        fetch(`/jobs/${jobId}/update-dependency`, {
            method: 'POST',
            body: formData,
        })
        .then(r => {
            if (r.redirected) {
                window.location.reload();
            } else {
                return r.json();
            }
        })
        .then(data => {
            if (data && !data.success) {
                alert('Failed to update dependency: ' + (data.error || 'Unknown error'));
            } else {
                window.location.reload();
            }
        })
        .catch(err => {
            alert('Error updating dependency: ' + err.message);
        });
    }

    // ── Real-time Status Updates ──────────────────────────────
    if (window.Echo) {
        window.Echo.channel('print-jobs')
            .listen('.job.status.updated', (e) => {
                const row = document.getElementById('job-row-' + e.job_id);
                if (row) {
                    const statusTd = document.getElementById('job-status-' + e.job_id);
                    const errorTd = document.getElementById('job-error-' + e.job_id);

                    if (e.status === 'success') {
                        statusTd.innerHTML = '<span class="badge badge-success">✓ Success</span>';
                        row.style.backgroundColor = 'rgba(34, 197, 94, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                        if (errorTd) errorTd.innerText = '—';
                    } else if (e.status === 'failed') {
                        statusTd.innerHTML = '<span class="badge badge-danger">✗ Failed</span>';
                        row.style.backgroundColor = 'rgba(239, 68, 68, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                        if (errorTd) errorTd.innerText = e.error || '—';
                    } else {
                        statusTd.innerHTML = '<span class="badge badge-warning">' + e.status + '</span>';
                        row.style.backgroundColor = 'rgba(245, 158, 11, 0.1)';
                        setTimeout(() => row.style.backgroundColor = '', 2000);
                    }
                }
            });
    }

    // ── Quick Filter Keyboard Navigation ─────────────────────
    function updateActiveTab(btn) {
        const tablist = btn.closest('[role="tablist"]');
        if (!tablist) return;
        tablist.querySelectorAll('[role="tab"]').forEach(t => {
            t.setAttribute('tabindex', '-1');
            t.setAttribute('aria-selected', 'false');
        });
        btn.setAttribute('tabindex', '0');
        btn.setAttribute('aria-selected', 'true');
        btn.focus();
    }

    function navigateQuickFilter(direction, tablist) {
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        const currentIdx = tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
        let nextIdx = currentIdx + direction;
        if (nextIdx < 0) nextIdx = tabs.length - 1;
        if (nextIdx >= tabs.length) nextIdx = 0;
        tabs[nextIdx].click();
    }

    function clearQuickFilter(tablist) {
        const firstTab = tablist.querySelector('[role="tab"]');
        if (firstTab) firstTab.click();
        tablist.closest('.filter-bar').querySelector('[name=status]').value = '';
        tablist.closest('.filter-bar').querySelector('[name=scheduled_filter]').value = '';
    }

    // ── Print Preview Modal (Task 4) ─────────────────────────
    let previewIframe = null;
    function previewJob(jobId) {
        // Remove existing preview modal if any
        closePreviewModal();

        const overlay = document.createElement('div');
        overlay.id = 'preview-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9999;display:flex;align-items:center;justify-content:center;';

        const modal = document.createElement('div');
        modal.className = 'card';
        modal.style.cssText = 'width:90%;max-width:900px;height:90%;display:flex;flex-direction:column;padding:0;overflow:hidden;';

        modal.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.5rem;border-bottom:1px solid var(--border);flex-shrink:0;">
                <h2 style="font-size:1rem;font-weight:600;">Print Preview — <code class="mono">${jobId}</code></h2>
                <button onclick="closePreviewModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer;padding:0.25rem;" title="Close preview">&times;</button>
            </div>
            <div style="flex:1;display:flex;align-items:center;justify-content:center;background:var(--bg);min-height:0;">
                <div class="spinner" style="width:32px;height:32px;border-width:3px;"></div>
                <span style="margin-left:0.75rem;color:var(--text-muted);font-size:0.9rem;">Loading preview...</span>
            </div>
        `;

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Fetch the preview PDF
        const previewUrl = '/jobs/' + jobId + '/preview';
        const contentDiv = modal.querySelector('div[style*="flex:1"]');

        fetch(previewUrl)
            .then(r => {
                if (!r.ok) throw new Error('Preview failed (HTTP ' + r.status + ')');
                return r.blob();
            })
            .then(blob => {
                const url = URL.createObjectURL(blob);
                contentDiv.innerHTML = `<iframe src="${url}" style="width:100%;height:100%;border:none;" title="Print Preview"></iframe>`;
            })
            .catch(err => {
                contentDiv.innerHTML = `<div style="text-align:center;color:var(--danger);padding:2rem;">
                    <div style="font-size:2rem;margin-bottom:0.5rem;">❌</div>
                    <p>${err.message}</p>
                </div>`;
            });
    }

    function closePreviewModal() {
        const existing = document.getElementById('preview-overlay');
        if (existing) {
            existing.remove();
        }
    }

    // Close preview on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closePreviewModal();
    });
</script>
@endsection
