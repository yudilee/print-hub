@extends('admin.layout')
@section('title', 'User Management')

@section('content')
<x-breadcrumb :items="[['label' => 'Users & Access']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Users & Access Control</h2>
        <p class="text-xs text-slate-400">Manage administrator accounts, branch scopes, and authentication sources</p>
    </div>
    <button class="btn-primary btn-sm" onclick="document.getElementById('modal-add-user').classList.remove('hidden')">
        <x-icon name="plus" size="13" />
        <span>Add User</span>
    </button>
</div>

@if($errors->any())
    <div class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Users: <span class="text-white font-mono font-bold">{{ $users->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">User</th>
                    <th class="px-5 py-3.5">Role</th>
                    <th class="px-5 py-3.5">Assigned Scope</th>
                    <th class="px-5 py-3.5">Auth Source</th>
                    <th class="px-5 py-3.5">Created</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($users as $user)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-blue-400">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div>
                                <span class="font-bold text-white block">{{ $user->name }}</span>
                                <span class="text-xs text-slate-400 font-mono">{{ $user->email }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        @php
                            $roleBadges = [
                                'super-admin'     => 'badge-danger',
                                'company-admin'   => 'badge-warning',
                                'branch-admin'    => 'badge-info',
                                'branch-operator' => 'badge-success',
                                'viewer'          => 'badge-info',
                            ];
                            $badgeClass = $roleBadges[$user->role] ?? 'badge-info';
                        @endphp
                        <span class="badge {{ $badgeClass }}">
                            {{ ucfirst(str_replace('-', ' ', $user->role)) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        @if($user->branch)
                            <span class="text-blue-400 font-semibold">📍 {{ $user->branch->name }}</span>
                            @if($user->company)
                                <span class="block text-[10px] text-slate-500">🏢 {{ $user->company->code }}</span>
                            @endif
                        @elseif($user->company)
                            <span class="text-slate-300">🏢 {{ $user->company->name }}</span>
                        @else
                            <span class="text-slate-500 italic">Global Access</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge {{ $user->auth_source === 'local' ? 'badge-info' : 'badge-warning' }}">
                            {{ ucfirst($user->auth_source) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $user->created_at->format('M d, Y') }}
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button class="btn-secondary btn-sm" onclick="editUser({{ $user->toJson() }})">Edit</button>
                            <button class="btn-secondary btn-sm" onclick="resetPassword({{ $user->id }}, '{{ $user->name }}')">Reset Pwd</button>
                            @if($user->id !== auth()->id())
                                <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user?');">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-sm">Delete</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="👤" title="No users found" description="Create user accounts to manage access." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($users->hasPages())
    <div class="p-4 border-t border-slate-800">
        {{ $users->links() }}
    </div>
    @endif
</div>

<!-- Add User Modal -->
<div id="modal-add-user" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Create New User</h3>
            <button onclick="document.getElementById('modal-add-user').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Full Name</label>
                <input type="text" name="name" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Email Address</label>
                <input type="email" name="email" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Password</label>
                    <input type="password" name="password" minlength="6" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Role</label>
                    <select name="role" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        @foreach($roles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Branch</label>
                    <select name="branch_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">— No Branch —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Company</label>
                    <select name="company_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">— Auto from Branch —</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code }} — {{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('modal-add-user').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="modal-edit-user" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Edit User</h3>
            <button onclick="document.getElementById('modal-edit-user').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="form-edit-user" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Full Name</label>
                <input type="text" name="name" id="edit-name" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Email Address</label>
                <input type="email" name="email" id="edit-email" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Role</label>
                <select name="role" id="edit-role" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Branch</label>
                    <select name="branch_id" id="edit-branch" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">— No Branch —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->company->code ?? '' }} / {{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-400 mb-1">Company</label>
                    <select name="company_id" id="edit-company" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                        <option value="">— Auto from Branch —</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->code }} — {{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('modal-edit-user').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="modal-reset-pwd" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Reset Password</h3>
            <button onclick="document.getElementById('modal-reset-pwd').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <p class="text-xs text-slate-400 mb-4">Reset password for <strong id="reset-name" class="text-white"></strong></p>

        <form id="form-reset-pwd" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">New Password</label>
                <input type="password" name="password" minlength="6" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="document.getElementById('modal-reset-pwd').classList.add('hidden')">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function editUser(user) {
    document.getElementById('edit-name').value = user.name;
    document.getElementById('edit-email').value = user.email;
    document.getElementById('edit-role').value = user.role;
    document.getElementById('edit-branch').value = user.branch_id || '';
    document.getElementById('edit-company').value = user.company_id || '';
    document.getElementById('form-edit-user').action = '/users/' + user.id;
    document.getElementById('modal-edit-user').classList.remove('hidden');
}

function resetPassword(id, name) {
    document.getElementById('reset-name').innerText = name;
    document.getElementById('form-reset-pwd').action = '/users/' + id + '/reset-password';
    document.getElementById('modal-reset-pwd').classList.remove('hidden');
}
</script>
@endsection
