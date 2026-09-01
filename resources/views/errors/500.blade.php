@extends('admin.layout')
@section('title', '500 — Server Error')

@section('content')
<div class="flex flex-col items-center justify-center min-h-[60vh] text-center p-6">
    <div class="w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-2xl mb-4">
        💥
    </div>
    <span class="text-xs font-mono font-bold text-rose-400 uppercase tracking-widest block mb-1">Server Exception</span>
    <h1 class="text-2xl font-bold text-white mb-2">500 Internal Error</h1>
    <p class="text-xs text-slate-400 max-w-sm mb-6">
        An unexpected error occurred while processing your request. Please check the system logs.
    </p>
    <a href="{{ route('admin.dashboard') }}" class="btn-primary btn-sm">
        Return to Dashboard
    </a>
</div>
@endsection
