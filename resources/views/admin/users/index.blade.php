@extends('admin.layout')
@section('title', 'User Management')

@section('content')
<div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
    <div>
        <h1>Users & Access</h1>
        <p>Manage who can access the Print Hub dashboard</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modal-add-user').showModal()">+ Add User</button>
</div>

@if($errors->any())
    <div style="background: rgba(255, 50, 50, 0.1); border: 1px solid var(--danger); color: var(--danger); padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
        <ul style="margin:0; padding-left:20px;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Branch / Company</th>
                <th>Source</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    @php
                        $roleBadges = [
                            'super-admin'     => 'badge-danger',
                            'company-admin'   => 'badge-warning',
                            'branch-admin'    => 'badge-info',
                            'branch-operator' => 'badge-success',
                            'viewer'          => '',
                        ];
                        $badgeClass = $roleBadges[$user->role] ?? '';
                    @endphp
                    <span class="badge {{ $badgeClass }}" @if(!$badgeClass) style="background: rgba(255,255,255,0.08); color: var(--text-muted);" @endif>
                        {{ ucfirst(str_replace('-', ' ', $user->role)) }}
                    </span>
                </td>
                <td style="font-size: 0.8rem;">
                    @if($user->branch)
                        <span style="color: var(--primary);">📍 {{ $user->branch->name }}</span>
                        @if($user->company)
                            <br><span style="color: var(--text-muted);">🏢 {{ $user->company->code }}</span>
                        @endif
                    @elseif($user->company)
                        <span style="color: var(--text-muted);">🏢 {{ $user->company->name }}</span>
                    @else
                        <span style="color: var(--text-muted); font-style: italic;">Unassigned</span>
                    @endif
                </td>
                <td>
                    @if($user->auth_source === 'local')
                        <span class="badge" style="background:#0ea5e9;color:#fff">Local</span>
                    @else
                        <span class="badge" style="background:#8b5cf6;color:#fff">{{ $user->auth_source }}</span>
                    @endif
                </td>
                <td style="color:var(--text-muted); font-size:0.8rem;">{{ $user->created_at->format('M d, Y') }}</td>
                <td>
                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                        <button class="btn btn-sm btn-secondary" onclick="editUser({{ $user->toJson() }})">Edit</button>
                        <button class="btn btn-sm btn-secondary" onclick="resetPassword({{ $user->id }}, '{{ $user->name }}')">Reset Pwd</button>
                        @if($user->id !== auth()->id())
                            <form action="{{ route('admin.users.destroy', $user) }}" method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger">Delete</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center; color:var(--text-muted);">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    
    @if($users->hasPages())
        <div class="pagination" style="margin-top:1rem;">
            {{ $users->links() }}
        </div>
    @endif
</div>

<!-- Add User Modal -->
<dialog id="modal-add-user" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h2>Add User</h2>
        <form action="{{ route('admin.users.store') }}" method="POST">
            @csrf
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="text" name="password" minlength="6" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        @foreach($roles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch_id">
                        <option value="">— No Branch —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Company</label>
                    <select name="company_id">
                        <option value="">— Auto from Branch —</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code }} — {{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-add-user').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Edit User Modal -->
<dialog id="modal-edit-user" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <h2>Edit User</h2>
        <form id="form-edit-user" method="POST">
            @csrf
            @method('PUT')
            <div class="form-row">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="edit-name" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="text" name="email" id="edit-email" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="edit-role" required>
                        @foreach($roles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Branch</label>
                    <select name="branch_id" id="edit-branch">
                        <option value="">— No Branch —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Company</label>
                <select name="company_id" id="edit-company">
                    <option value="">— Auto from Branch —</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->code }} — {{ $company->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-edit-user').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</dialog>

<!-- Reset Password Modal -->
<dialog id="modal-reset-pwd" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <h2>Reset Password for <span id="reset-name"></span></h2>
        <form id="form-reset-pwd" method="POST">
            @csrf
            @method('PUT')
            <div class="form-group">
                <label>New Password</label>
                <input type="text" name="password" minlength="6" required>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modal-reset-pwd').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</dialog>

<script>
function editUser(user) {
    document.getElementById('edit-name').value = user.name;
    document.getElementById('edit-email').value = user.email;
    document.getElementById('edit-role').value = user.role;
    document.getElementById('edit-branch').value = user.branch_id || '';
    document.getElementById('edit-company').value = user.company_id || '';
    document.getElementById('form-edit-user').action = '/users/' + user.id;
    document.getElementById('modal-edit-user').showModal();
}

function resetPassword(id, name) {
    document.getElementById('reset-name').innerText = name;
    document.getElementById('form-reset-pwd').action = '/users/' + id + '/reset-password';
    document.getElementById('modal-reset-pwd').showModal();
}
</script>

<style>
.modal { border:none; border-radius:8px; padding:0; background:transparent; }
.modal::backdrop { background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
.modal-content { background: var(--surface); padding: 24px; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width:100%; }
.modal[open] { animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
</style>
@endsection
