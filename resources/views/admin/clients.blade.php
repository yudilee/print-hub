@extends('admin.layout')
@section('title', 'Client Apps')

@section('content')
<x-breadcrumb :items="[['label' => 'Client Apps']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Client Applications & API Keys</h2>
        <p class="text-xs text-slate-400">External services and ERP integrations authorized to dispatch print jobs</p>
    </div>
    <button onclick="document.getElementById('register-modal').classList.remove('hidden')" class="btn-primary btn-sm">
        <x-icon name="plus" size="13" />
        <span>Register Client App</span>
    </button>
</div>

{{-- API Quick Reference --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">API Quick Reference</span>
        <a href="{{ route('admin.clients.sdk') }}" target="_blank" class="text-xs text-blue-400 hover:underline">
            Download PHP SDK →
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 font-mono text-xs mb-3">
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
            <span class="text-emerald-400 font-bold">GET</span> <span class="text-slate-300">/api/v1/templates</span>
            <span class="block text-[10px] text-slate-500 font-sans mt-1">List schema & templates</span>
        </div>
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
            <span class="text-amber-400 font-bold">POST</span> <span class="text-slate-300">/api/v1/print</span>
            <span class="block text-[10px] text-slate-500 font-sans mt-1">Unified print dispatch</span>
        </div>
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
            <span class="text-emerald-400 font-bold">GET</span> <span class="text-slate-300">/api/v1/jobs/{id}</span>
            <span class="block text-[10px] text-slate-500 font-sans mt-1">Polling job status</span>
        </div>
        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
            <span class="text-blue-400 font-bold">AUTH</span> <span class="text-slate-300">X-API-Key</span>
            <span class="block text-[10px] text-slate-500 font-sans mt-1">Pass in HTTP header</span>
        </div>
    </div>
</div>

{{-- Registered Apps Table --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">
            Registered Applications: <span class="text-white font-mono font-bold">{{ $clients->count() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-slate-300">
            <thead class="bg-slate-950/60 text-xs uppercase text-slate-400 border-b border-slate-800 font-semibold tracking-wider">
                <tr>
                    <th class="px-5 py-3.5">Application Name</th>
                    <th class="px-5 py-3.5">API Key Signature</th>
                    <th class="px-5 py-3.5">Status</th>
                    <th class="px-5 py-3.5">Last Used</th>
                    <th class="px-5 py-3.5">Key Age</th>
                    <th class="px-5 py-3.5 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60">
                @forelse($clients as $client)
                <tr class="hover:bg-slate-800/40 transition">
                    <td class="px-5 py-3.5 font-bold text-white">
                        {{ $client->name }}
                    </td>
                    <td class="px-5 py-3.5">
                        <code class="px-2 py-0.5 rounded bg-slate-950 border border-slate-800 text-slate-400 text-xs font-mono">
                            sha256:••••••••
                        </code>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($client->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-danger">Revoked</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-xs text-slate-400 font-mono">
                        {{ $client->last_used_at ? $client->last_used_at->diffForHumans() : 'Never' }}
                    </td>
                    <td class="px-5 py-3.5 text-xs font-mono">
                        @php $keyAge = $client->last_key_rotated_at ? $client->last_key_rotated_at->diffInDays(now()) : null; @endphp
                        @if(is_null($keyAge))
                            <span class="text-slate-500 italic">N/A</span>
                        @elseif($keyAge > ($keyRotationDays ?? 90))
                            <span class="badge badge-warning">⚠️ {{ $keyAge }} days</span>
                        @else
                            <span class="text-slate-300">{{ $keyAge }} days</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            <form method="POST" action="{{ route('admin.clients.regenerate-key', $client) }}"
                                  onsubmit="return confirm('Regenerate API key for {{ $client->name }}? The old key will stop working immediately.')" class="inline">
                                @csrf
                                <button type="submit" class="btn-secondary btn-sm">Regen Key</button>
                            </form>
                            <form method="POST" action="{{ route('admin.clients.destroy', $client) }}"
                                  onsubmit="return confirm('Revoke API key for {{ $client->name }}?')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-danger btn-sm">Revoke</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <x-empty-state icon="🔌" title="No client apps registered" description="Create an API client to generate secret keys for third-party systems." />
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Register Modal --}}
<div id="register-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
        <div class="flex items-center justify-between pb-4 mb-4 border-b border-slate-800">
            <h3 class="text-base font-bold text-white">Register Client Application</h3>
            <button onclick="document.getElementById('register-modal').classList.add('hidden')" class="p-1 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition">
                <x-icon name="x" size="18" />
            </button>
        </div>

        <form method="POST" action="{{ route('admin.clients.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">App Name</label>
                <input type="text" name="name" placeholder="e.g. Invoice Billing System" required
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1">Allowed CORS Origins (Optional)</label>
                <input type="text" name="allowed_origins" placeholder="e.g. https://erp.local, http://localhost:3000"
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-blue-500">
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('register-modal').classList.add('hidden')" class="btn-secondary btn-sm">Cancel</button>
                <button type="submit" class="btn-primary btn-sm">Generate Key & Save</button>
            </div>
        </form>
    </div>
</div>
@endsection
