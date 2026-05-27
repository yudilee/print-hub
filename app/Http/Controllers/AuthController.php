<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        
        if ($user && Hash::check($request->password, $user->password)) {
            // Check if user has MFA enabled
            $mfaToken = \App\Models\MfaToken::where('user_id', $user->id)
                ->where('is_enabled', true)
                ->first();

            if ($mfaToken) {
                // Store user ID in session and redirect to MFA challenge
                session(['mfa:user:id' => $user->id, 'mfa:remember' => $request->boolean('remember')]);
                return redirect()->route('mfa.challenge');
            }

            $this->completeLogin($user, $request);
            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    protected function completeLogin($user, Request $request): void
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        \App\Models\UserSession::where('user_id', $user->id)
            ->update(['is_current' => false]);

        \App\Models\UserSession::updateOrCreate(
            ['session_id' => session()->getId()],
            [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_current' => true,
                'last_active_at' => now(),
            ]
        );
    }

    public function logout(Request $request)
    {
        \App\Models\UserSession::where('session_id', session()->getId())
            ->update(['is_current' => false]);
        
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
