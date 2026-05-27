<?php

namespace App\Http\Middleware;

use App\Services\GroupPolicyEnforcer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceTls
{
    private GroupPolicyEnforcer $policyEnforcer;

    public function __construct(?GroupPolicyEnforcer $policyEnforcer = null)
    {
        $this->policyEnforcer = $policyEnforcer ?? app(GroupPolicyEnforcer::class);
    }

    /**
     * Handle an incoming request.
     *
     * Redirects HTTP to HTTPS based on group policy or production environment.
     * The GroupPolicyEnforcer reads the `force_tls` policy from the settings table.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Enforce session timeout from group policy
        $this->policyEnforcer->enforceSessionTimeout();

        // Check group policy TLS enforcement first
        $result = $this->policyEnforcer->enforceTls(
            $request->secure(),
            $request->getRequestUri()
        );

        if ($result['redirect'] !== null) {
            return redirect($result['redirect']);
        }

        // Fallback: enforce TLS in production even if policy is not set
        if (! $request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }

        /** @var Response $response */
        $response = $next($request);

        // Add HSTS header (Task 4.4)
        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
