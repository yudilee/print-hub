@extends('admin.layout')
@section('title', 'Two-Factor Authentication Setup')

@section('content')
<x-breadcrumb :items="[['label' => 'Two-Factor Authentication']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">Two-Factor Authentication (2FA / TOTP)</h2>
        <p class="text-xs text-slate-400">Protect administrative access using hardware tokens or authenticator apps</p>
    </div>
</div>

@if($mfaToken && $mfaToken->is_enabled)
    <div class="bg-slate-900 border border-emerald-500/30 rounded-2xl p-6 shadow-xs mb-6">
        <div class="flex items-center gap-3 mb-3">
            <span class="w-8 h-8 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-bold">✓</span>
            <div>
                <h3 class="text-sm font-bold text-white">2FA Protection Active</h3>
                <span class="text-xs text-slate-400">Your account requires a numeric authenticator code on each sign-in attempt.</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 pt-4 border-t border-slate-800">
            <form action="{{ route('admin.mfa.regenerate') }}" method="POST" onsubmit="return confirm('Regenerating codes invalidates old ones. Proceed?')">
                @csrf
                <button type="submit" class="btn-warning btn-sm">Regenerate Recovery Codes</button>
            </form>
            <form action="{{ route('admin.mfa.disable') }}" method="POST" onsubmit="return confirm('Disable 2FA protection?')">
                @csrf
                <button type="submit" class="btn-danger btn-sm">Disable 2FA</button>
            </form>
        </div>
    </div>

    @if($mfaToken->recovery_codes && count($mfaToken->recovery_codes) > 0)
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Emergency Recovery Codes</h3>
        <p class="text-xs text-slate-400 mb-4">Store these one-time codes in a secure password vault.</p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 font-mono text-xs text-blue-400 mb-4">
            @foreach($mfaToken->recovery_codes as $code)
                <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-center">
                    {{ $code }}
                </div>
            @endforeach
        </div>

        <button class="btn-secondary btn-sm" onclick="copyRecoveryCodes()">📋 Copy All Codes</button>
    </div>
    @endif

@elseif($mfaToken && !$mfaToken->is_enabled)
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xs max-w-lg mx-auto">
        <h3 class="text-sm font-bold text-white mb-2 text-center">Step 2: Scan QR & Verify</h3>
        <p class="text-xs text-slate-400 text-center mb-6">Scan the code with Google Authenticator, Authy, or 1Password</p>

        <div class="flex justify-center mb-6">
            <div id="qrcode" class="p-3 bg-white rounded-2xl shadow-xl"></div>
        </div>

        <div class="text-center mb-6">
            <span class="text-xs text-slate-500 block mb-1">Manual Secret Key</span>
            <code class="px-3 py-1 rounded-xl bg-slate-950 border border-slate-800 text-blue-400 text-xs font-mono">
                {{ $mfaToken->secret }}
            </code>
        </div>

        <form action="{{ route('admin.mfa.verify') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-400 mb-1 text-center">Enter 6-Digit Authenticator Code</label>
                <input type="text" name="code" pattern="[0-9]{6}" maxlength="6" inputmode="numeric" required placeholder="000000"
                    class="w-full text-center py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-lg font-mono text-white tracking-widest focus:outline-none focus:border-blue-500">
            </div>

            <button type="submit" class="btn-primary w-full justify-center">✅ Verify & Activate 2FA</button>
        </form>

        <div class="text-center mt-4">
            <form action="{{ route('admin.mfa.cancel-setup') }}" method="POST">
                @csrf
                <button type="submit" class="text-xs text-slate-500 hover:text-slate-400">Cancel Setup</button>
            </form>
        </div>
    </div>
@else
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xs max-w-lg mx-auto text-center">
        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 border border-blue-500/30 flex items-center justify-center text-xl mx-auto mb-4">
            🛡️
        </div>
        <h3 class="text-base font-bold text-white mb-2">Enhance Administrator Security</h3>
        <p class="text-xs text-slate-400 mb-6">
            Add two-factor verification to your Print Hub account to guard against credential theft and unauthorized job dispatches.
        </p>

        <form action="{{ route('admin.mfa.initiate') }}" method="POST">
            @csrf
            <button type="submit" class="btn-primary">Begin Setup →</button>
        </form>
    </div>
@endif

@if($mfaToken && !$mfaToken->is_enabled)
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const uri = '{{ $mfaToken->getTotpUri("Print Hub", auth()->user()->email) }}';
    new QRCode(document.getElementById('qrcode'), {
        text: uri,
        width: 180,
        height: 180,
        correctLevel: QRCode.CorrectLevel.M
    });
});
</script>
@endif
<script>
function copyRecoveryCodes() {
    const codes = {{ Js::from($mfaToken->recovery_codes ?? []) }};
    navigator.clipboard.writeText(codes.join('\n'));
    alert('Recovery codes copied to clipboard!');
}
</script>
@endsection
