@extends('admin.layout')
@section('title', 'Branches')

@section('content')
<x-breadcrumb :items="[['label' => 'Branches']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Branch Locations & Facilities</h2>
        <p class="text-xs text-slate-400">Manage physical branch networks, local agents, and location-specific print quotas</p>
    </div>
</div>

{{-- Create Form Card --}}
@if(auth()->user()->hasAnyRole(['super-admin', 'company-admin']))
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
        Register New Branch
    </h3>

    <form action="{{ route('admin.branches.store') }}" method="POST">
        @csrf
        @if($errors->any())
            <div class="mb-4 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs">
                <ul class="list-disc pl-5 space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Company</label>
                <select name="company_id" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
                    <option value="">-- Select Company --</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                            {{ $company->name }} ({{ $company->code }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Branch Name</label>
                <input type="text" name="name" required placeholder="e.g. Surabaya Office" value="{{ old('name') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Branch Code</label>
                <input type="text" name="code" required placeholder="e.g. SDP-SBY" style="text-transform: uppercase;" value="{{ old('code') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Address (Optional)</label>
                <input type="text" name="address" placeholder="e.g. Jl. Rungkut..." value="{{ old('address') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary btn-sm">
                <x-icon name="plus" size="13" />
                <span>Register Branch</span>
            </button>
        </div>
    </form>
</div>
@endif

{{-- Branches Table Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Branches: <span class="text-white font-mono font-bold">{{ $branches->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Branch</th>
                    <th class="px-5 py-3.5">Code</th>
                    <th class="px-5 py-3.5">Company</th>
                    <th class="px-5 py-3.5">Agents Online</th>
                    <th class="px-5 py-3.5">Queues</th>
                    <th class="px-5 py-3.5">Total Jobs</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($branches as $branch)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-bold text-white">
                        {{ $branch->name }}
                        @if($branch->address)
                            <span class="block text-[11px] text-slate-500 font-normal">{{ Str::limit($branch->address, 40) }}</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $branch->code }}
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="badge badge-info">{{ $branch->company->code }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="text-emerald-400 font-bold">{{ $branch->agents->filter(fn($a) => $a->isOnline())->count() }}</span>
                        <span class="text-slate-500">/ {{ $branch->agents_count }} agents</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-300">{{ $branch->profiles_count }}</td>
                    <td class="px-5 py-3.5 text-xs text-slate-300 font-mono">{{ number_format($branch->jobs_count) }}</td>
                    <td class="px-5 py-3.5">
                        @if($branch->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <a href="{{ route('admin.branches.template-defaults', $branch) }}" class="btn-secondary btn-sm">Defaults</a>
                            @if(auth()->user()->hasAnyRole(['super-admin', 'company-admin']))
                                <button class="btn-secondary btn-sm" onclick="openEditModal({{ json_encode($branch) }})">Edit</button>
                                @if($branch->agents_count === 0 && $branch->users_count === 0)
                                <form action="{{ route('admin.branches.destroy', $branch) }}" method="POST" class="inline" onsubmit="return confirm('Delete this branch?')">
                                    @csrf @method('DELETE')
                                    <button class="btn-danger btn-sm">Delete</button>
                                </form>
                                @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <x-empty-state icon="🏢" title="No branches found" description="Register branch offices to scope print queues and assign hardware." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Edit Modal --}}
<div id="edit-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Edit Branch</h3>
            <button onclick="closeEditModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="edit-form" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Branch Name</label>
                <input type="text" name="name" id="edit-name" required
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Code</label>
                <input type="text" name="code" id="edit-code" required style="text-transform: uppercase;"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Address</label>
                <input type="text" name="address" id="edit-address"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Monthly Page Goal (Print Reduction)</label>
                <input type="number" name="monthly_page_goal" id="edit-monthly-goal" min="0" placeholder="e.g. 5000"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Branch Active</span>
                </label>
            </div>
            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" class="btn-secondary btn-sm" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Save Changes</button>
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
    document.getElementById('edit-monthly-goal').value = branch.monthly_page_goal || '';
    document.getElementById('edit-active').checked = branch.is_active;
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}
</script>
@endsection
