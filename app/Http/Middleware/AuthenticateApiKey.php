<?php

namespace App\Http\Middleware;

use App\Models\ClientApp;
use App\Services\GroupPolicyEnforcer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests from client applications using the X-API-Key header.
 *
 * On success, the resolved ClientApp is stored in the request attributes
 * so controllers can access it without repeating the lookup:
 *
 *   $app = $request->attributes->get('client_app');
 *
 * Also enforces the `min_key_length` group policy by rejecting keys that
 * are shorter than the configured minimum length.
 */
class AuthenticateApiKey
{
    private GroupPolicyEnforcer $policyEnforcer;

    public function __construct(?GroupPolicyEnforcer $policyEnforcer = null)
    {
        $this->policyEnforcer = $policyEnforcer ?? app(GroupPolicyEnforcer::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key');

        if (! $key) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MISSING_API_KEY',
                    'message' => 'Provide a valid X-API-Key header.',
                ],
            ], 401);
        }

        // Enforce minimum key length from group policy
        $keyLengthCheck = $this->policyEnforcer->enforceMinKeyLength($key);
        if (! $keyLengthCheck['valid']) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'KEY_TOO_SHORT',
                    'message' => $keyLengthCheck['error'],
                ],
            ], 401);
        }

        $app = ClientApp::findByKey($key);

        if (! $app || ! $app->is_active) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_API_KEY',
                    'message' => 'The provided API key is invalid or inactive.',
                ],
            ], 401);
        }

        // Enforce client app IP whitelist if configured
        if ($app->allowed_ips && is_array($app->allowed_ips) && count($app->allowed_ips) > 0) {
            $clientIp = $request->ip();
            $matched = false;
            foreach ($app->allowed_ips as $allowed) {
                if ($this->ipMatches($clientIp, $allowed)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'IP_NOT_ALLOWED',
                        'message' => 'Access denied: Client IP not whitelisted.',
                    ],
                ], 403);
            }
        }

        // Track last used timestamp
        $app->update(['last_used_at' => now()]);

        // Make the resolved app available to controllers
        $request->attributes->set('client_app', $app);

        return $next($request);
    }

    /**
     * Check if an IP matches an allowed entry (supports CIDR notation).
     */
    private function ipMatches(string $ip, string $allowed): bool
    {
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
