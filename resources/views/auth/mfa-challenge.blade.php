@extends('auth.layout')
@section('title', 'Two-Factor Authentication')

@section('content')
<div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--bg); padding: 2rem;">
    <div class="card" style="width: 100%; max-width: 420px; padding: 2.5rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🔐</div>
            <h1 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem;">Two-Factor Authentication</h1>
            <p style="color: var(--text-muted); font-size: 0.85rem;">
                Enter the verification code from your authenticator app.
            </p>
        </div>

        @if($errors->any())
            <div class="alert alert-error" role="alert">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('mfa.challenge.verify') }}" method="POST" data-loading>
            @csrf
            <div class="form-group">
                <label for="code">Authentication Code</label>
                <input type="text" name="code" id="code" pattern="[0-9]{4,8}" maxlength="8"
                       inputmode="numeric" autocomplete="one-time-code" required
                       placeholder="000000"
                       style="text-align: center; font-size: 1.8rem; letter-spacing: 0.5em; font-family: monospace; padding: 0.75rem;">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 1rem; padding: 0.75rem; margin-top: 0.5rem;">
                Verify & Login
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center;">
            <a href="{{ route('login') }}" style="color: var(--text-muted); font-size: 0.8rem;">
                ← Back to login
            </a>
        </div>
    </div>
</div>
@endsection
