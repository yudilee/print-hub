<?php

namespace App\Http\Middleware;

use App\Models\UserSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UpdateSessionActivity
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $sessionId = session()->getId();
            $userId = Auth::id();
            
            $sessionExists = UserSession::where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->exists();
            
            if (!$sessionExists) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                
                return redirect()->route('login')
                    ->with('warning', 'Your session was terminated from another device.');
            }
            
            $cacheKey = 'session_activity_' . $sessionId;
            
            if (!cache()->has($cacheKey)) {
                UserSession::where('session_id', $sessionId)
                    ->where('user_id', $userId)
                    ->update(['last_active_at' => now()]);
                    
                cache()->put($cacheKey, true, 60);
            }
        }

        return $next($request);
    }
}
