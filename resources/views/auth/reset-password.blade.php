@extends('auth.layout')
@section('title', 'Reset Password')

@section('content')
<div class="login-header">
    <h1>Print Hub</h1>
    <p>Set a new password</p>
</div>

@if($errors->any())
    <div class="alert-danger">
        @foreach ($errors->all() as $error)
            {{ $error }}
        @endforeach
    </div>
@endif

<form action="{{ route('password.update') }}" method="POST">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="form-group">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="{{ old('email', request('email')) }}" required autofocus autocomplete="email">
    </div>
    <div class="form-group">
        <label for="password">New Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
    </div>
    <div class="form-group">
        <label for="password_confirmation">Confirm Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password" minlength="8">
    </div>
    <button type="submit" class="btn-primary">Reset Password</button>
</form>
<a href="{{ route('login') }}" class="btn-secondary">← Back to Sign In</a>
@endsection
