@extends('auth.layout')
@section('title', 'Two-Factor Authentication')

@section('content')
<div class="text-center mb-6">
    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 to-rose-500 flex items-center justify-center text-white mx-auto mb-4 shadow-lg shadow-amber-500/25">
        <x-icon name="shield" size="24" />
    </div>
    <h1 class="text-xl font-bold tracking-tight text-white">Two-Factor Authentication</h1>
    <p class="text-xs text-slate-400 mt-1">Enter the 6-digit code from your authenticator app</p>
</div>

@if($errors->any())
    <div class="mb-5 p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form action="{{ route('mfa.challenge.verify') }}" method="POST" class="space-y-4">
    @csrf
    <div>
        <label for="code" class="block text-xs font-semibold text-slate-300 mb-1.5 text-center">Authentication Code</label>
        <input type="text" name="code" id="code" pattern="[0-9]{4,8}" maxlength="8"
               inputmode="numeric" autocomplete="one-time-code" required autofocus
               placeholder="000000"
               class="w-full text-center text-2xl tracking-[0.4em] font-mono font-bold py-3 bg-slate-950 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
    </div>

    <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-500/20 transition mt-2">
        Verify & Continue
    </button>
</form>

<div class="mt-6 text-center">
    <a href="{{ route('login') }}" class="text-xs text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
        <x-icon name="chevron-left" size="14" />
        <span>Back to Sign In</span>
    </a>
</div>
@endsection
