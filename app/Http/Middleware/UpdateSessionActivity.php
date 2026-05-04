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

            UserSession::where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->update(['last_active_at' => now()]);

            $cacheKey = 'session_activity_' . $sessionId;

            if (!cache()->has($cacheKey)) {
                cache()->put($cacheKey, true, 60);
            }
        }

        return $next($request);
    }
}
