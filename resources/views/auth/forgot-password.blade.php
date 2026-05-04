@extends('auth.layout')
@section('title', 'Forgot Password')

@section('content')
<div class="login-header">
    <h1>Print Hub</h1>
    <p>Reset your password</p>
</div>

@if(session('status'))
    <div class="alert-success">{{ session('status') }}</div>
@endif

@if($errors->any())
    <div class="alert-danger">
        @foreach ($errors->all() as $error)
            {{ $error }}
        @endforeach
    </div>
@endif

<form action="{{ route('password.email') }}" method="POST">
    @csrf
    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        <p style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.5rem;">Enter your email and we'll send you a password reset link.</p>
    </div>
    <button type="submit" class="btn-primary">Send Reset Link</button>
</form>
<a href="{{ route('login') }}" class="btn-secondary">← Back to Sign In</a>
@endsection
