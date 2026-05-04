<?php

namespace App\Http\Middleware;

use App\Models\ClientApp;
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
 */
class AuthenticateApiKey
{
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

        // Track last used timestamp
        $app->update(['last_used_at' => now()]);

        // Make the resolved app available to controllers
        $request->attributes->set('client_app', $app);

        return $next($request);
    }
}
