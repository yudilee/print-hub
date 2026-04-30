<?php

namespace App\Http\Middleware;

use App\Auth\PermissionConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please login to access this page.');
        }

        $userRole = $user->role ?? 'viewer';

        // Flatten permissions in case they were passed as comma-separated
        $expandedPermissions = [];
        foreach ($permissions as $perm) {
            $parts = explode(',', $perm);
            foreach ($parts as $part) {
                $expandedPermissions[] = trim($part);
            }
        }

        // Check if user has any of the required permissions
        foreach ($expandedPermissions as $permission) {
            if (PermissionConfig::hasPermission($userRole, $permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
