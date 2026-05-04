<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelist
{
    /**
     * Handle an incoming request.
     *
     * Checks the client IP against a configured whitelist.
     * Supports CIDR notation (e.g., 192.168.1.0/24).
     * If the whitelist is empty, all IPs are allowed.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $whitelist = config('app.api_ip_whitelist', []);

        if (empty($whitelist)) {
            return $next($request);
        }

        $ip = $request->ip();

        foreach ($whitelist as $allowed) {
            if ($this->ipMatches($ip, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Access denied: IP not whitelisted');
    }

    /**
     * Check if an IP matches an allowed entry (supports CIDR notation).
     */
    private function ipMatches(string $ip, string $allowed): bool
    {
        // Support CIDR notation (e.g., 192.168.1.0/24)
        if (str_contains($allowed, '/')) {
            [$subnet, $bits] = explode('/', $allowed, 2);
            $bits = (int) $bits;

            $ipLong    = ip2long($ip);
            $subnetLong = ip2long($subnet);

            if ($ipLong === false || $subnetLong === false) {
                return false;
            }

            $mask = -1 << (32 - $bits);
            $subnetLong &= $mask;

            return ($ipLong & $mask) === $subnetLong;
        }

        return $ip === $allowed;
    }
}
