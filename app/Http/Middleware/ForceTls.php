<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceTls
{
    /**
     * Handle an incoming request.
     *
     * Redirects HTTP to HTTPS in production environments.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        return $next($request);
    }
}
