@extends('admin.layout')
@section('title', 'Companies')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Companies']]" />

<div class="page-header">
    <h1>Companies</h1>
    <p>Manage companies within the Hartono Raya Motor Group.</p>
</div>

{{-- Create Company --}}
<div class="card">
    <div class="card-header"><h2>Register New Company</h2></div>
    <form action="{{ route('admin.companies.store') }}" method="POST">
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
                <label for="name">Company Name</label>
                <input type="text" name="name" id="name" required placeholder="e.g. Surya Darma Perkasa" value="{{ old('name') }}">
            </div>
            <div class="form-group">
                <label for="code">Code (unique identifier)</label>
                <input type="text" name="code" id="code" required placeholder="e.g. SDP" style="text-transform: uppercase;" value="{{ old('code') }}">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="short_name">Short Name / Alias (optional)</label>
                <input type="text" name="short_name" id="short_name" placeholder="e.g. Harent" value="{{ old('short_name') }}">
            </div>
            <div class="form-group" style="display: flex; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">+ Register Company</button>
            </div>
        </div>
    </form>
</div>

{{-- Company List --}}
<div class="card">
    <div class="card-header"><h2>All Companies ({{ $companies->count() }})</h2></div>
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Code</th>
                <th>Short Name</th>
                <th>Branches</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($companies as $company)
            <tr>
                <td><strong>{{ $company->name }}</strong></td>
                <td><span class="mono">{{ $company->code }}</span></td>
                <td style="color: var(--text-muted);">{{ $company->short_name ?? '—' }}</td>
                <td>
                    <span class="badge badge-info">{{ $company->branches_count }} branch{{ $company->branches_count !== 1 ? 'es' : '' }}</span>
                    @foreach($company->branches as $branch)
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                            📍 {{ $branch->name }}
                            <span style="color: var(--text-muted);">({{ $branch->agents_count }} agents, {{ $branch->users_count }} users)</span>
                        </div>
                    @endforeach
                </td>
                <td>
                    @if($company->is_active)
                        <span class="badge badge-success">Active</span>
                    @else
                        <span class="badge badge-danger">Inactive</span>
                    @endif
                </td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn btn-secondary btn-sm" onclick="openEditModal({{ json_encode($company) }})">Edit</button>
                        @if($company->branches_count === 0)
                        <form action="{{ route('admin.companies.destroy', $company) }}" method="POST" onsubmit="return confirm('Delete this company?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="6">
                <x-empty-state icon="🏢" title="No companies registered yet" description="Create a company to start organizing branches and users." actionText="+ Create Company" :actionUrl="'#'" />
            </td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 480px; padding: 2rem;">
        <div class="card-header"><h2>Edit Company</h2></div>
        <form id="edit-form" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>Company Name</label>
                <input type="text" name="name" id="edit-name" required>
            </div>
            <div class="form-group">
                <label>Code</label>
                <input type="text" name="code" id="edit-code" required style="text-transform: uppercase;">
            </div>
            <div class="form-group">
                <label>Short Name</label>
                <input type="text" name="short_name" id="edit-short-name">
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
function openEditModal(company) {
    document.getElementById('edit-form').action = `/companies/${company.id}`;
    document.getElementById('edit-name').value = company.name;
    document.getElementById('edit-code').value = company.code;
    document.getElementById('edit-short-name').value = company.short_name || '';
    document.getElementById('edit-active').checked = company.is_active;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>
@endsection
