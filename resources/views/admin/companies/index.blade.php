@extends('admin.layout')
@section('title', 'Companies')

@section('content')
<x-breadcrumb :items="[['label' => 'Companies']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Company Entities</h2>
        <p class="text-xs text-slate-400">Enterprise organizations and subsidiaries operating within the group</p>
    </div>
</div>

{{-- Create Form Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 pb-2 border-b border-slate-800">
        Register New Company
    </h3>

    <form action="{{ route('admin.companies.store') }}" method="POST">
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

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Company Name</label>
                <input type="text" name="name" required placeholder="e.g. Surya Darma Perkasa" value="{{ old('name') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Unique Code</label>
                <input type="text" name="code" required placeholder="e.g. SDP" style="text-transform: uppercase;" value="{{ old('code') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Short Name / Alias</label>
                <input type="text" name="short_name" placeholder="e.g. Harent" value="{{ old('short_name') }}"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="btn-primary btn-sm">
                <x-icon name="plus" size="13" />
                <span>Register Company</span>
            </button>
        </div>
    </form>
</div>

{{-- Companies List Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Total Registered: <span class="text-white font-mono font-bold">{{ $companies->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Company Name</th>
                    <th class="px-5 py-3.5">Code</th>
                    <th class="px-5 py-3.5">Alias</th>
                    <th class="px-5 py-3.5">Branches & Scopes</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($companies as $company)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-bold text-white">
                        {{ $company->name }}
                    </td>
                    <td class="px-5 py-3.5 font-mono font-bold text-blue-400 text-xs">
                        {{ $company->code }}
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400">
                        {{ $company->short_name ?? '—' }}
                    </td>
                    <td class="px-5 py-3.5 text-xs">
                        <span class="badge badge-info mb-1">{{ $company->branches_count }} branch{{ $company->branches_count !== 1 ? 'es' : '' }}</span>
                        @foreach($company->branches as $branch)
                            <div class="text-[11px] text-slate-400 mt-0.5">
                                📍 {{ $branch->name }}
                                <span class="text-slate-500">({{ $branch->agents_count }} agents, {{ $branch->users_count }} users)</span>
                            </div>
                        @endforeach
                    </td>
                    <td class="px-5 py-3.5">
                        @if($company->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Inactive</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <button class="btn-secondary btn-sm" onclick="openEditModal({{ json_encode($company) }})">Edit</button>
                            @if($company->branches_count === 0)
                            <form action="{{ route('admin.companies.destroy', $company) }}" method="POST" class="inline" onsubmit="return confirm('Delete this company?')">
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
                        <x-empty-state icon="🏢" title="No companies found" description="Register your organizational entities to scope branches and agents." />
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
            <h3 class="text-base font-bold text-white">Edit Company</h3>
            <button onclick="closeEditModal()" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form id="edit-form" method="POST" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Company Name</label>
                <input type="text" name="name" id="edit-name" required
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Code</label>
                <input type="text" name="code" id="edit-code" required style="text-transform: uppercase;"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500 font-mono">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Short Name</label>
                <input type="text" name="short_name" id="edit-short-name"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-300 cursor-pointer">
                    <input type="checkbox" name="is_active" id="edit-active" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600">
                    <span>Company Active</span>
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
function openEditModal(company) {
    document.getElementById('edit-form').action = `/companies/${company.id}`;
    document.getElementById('edit-name').value = company.name;
    document.getElementById('edit-code').value = company.code;
    document.getElementById('edit-short-name').value = company.short_name || '';
    document.getElementById('edit-active').checked = company.is_active;
    document.getElementById('edit-modal').classList.remove('hidden');
}
function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}
</script>
@endsection
