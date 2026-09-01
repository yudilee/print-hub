@extends('auth.layout')
@section('title', 'Reset Password')

@section('content')
<div class="text-center mb-6">
    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white mx-auto mb-4 shadow-lg shadow-blue-500/25">
        <x-icon name="key" size="22" />
    </div>
    <h1 class="text-xl font-bold tracking-tight text-white">Create New Password</h1>
    <p class="text-xs text-slate-400 mt-1">Set a strong password for your account</p>
</div>

@if($errors->any())
    <div class="mb-5 p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form action="{{ route('password.update') }}" method="POST" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div>
        <label for="email" class="block text-xs font-semibold text-slate-300 mb-1.5">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email', request('email')) }}" required autofocus autocomplete="email"
            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
    </div>

    <div>
        <label for="password" class="block text-xs font-semibold text-slate-300 mb-1.5">New Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8"
            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
    </div>

    <div>
        <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 mb-1.5">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" minlength="8"
            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
    </div>

    <button type="submit" class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-500/20 transition mt-2">
        Reset Password
    </button>
</form>

<div class="mt-6 text-center">
    <a href="{{ route('login') }}" class="text-xs text-slate-400 hover:text-white transition flex items-center justify-center gap-1">
        <x-icon name="chevron-left" size="14" />
        <span>Back to Sign In</span>
    </a>
</div>
@endsection
