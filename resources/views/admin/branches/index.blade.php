@extends('admin.layout')
@section('title', 'Branches')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Branches']]" />

<div class="page-header">
    <h1>Branches</h1>
    <p>Manage branch locations and their printing configurations.</p>
</div>

{{-- Create Branch --}}
@if(auth()->user()->hasAnyRole(['super-admin', 'company-admin']))
<div class="card">
    <div class="card-header"><h2>Create New Branch</h2></div>
    <form action="{{ route('admin.branches.store') }}" method="POST">
        @csrf
        @if($errors->any())
            <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="form-row">
            <div class="form-group">
                <label for="company_id">Company</label>
                <select name="company_id" id="company_id" required>
                    <option value="">-- Select Company --</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                            {{ $company->name }} ({{ $company->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="name">Branch Name</label>
                <input type="text" name="name" id="name" required placeholder="e.g. SDP - Surabaya Office" value="{{ old('name') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="code">Branch Code (unique)</label>
                <input type="text" name="code" id="code" required placeholder="e.g. SDP-SBY" style="text-transform: uppercase;" value="{{ old('code') }}">
            </div>
            <div class="form-group">
                <label for="address">Address (optional)</label>
                <input type="text" name="address" id="address" placeholder="e.g. Jl. Rungkut Industri..." value="{{ old('address') }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary">+ Create Branch</button>
    </form>
</div>
@endif

{{-- Filter --}}
@if(auth()->user()->isSuperAdmin() && $companies->count() > 1)
<div class="filter-bar">
    <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
        <select name="company_id" onchange="this.form.submit()">
            <option value="">All Companies</option>
            @foreach($companies as $company)
                <option value="{{ $company->id }}" {{ request('company_id') == $company->id ? 'selected' : '' }}>
                    {{ $company->code }} — {{ $company->name }}
                </option>
            @endforeach
        </select>
    </form>
</div>
@endif

{{-- Branch List --}}
<div class="card">
    <div class="card-header"><h2>Branches ({{ $branches->count() }})</h2></div>
    <table>
        <thead>
            <tr>
                <th>Branch</th>
                <th>Code</th>
                <th>Company</th>
                <th>Agents</th>
                <th>Queues</th>
                <th>Users</th>
                <th>Jobs</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($branches as $branch)
            <tr>
                <td>
                    <strong>{{ $branch->name }}</strong>
                    @if($branch->address)
                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 2px;">{{ Str::limit($branch->address, 50) }}</div>
                    @endif
                </td>
                <td><span class="mono">{{ $branch->code }}</span></td>
                <td>
                    <span class="badge badge-info">{{ $branch->company->code }}</span>
                </td>
                <td>
                    <span style="color: var(--success);">{{ $branch->agents->filter(fn($a) => $a->isOnline())->count() }}</span>
                    / {{ $branch->agents_count }}
                </td>
                <td>{{ $branch->profiles_count }}</td>
                <td>{{ $branch->users_count }}</td>
                <td>{{ number_format($branch->jobs_count) }}</td>
                <td>
                    @if($branch->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
                <td>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="{{ route('admin.branches.template-defaults', $branch) }}" class="btn btn-warning btn-sm" style="text-decoration: none;">
                            Defaults
                        </a>
                        @if(auth()->user()->hasAnyRole(['super-admin', 'company-admin']))
                        <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ json_encode($branch) }})">Edit</button>
                        @if($branch->agents_count === 0 && $branch->users_count === 0)
                        <form action="{{ route('admin.branches.destroy', $branch) }}" method="POST" onsubmit="return confirm('Delete this branch?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                        @endif
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="9">
                <x-empty-state icon="🏢" title="No branches found" description="Create a branch to organize your agents and queues by location." />
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 480px; padding: 2rem;">
        <div class="card-header"><h2>Edit Branch</h2></div>
        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Branch Name</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>Code</label>
                <input type="text" name="code" id="edit-code" required style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" id="edit-address">
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" style="width: 18px; height: 18px;">
                    Active
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(branch) {
    document.getElementById('edit-form').action = `/branches/${branch.id}`;
    document.getElementById('edit-name').value = branch.name;
    document.getElementById('edit-code').value = branch.code;
    document.getElementById('edit-address').value = branch.address || '';
    document.getElementById('edit-active').checked = branch.is_active;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>
@endsection
