@extends('auth.layout')
@section('title', 'Sign In')

@section('content')
<div class="text-center mb-8">
    <div class="w-14 h-14 rounded-2xl bg-slate-950 border border-slate-700/60 p-2 flex items-center justify-center mx-auto mb-4 shadow-xl shadow-teal-500/10">
        <img src="{{ asset('logo-icon.png') }}" alt="Print Hub Logo" class="w-full h-full object-contain">
    </div>
    <h1 class="text-xl font-bold tracking-tight text-white">Welcome Back</h1>
    <p class="text-xs text-slate-400 mt-1">Sign in to the Print Hub Central Portal</p>
</div>

@if($errors->any())
    <div class="mb-5 p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

@if(session('status'))
    <div class="mb-5 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-semibold">
        {{ session('status') }}
    </div>
@endif

<form action="{{ route('login') }}" method="POST" class="space-y-4">
    @csrf
    <div>
        <label for="email" class="block text-xs font-semibold text-slate-300 mb-1.5">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
            placeholder="admin@example.com">
    </div>

    <div>
        <div class="flex items-center justify-between mb-1.5">
            <label for="password" class="block text-xs font-semibold text-slate-300">Password</label>
            <a href="{{ route('password.request') }}" class="text-xs text-blue-400 hover:underline">Forgot password?</a>
        </div>
        <input type="password" id="password" name="password" required autocomplete="current-password"
            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
            placeholder="••••••••">
    </div>

    <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-500/20 transition mt-2">
        Sign In to Portal
    </button>
</form>

@if(config('sso.enabled'))
<div class="mt-6 pt-6 border-t border-slate-800 text-center">
    <p class="text-xs text-slate-400 mb-3">Or authenticate via enterprise SSO</p>
    <a href="{{ route('sso.login') }}" class="w-full py-2.5 px-4 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold rounded-xl flex items-center justify-center gap-2 transition">
        <x-icon name="key" size="14" class="text-blue-400" />
        <span>Single Sign-On ({{ ucfirst(config('sso.provider', 'SAML 2.0')) }})</span>
    </a>
</div>
@endif
@endsection
