@extends('admin.layout')
@section('title', 'IP Whitelist Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'Network Security & Whitelisting']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Network IP Whitelists & CIDR Guard</h2>
        <p class="text-xs text-slate-400">Restrict ingress traffic and API dispatch to trusted corporate IP addresses and subnet masks</p>
    </div>
</div>

@php
    $whitelistEntries = $globalWhitelist ?? [];
@endphp

{{-- Global IP Whitelist Card --}}
<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 mb-6 shadow-xs">
    <div class="flex items-center justify-between pb-3 mb-4 border-b border-slate-800">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">🌐 Global Gateway Whitelist</h3>
        @if(!empty($whitelistEntries))
            <span class="badge badge-success">Active Enforced ({{ count($whitelistEntries) }} rules)</span>
        @else
            <span class="badge badge-warning">Permissive (All Traffic Allowed)</span>
        @endif
    </div>

    @if(!empty($whitelistEntries))
        <div class="flex flex-wrap gap-2 mb-4">
            @foreach($whitelistEntries as $entry)
                <span class="px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 font-mono text-xs text-blue-400">
                    {{ trim($entry) }}
                </span>
            @endforeach
        </div>
    @endif

    <p class="text-xs text-slate-400">
        Global rules are managed via the <code class="text-blue-400 font-mono">API_IP_WHITELIST</code> environment variable in your <code class="text-slate-300 font-mono">.env</code> configuration.
    </p>
</div>

{{-- Per-Client-App & Per-Agent IP Whitelists --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Client Apps --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
        <h3 class="text-xs font-bold text-blue-400 uppercase tracking-wider pb-2 border-b border-slate-800">
            🔑 Per-Client-App Ingress Rules
        </h3>
        
        @forelse($clientApps as $clientApp)
            @php $appIps = is_array($clientApp->allowed_ips) ? $clientApp->allowed_ips : []; @endphp
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-white text-xs">{{ $clientApp->name }}</span>
                    <span class="badge {{ !empty($appIps) ? 'badge-info' : 'badge-warning' }} text-[10px]">
                        {{ !empty($appIps) ? count($appIps) . ' IPs' : 'Any IP' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.ip-whitelist.client-app', $clientApp) }}" class="space-y-2">
                    @csrf
                    <textarea name="allowed_ips" rows="2" placeholder="192.168.1.100&#10;10.0.0.0/24"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500">{{ implode("\n", $appIps) }}</textarea>
                    <div class="flex justify-end gap-2">
                        <button type="submit" class="btn-primary btn-sm">Save Rules</button>
                    </div>
                </form>
            </div>
        @empty
            <span class="text-xs text-slate-500 italic">No client applications registered.</span>
        @endforelse
    </div>

    {{-- Agents --}}
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs space-y-4">
        <h3 class="text-xs font-bold text-emerald-400 uppercase tracking-wider pb-2 border-b border-slate-800">
            🖥️ Per-Agent Ingress Rules
        </h3>

        @forelse($agents as $agent)
            @php
                $agentIps = $agent->allowed_ips ? explode("\n", $agent->allowed_ips) : [];
                $agentIps = array_filter($agentIps, fn($ip) => !empty(trim($ip)));
            @endphp
            <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-white text-xs">{{ $agent->name }}</span>
                    <span class="badge {{ !empty($agentIps) ? 'badge-info' : 'badge-warning' }} text-[10px]">
                        {{ !empty($agentIps) ? count($agentIps) . ' IPs' : 'Any IP' }}
                    </span>
                </div>

                <form method="POST" action="{{ route('admin.ip-whitelist.agent', $agent) }}" class="space-y-2">
                    @csrf
                    <textarea name="allowed_ips" rows="2" placeholder="192.168.1.100&#10;10.0.0.0/24"
                        class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs font-mono text-slate-200 focus:outline-none focus:border-blue-500">{{ implode("\n", $agentIps) }}</textarea>
                    <div class="flex justify-end gap-2">
                        <button type="submit" class="btn-primary btn-sm">Save Rules</button>
                    </div>
                </form>
            </div>
        @empty
            <span class="text-xs text-slate-500 italic">No agents registered.</span>
        @endforelse
    </div>
</div>
@endsection
