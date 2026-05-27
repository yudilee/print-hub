<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MfaToken;
use Illuminate\Http\Request;

/**
 * Controller for managing Two-Factor Authentication (TOTP-based MFA).
 *
 * Provides the setup flow: initiate → scan QR code → verify → enable,
 * as well as disable and recovery code regeneration.
 */
class MfaController extends Controller
{
    /**
     * Show the MFA setup page.
     */
    public function setup()
    {
        $mfaToken = MfaToken::where('user_id', auth()->id())->first();

        return view('admin.mfa.setup', compact('mfaToken'));
    }

    /**
     * Initiate MFA setup by generating a new secret and recovery codes.
     */
    public function initiate(Request $request)
    {
        $user = $request->user();

        // Delete any existing MFA token for this user
        MfaToken::where('user_id', $user->id)->delete();

        $mfaToken = MfaToken::create([
            'user_id'        => $user->id,
            'secret'         => MfaToken::generateSecret(),
            'is_enabled'     => false,
            'recovery_codes' => MfaToken::generateRecoveryCodes(8),
        ]);

        return redirect()->route('admin.mfa.setup')
            ->with('mfa_success', 'Scan the QR code with your authenticator app, then enter the verification code below.');
    }

    /**
     * Verify the TOTP code and enable MFA.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $mfaToken = MfaToken::where('user_id', $request->user()->id)->first();

        if (!$mfaToken) {
            return redirect()->route('admin.mfa.setup')
                ->withErrors(['code' => 'MFA setup not initiated. Please start over.']);
        }

        if ($mfaToken->verifyTotp($request->code)) {
            $mfaToken->update([
                'is_enabled'       => true,
                'last_verified_at' => now(),
            ]);

            return redirect()->route('admin.mfa.setup')
                ->with('mfa_success', '✅ Two-factor authentication has been enabled successfully!');
        }

        return redirect()->route('admin.mfa.setup')
            ->withErrors(['code' => 'Invalid verification code. Please try again.']);
    }

    /**
     * Disable MFA for the current user.
     */
    public function disable(Request $request)
    {
        $mfaToken = MfaToken::where('user_id', $request->user()->id)->first();

        if ($mfaToken) {
            $mfaToken->delete();
        }

        return redirect()->route('admin.mfa.setup')
            ->with('mfa_success', 'Two-factor authentication has been disabled.');
    }

    /**
     * Cancel an incomplete MFA setup (discard the generated secret).
     */
    public function cancelSetup(Request $request)
    {
        MfaToken::where('user_id', $request->user()->id)
            ->where('is_enabled', false)
            ->delete();

        return redirect()->route('admin.mfa.setup')
            ->with('mfa_success', 'MFA setup cancelled.');
    }

    /**
     * Regenerate recovery codes for an active MFA token.
     */
    public function regenerate(Request $request)
    {
        $mfaToken = MfaToken::where('user_id', $request->user()->id)
            ->where('is_enabled', true)
            ->firstOrFail();

        $mfaToken->update([
            'recovery_codes' => MfaToken::generateRecoveryCodes(8),
        ]);

        return redirect()->route('admin.mfa.setup')
            ->with('mfa_success', 'New recovery codes generated. Save them securely!');
    }
}
