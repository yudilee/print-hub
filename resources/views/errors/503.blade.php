@extends('admin.layout')
@section('title', '503 — Service Unavailable')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center p-6">
    <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-2xl mb-4">
        🛠️
    </div>
    <span class="text-xs font-mono font-bold text-indigo-400 uppercase tracking-widest block mb-1">Maintenance Mode</span>
    <h1 class="text-2xl font-bold text-white mb-2">503 Service Unavailable</h1>
    <p class="text-xs text-slate-400 max-w-sm mb-6">
        Print Hub is currently undergoing scheduled maintenance. Services will resume shortly.
    </p>
    <a href="{{ route('admin.dashboard') }}" class="btn-primary btn-sm">
        Refresh Status
    </a>
</div>
@endsection
