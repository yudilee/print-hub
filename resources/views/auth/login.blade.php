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
</div>
@endsection
