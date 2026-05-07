<?php

namespace App\Http\Middleware;

use App\Models\ClientApp;
use App\Models\PrintAgent;
use App\Models\Setting;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ThrottleApiKeys applies per-API-key rate limits to API routes.
 *
 * For client apps (X-API-Key header), it reads the limit from the
 * `max_requests_per_minute_client` setting. For print agents (Bearer token),
 * it reads from `max_requests_per_minute_agent`.
 *
 * When the limit is exceeded, a 429 Too Many Requests response is returned
 * with a Retry-After header. All responses also include X-RateLimit-*
 * headers via Laravel's built-in RateLimiter.
 */
class ThrottleApiKeys
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Determine the API key and entity type
        $apiKey = $request->header('X-API-Key');
        $bearerToken = $request->bearerToken();

        if ($apiKey) {
            // Client app rate limiting
            $limit = Setting::getValue('max_requests_per_minute_client', 60);
            $keyType = 'client';
            $key = 'client-api:' . md5($apiKey);
        } elseif ($bearerToken) {
            // Agent rate limiting
            $limit = Setting::getValue('max_requests_per_minute_agent', 120);
            $keyType = 'agent';
            $key = 'agent-api:' . md5($bearerToken);
        } else {
            // No API key — apply a default conservative limit
            $limit = Setting::getValue('max_requests_per_minute_client', 60);
            $keyType = 'anonymous';
            $key = 'anonymous:' . $request->ip();
        }

        $limiter = app(RateLimiter::class);
        $keyName = 'throttle-api-key:' . $key;

        // Track the attempt
        $executed = $limiter->attempt(
            $keyName,
            $limit,
            function () {},
            60 // 1 minute decay
        );

        // Get remaining attempts and reset time
        $remaining = $limiter->remaining($keyName, $limit);
        $resetAt = $limiter->availableIn($keyName);

        if (! $executed) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Too many requests. Please slow down.',
                ],
            ], 429, [
                'Retry-After'         => $resetAt,
                'X-RateLimit-Limit'    => $limit,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset'    => now()->addSeconds($resetAt)->unix(),
            ]);
        }

        $response = $next($request);

        // Add rate limit headers to the response
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $remaining - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addSeconds($resetAt)->unix());

        return $response;
    }
}
