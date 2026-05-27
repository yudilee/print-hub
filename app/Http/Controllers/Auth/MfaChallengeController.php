<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\MfaToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles the MFA challenge step during login.
 *
 * After a user successfully enters their password, if they have MFA
 * enabled, they are redirected to this controller to enter their
 * TOTP verification code before the session is established.
 */
class MfaChallengeController extends Controller
{
    /**
     * Show the MFA challenge form.
     */
    public function showChallenge()
    {
        // Ensure user has an active MFA session
        if (!session()->has('mfa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    /**
     * Verify the MFA code and complete login.
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        $userId = session('mfa:user:id');

        if (!$userId) {
            return redirect()->route('login')
                ->withErrors(['code' => 'Session expired. Please log in again.']);
        }

        $mfaToken = MfaToken::where('user_id', $userId)
            ->where('is_enabled', true)
            ->first();

        if (!$mfaToken) {
            // MFA was disabled between login and challenge
            session()->forget(['mfa:user:id', 'mfa:remember']);
            return redirect()->route('login');
        }

        // Check recovery code first
        if ($mfaToken->verifyRecoveryCode($request->code)) {
            $this->completeMfaLogin($userId);
            $mfaToken->update(['last_verified_at' => now()]);
            return redirect()->intended(route('admin.dashboard'))
                ->with('success', 'Recovery code accepted. Please set up a new authenticator if needed.');
        }

        // Check TOTP code
        if ($mfaToken->verifyTotp($request->code)) {
            $this->completeMfaLogin($userId);
            $mfaToken->update(['last_verified_at' => now()]);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'code' => 'Invalid verification code. Please try again.',
        ])->withInput();
    }

    /**
     * Complete the MFA-authenticated login.
     */
    private function completeMfaLogin(int $userId): void
    {
        Auth::loginUsingId($userId, session('mfa:remember', false));
        session()->forget(['mfa:user:id', 'mfa:remember']);
        session()->regenerate();
    }
}
