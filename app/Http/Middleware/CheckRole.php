<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userRole = $user->role;

        // Flatten roles in case they were passed as a single comma-separated string
        $expandedRoles = [];
        foreach ($roles as $role) {
            foreach (explode(',', $role) as $part) {
                $expandedRoles[] = trim($part);
            }
        }

        foreach ($expandedRoles as $allowedRole) {
            if ($userRole === $allowedRole) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
