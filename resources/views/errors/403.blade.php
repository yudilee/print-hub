@extends('admin.layout')
@section('title', '403 — Forbidden')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center p-6">
    <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-2xl mb-4">
        🔒
    </div>
    <span class="text-xs font-mono font-bold text-amber-400 uppercase tracking-widest block mb-1">Access Denied</span>
    <h1 class="text-2xl font-bold text-white mb-2">403 Forbidden</h1>
    <p class="text-xs text-slate-400 max-w-sm mb-6">
        {{ $exception->getMessage() ?: 'You do not have administrative permission to access this resource.' }}
    </p>
    <a href="{{ route('admin.dashboard') }}" class="btn-primary btn-sm">
        Return to Dashboard
    </a>
</div>
@endsection
