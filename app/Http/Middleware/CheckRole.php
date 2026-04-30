<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    protected array $roleHierarchy = [
        'admin' => ['admin', 'user'],
        'user' => ['user'],
    ];

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userRole = $user->role ?? 'user';

        // Flatten roles in case they were passed as a single comma-separated string
        $expandedRoles = [];
        foreach ($roles as $role) {
            $parts = explode(',', $role);
            foreach ($parts as $part) {
                $expandedRoles[] = trim($part);
            }
        }

        foreach ($expandedRoles as $allowedRole) {
            if ($this->userHasRole($userRole, $allowedRole)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }

    protected function userHasRole(string $userRole, string $requiredRole): bool
    {
        if ($userRole === $requiredRole) return true;
        $allowedRoles = $this->roleHierarchy[$userRole] ?? ['user'];
        return in_array($requiredRole, $allowedRoles);
    }
}
