@extends('admin.layout')
@section('title', 'Agents')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Agents']]" />

<div class="page-header">
    <h1>Print Agents</h1>
    <p>Manage the print agent applications installed on each workstation</p>
</div>

{{-- Add Agent Form --}}
<div class="card">
    <div class="card-header">
        <h2>Register New Agent</h2>
    </div>
    <form action="{{ route('admin.agents.store') }}" method="POST">
        @csrf
        <div class="form-row" style="grid-template-columns: 2fr 2fr 1fr 1fr;">
            <div class="form-group">
                <label for="name">Agent Name <span style="color: var(--danger);">*</span></label>
                <input type="text" name="name" id="name" required placeholder="e.g. PC Front Office">
            </div>
            <div class="form-group">
                <label for="branch_id">Branch</label>
                <select name="branch_id" id="branch_id">
                    <option value="">-- Global (All Branches) --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->company->code }} / {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" name="location" id="location" placeholder="e.g. Lobby">
            </div>
            <div class="form-group">
                <label for="department">Department</label>
                <input type="text" name="department" id="department" placeholder="e.g. Service">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Add Agent</button>
    </form>
</div>

{{-- Agent List --}}
<div class="card" x-data="{ search: '' }">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h2>All Agents ({{ $agents->count() }})</h2>
        <div style="position: relative;">
            <input type="text" x-model="search" placeholder="🔍 Search agents..."
                   style="padding: 6px 12px 6px 30px; font-size: 0.8rem; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 6px; width: 220px; outline: none;">
            <span style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); font-size: 0.8rem; opacity: 0.4;">🔍</span>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Branch</th>
                <th>Location</th>
                <th>Status</th>
                <th>Printers</th>
                <th>Last Seen</th>
                <th>Key Age</th>
                <th>Jobs</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($agents as $agent)
            <tr x-show="search === '' || $el.textContent.toLowerCase().includes(search.toLowerCase())">
                <td>
                    <strong>{{ $agent->name }}</strong>
                    @if($agent->department)
                        <br><span style="font-size: 0.75rem; color: var(--text-muted);">{{ $agent->department }}</span>
                    @endif
                </td>
                <td>
                    @if($agent->branch)
                        <span class="badge badge-info">{{ $agent->branch->company->code ?? '' }}</span>
                        <span style="font-size: 0.8rem;">{{ $agent->branch->name }}</span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">Global</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $agent->location ?? '—' }}</td>
                <td>
                    @if(!$agent->is_active)
                        <span class="badge badge-danger">Disabled</span>
                    @elseif($agent->isOnline())
                        <span class="dot dot-green"></span><span class="badge badge-success">Online</span>
                    @else
                        <span class="dot dot-red"></span><span class="badge badge-danger">Offline</span>
                    @endif
                </td>
                <td>
                    @if(!empty($agent->printers))
                        <div style="display: flex; flex-wrap: wrap; gap: 3px;">
                        @foreach($agent->printers as $printer)
                            <code style="font-size: 0.7rem; padding: 1px 5px; background: var(--surface); border-radius: 3px;">{{ $printer }}</code>
                        @endforeach
                        </div>
                    @else
                        <span style="font-style: italic;">—</span>
                    @endif
                </td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">
                    {{ $agent->last_seen_at ? $agent->last_seen_at->diffForHumans() : 'Never' }}
                    @if($agent->ip_address)
                        <br><code style="font-size: 0.7rem;">{{ $agent->ip_address }}</code>
                    @endif
                </td>
                <td style="font-size: 0.8rem; white-space: nowrap;">
                    @php $keyAge = $agent->last_key_rotated_at ? $agent->last_key_rotated_at->diffInDays(now()) : null; @endphp
                    @if(is_null($keyAge))
                        <span style="color: var(--text-muted); font-style: italic;">N/A</span>
                    @elseif($keyAge > 90)
                        <span style="background: rgba(245,158,11,0.15); color: var(--warning); padding: 2px 8px; border-radius: 10px; font-size: 0.75rem; font-weight: 500;">{{ $keyAge }} days</span>
                    @else
                        {{ $keyAge }} days
                    @endif
                </td>
                <td>{{ $agent->jobs_count }}</td>
                <td>
                    <div style="display: flex; gap: 6px;">
                        <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ $agent->id }}, '{{ e($agent->name) }}', '{{ $agent->branch_id }}', '{{ e($agent->location ?? '') }}', '{{ e($agent->department ?? '') }}', {{ $agent->is_active ? 'true' : 'false' }})">
                            Edit
                        </button>
                        <form action="{{ route('admin.agents.destroy', $agent) }}" method="POST" onsubmit="return confirm('Remove this agent?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Remove</button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9">
                <x-empty-state icon="🖥️" title="No agents registered yet" description="Register your first print agent above to get started." actionText="+ Add Agent" :actionUrl="'#'" />
            </td></tr>
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

{{-- Edit Agent Modal --}}
<div id="edit-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 500px; padding: 2rem; max-height: 90vh; overflow-y: auto;">
        <div class="card-header"><h2>Edit Agent</h2></div>
        <form id="edit-form" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label for="edit_name">Agent Name <span style="color: var(--danger);">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            <div class="form-group">
                <label for="edit_branch_id">Branch</label>
                <select name="branch_id" id="edit_branch_id">
                    <option value="">-- Global (All Branches) --</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->company->code }} / {{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="edit_location">Location</label>
                <input type="text" name="location" id="edit_location" placeholder="e.g. Lobby">
            </div>
            <div class="form-group">
                <label for="edit_department">Department</label>
                <input type="text" name="department" id="edit_department" placeholder="e.g. Service">
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit_is_active" value="1" style="width: 18px; height: 18px;" checked>
                    Agent Active
                </label>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="regenerateKey()">Regenerate Key</button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
let currentAgentId = null;

function openEditModal(id, name, branchId, location, department, isActive) {
    currentAgentId = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_branch_id').value = branchId || '';
    document.getElementById('edit_location').value = location || '';
    document.getElementById('edit_department').value = department || '';
    document.getElementById('edit_is_active').checked = isActive;
    document.getElementById('edit-form').action = '/agents/' + id;
    document.getElementById('edit-modal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}

function regenerateKey() {
    if (confirm('This will invalidate the current agent key. The agent will need to be reconfigured. Continue?')) {
        const form = document.getElementById('edit-form');
        form.action = '/agents/' + currentAgentId + '/regenerate-key';
        form.method = 'POST';
        const methodInput = form.querySelector('input[name="_method"]');
        if (methodInput) methodInput.remove();
        form.submit();
    }
}

document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});
</script>
@endsection
