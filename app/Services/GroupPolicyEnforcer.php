<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * GroupPolicyEnforcer reads enterprise group policies from GroupPolicyService
 * and enforces them across the application.
 *
 * Supported policies:
 *   - force_tls               Redirect HTTP to HTTPS
 *   - session_timeout_minutes Override session lifetime
 *   - min_key_length          Reject API keys below minimum length
 *   - allowed_auth_providers  Whitelist SSO providers
 */
class GroupPolicyEnforcer
{
    private GroupPolicyService $policy;

    public function __construct(?GroupPolicyService $policy = null)
    {
        $this->policy = $policy ?? app(GroupPolicyService::class);
    }

    /**
     * Enforce TLS by checking if the request is secure.
     *
     * @param  bool   $isSecure  Whether the current request is over HTTPS.
     * @param  string $requestUri The request URI for redirect.
     * @return array{redirect: string|null}  A redirect URL if enforcement is needed.
     */
    public function enforceTls(bool $isSecure, string $requestUri): array
    {
        if (! $this->policy->isTlsForced()) {
            return ['redirect' => null];
        }

        if (! $isSecure) {
            $secureUrl = 'https://' . request()->getHttpHost() . $requestUri;
            Log::info('GroupPolicyEnforcer: Redirecting HTTP to HTTPS', [
                'from' => $requestUri,
                'to'   => $secureUrl,
            ]);
            return ['redirect' => $secureUrl];
        }

        return ['redirect' => null];
    }

    /**
     * Enforce session timeout by overriding the session lifetime config.
     *
     * Call this early in the request lifecycle (e.g., from a middleware or
     * service provider) to ensure the session TTL matches the policy.
     */
    public function enforceSessionTimeout(): void
    {
        $timeoutMinutes = $this->policy->getSessionTimeoutMinutes();

        // Override the session lifetime configuration
        Config::set('session.lifetime', $timeoutMinutes);

        Log::debug('GroupPolicyEnforcer: Session timeout enforced', [
            'lifetime_minutes' => $timeoutMinutes,
        ]);
    }

    /**
     * Enforce minimum API key length.
     *
     * @param  string $rawKey The raw API key to validate.
     * @return array{valid: bool, error: string|null}
     */
    public function enforceMinKeyLength(string $rawKey): array
    {
        $minLength = $this->policy->getMinKeyLength();

        if (strlen($rawKey) < $minLength) {
            return [
                'valid' => false,
                'error' => "API key must be at least {$minLength} characters long.",
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Enforce allowed authentication providers.
     *
     * @param  string $provider The SSO provider identifier (e.g., 'saml2', 'azure', 'okta').
     * @return array{allowed: bool, error: string|null}
     */
    public function enforceAllowedAuthProviders(string $provider): array
    {
        $allowed = $this->policy->getAllowedAuthProviders();

        // 'local' is always allowed for username/password login
        if ($provider === 'local') {
            return ['allowed' => true, 'error' => null];
        }

        if (! in_array($provider, $allowed, true)) {
            return [
                'allowed' => false,
                'error'   => "Authentication provider '{$provider}' is not allowed by group policy. Allowed: " . implode(', ', $allowed) . '.',
            ];
        }

        return ['allowed' => true, 'error' => null];
    }

    /**
     * Get the underlying policy service instance.
     */
    public function getPolicyService(): GroupPolicyService
    {
        return $this->policy;
    }
}
