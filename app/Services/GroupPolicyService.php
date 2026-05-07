<?php

namespace App\Services;

use App\Models\Setting;

/**
 * GroupPolicyService reads and manages enterprise group policies.
 *
 * Policies are stored in the `settings` table with a `policy_` prefix.
 * Supported policies:
 *   - force_tls (boolean)          – Enforce TLS for all connections
 *   - min_key_length (int)         – Minimum allowed API key length
 *   - allowed_auth_providers (array) – List of allowed SSO providers
 *   - session_timeout_minutes (int) – Max admin session idle time
 *   - audit_log_retention_days (int) – Days to retain activity logs
 */
class GroupPolicyService
{
    /**
     * Default policy values.
     */
    private array $defaults = [
        'force_tls'               => false,
        'min_key_length'          => 32,
        'allowed_auth_providers'  => ['local'],
        'session_timeout_minutes' => 480,
        'audit_log_retention_days' => 90,
    ];

    /**
     * Get a single policy value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settingKey = 'policy_' . $key;
        $fallback = $default ?? $this->defaults[$key] ?? null;

        return Setting::getValue($settingKey, $fallback);
    }

    /**
     * Set a single policy value.
     */
    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        Setting::setValue('policy_' . $key, $value, $type);
    }

    /**
     * Get all policies as a key-value array.
     */
    public function all(): array
    {
        $policies = [];
        foreach (array_keys($this->defaults) as $key) {
            $policies[$key] = $this->get($key);
        }
        return $policies;
    }

    /**
     * Check whether TLS is enforced.
     */
    public function isTlsForced(): bool
    {
        return (bool) $this->get('force_tls', false);
    }

    /**
     * Get the minimum allowed API key length.
     */
    public function getMinKeyLength(): int
    {
        return (int) $this->get('min_key_length', 32);
    }

    /**
     * Get the list of allowed authentication providers.
     *
     * @return string[]
     */
    public function getAllowedAuthProviders(): array
    {
        $providers = $this->get('allowed_auth_providers', ['local']);
        return is_array($providers) ? $providers : ['local'];
    }

    /**
     * Get the session timeout in minutes.
     */
    public function getSessionTimeoutMinutes(): int
    {
        return (int) $this->get('session_timeout_minutes', 480);
    }

    /**
     * Get the audit log retention period in days.
     */
    public function getAuditLogRetentionDays(): int
    {
        return (int) $this->get('audit_log_retention_days', 90);
    }
}
