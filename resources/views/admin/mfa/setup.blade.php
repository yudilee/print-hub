@extends('admin.layout')
@section('title', 'Two-Factor Authentication Setup')

@section('content')
<x-breadcrumb :items="[['label' => 'Dashboard', 'url' => route('admin.dashboard')], ['label' => 'Two-Factor Auth']]" />

<div class="page-header">
    <h1>Two-Factor Authentication</h1>
    <p>Enhance your account security with time-based one-time passwords (TOTP).</p>
</div>

@if(session('mfa_success'))
    <div class="alert alert-success">✓ {!! session('mfa_success') !!}</div>
@endif

@if($mfaToken && $mfaToken->is_enabled)
    {{-- MFA is already enabled --}}
    <div class="card" style="border-color: var(--success);">
        <div class="card-header">
            <h2>✅ Two-Factor Authentication is Active</h2>
        </div>
        <p style="margin-bottom: 1rem; color: var(--text-muted);">
            Your account is protected with two-factor authentication. You will be prompted
            for a verification code each time you log in.
        </p>
        <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
            <strong>Last verified:</strong>
            {{ $mfaToken->last_verified_at ? $mfaToken->last_verified_at->diffForHumans() : 'Never' }}
        </p>
        <div style="display: flex; gap: 0.75rem;">
            <form action="{{ route('admin.mfa.regenerate') }}" method="POST"
                  onsubmit="return confirm('Regenerating recovery codes will invalidate your existing ones. Continue?')">
                @csrf
                <button type="submit" class="btn btn-warning">Regenerate Recovery Codes</button>
            </form>
            <form action="{{ route('admin.mfa.disable') }}" method="POST"
                  onsubmit="return confirm('Disable two-factor authentication? Your account will no longer require a verification code at login.')">
                @csrf
                <button type="submit" class="btn btn-danger">Disable 2FA</button>
            </form>
        </div>
    </div>

    {{-- Recovery Codes --}}
    @if($mfaToken->recovery_codes && count($mfaToken->recovery_codes) > 0)
    <div class="card">
        <div class="card-header">
            <h2>🔑 Recovery Codes</h2>
        </div>
        <p style="margin-bottom: 1rem; color: var(--text-muted); font-size: 0.85rem;">
            Save these one-time use recovery codes in a secure location. If you lose access
            to your authenticator app, you can use a recovery code to log in.
        </p>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-family: monospace; font-size: 0.9rem;">
            @foreach($mfaToken->recovery_codes as $code)
                <div style="padding: 0.5rem 0.75rem; background: var(--bg); border-radius: 4px; border: 1px solid var(--border); text-align: center;">
                    {{ $code }}
                </div>
            @endforeach
        </div>
        <div style="margin-top: 1rem;">
            <button class="btn btn-secondary" onclick="copyRecoveryCodes()">📋 Copy All Codes</button>
        </div>
    </div>
    @endif

@elseif($mfaToken && !$mfaToken->is_enabled)
    {{-- MFA token exists but not yet enabled -- show verification step --}}
    <div class="card">
        <div class="card-header"><h2>📱 Step 2: Verify Setup</h2></div>
        <p style="margin-bottom: 1rem; color: var(--text-muted);">
            Scan the QR code below with your authenticator app (e.g., Google Authenticator, Authy, 1Password),
            then enter the 6-digit code to verify the setup.
        </p>

        <div style="text-align: center; margin: 2rem 0;">
            <div id="qrcode" style="display: inline-block; padding: 1rem; background: white; border-radius: 8px;"></div>
        </div>

        <div style="text-align: center; margin-bottom: 1rem;">
            <p style="font-size: 0.8rem; color: var(--text-muted);">
                Or manually enter this key in your authenticator app:
            </p>
            <code class="mono" style="font-size: 1rem; padding: 0.5rem 1rem; display: inline-block; word-break: break-all;">
                {{ $mfaToken->secret }}
            </code>
        </div>

        <form action="{{ route('admin.mfa.verify') }}" method="POST" data-loading style="max-width: 320px; margin: 0 auto;">
            @csrf
            <div class="form-group">
                <label for="code">Verification Code</label>
                <input type="text" name="code" id="code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric"
                       autocomplete="one-time-code" required placeholder="000000"
                       style="text-align: center; font-size: 1.5rem; letter-spacing: 0.5em; font-family: monospace;">
                @error('code')
                    <div class="field-error visible">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; font-size: 1rem; padding: 0.75rem;">
                ✅ Verify & Enable 2FA
            </button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center;">
            <form action="{{ route('admin.mfa.cancel-setup') }}" method="POST"
                  onsubmit="return confirm('Cancel 2FA setup? The generated secret will be discarded.')">
                @csrf
                <button type="submit" class="btn btn-secondary">Cancel Setup</button>
            </form>
        </div>
    </div>

@else
    {{-- No MFA token exists -- show setup initiation --}}
    <div class="card">
        <div class="card-header"><h2>📱 Step 1: Set Up Two-Factor Authentication</h2></div>
        <p style="margin-bottom: 1rem; color: var(--text-muted);">
            Two-factor authentication adds an extra layer of security to your account.
            After enabling, you'll need to enter a verification code from your authenticator
            app in addition to your password when logging in.
        </p>

        <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 8px; padding: 1rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem;">You'll need:</h3>
            <ol style="margin: 0; padding-left: 1.2rem; color: var(--text-muted); font-size: 0.85rem; line-height: 1.8;">
                <li>An authenticator app (Google Authenticator, Authy, 1Password, etc.)</li>
                <li>Your camera to scan a QR code</li>
                <li>Your phone or device handy during login</li>
            </ol>
        </div>

        <form action="{{ route('admin.mfa.initiate') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-primary" style="font-size: 1rem; padding: 0.75rem 2rem;">
                Begin Setup →
            </button>
        </form>
    </div>
@endif
@endsection

@section('footer-scripts')
@if($mfaToken && !$mfaToken->is_enabled)
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const uri = '{{ $mfaToken->getTotpUri("Print Hub", auth()->user()->email) }}';
    new QRCode(document.getElementById('qrcode'), {
        text: uri,
        width: 220,
        height: 220,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
@endif
<script>
function copyRecoveryCodes() {
    const codes = {{ Js::from($mfaToken->recovery_codes ?? []) }};
    if (codes.length === 0) return;
    const text = codes.join('\n');
    navigator.clipboard.writeText(text).then(() => {
        showToast('Recovery codes copied to clipboard!', 'success');
    }).catch(() => {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('Recovery codes copied!', 'success');
    });
}
</script>
@endsection
