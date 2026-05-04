@extends('auth.layout')
@section('title', 'Login')

@section('content')
<div class="login-header">
    <h1>Print Hub</h1>
    <p>Sign in to central management</p>
</div>

@if($errors->any())
    <div class="alert-danger">
        @foreach ($errors->all() as $error)
            {{ $error }}
        @endforeach
    </div>
@endif

@if(session('status'))
    <div class="alert-success">{{ session('status') }}</div>
@endif

<form action="{{ route('login') }}" method="POST">
    @csrf
    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
    </div>
    <a href="{{ route('password.request') }}" class="forgot-link">Forgot password?</a>
    <button type="submit" class="btn-primary">Sign In</button>
</form>

@if(config('sso.enabled'))
<div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
    <p style="text-align: center; color: var(--text-muted); font-size: 0.8rem; margin-bottom: 0.75rem;">Or sign in with</p>
    <a href="{{ route('sso.login') }}" class="btn-primary" style="display: flex; justify-content: center; text-decoration: none;">
        SSO Login ({{ ucfirst(config('sso.provider', 'saml2')) }})
    </a>
</div>
@endif
</div>
@endsection
