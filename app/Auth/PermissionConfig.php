<?php

namespace App\Auth;

class PermissionConfig
{
    /**
     * Role definitions with their permissions.
     */
    public const ROLES = [
        'super-admin' => [
            'display'     => 'Super Admin',
            'description' => 'Full system access across all companies and branches',
            'permissions' => ['*'],
        ],
        'company-admin' => [
            'display'     => 'Company Admin',
            'description' => 'Manages all branches within their company',
            'permissions' => [
                'dashboard.view',
                'company.view',
                'branches.view', 'branches.manage',
                'agents.view', 'agents.manage',
                'profiles.view', 'profiles.manage',
                'jobs.view', 'jobs.manage', 'jobs.retry',
                'users.view', 'users.manage',
                'templates.view',
                'template-defaults.manage',
                'activity-logs.view',
                'clients.view',
            ],
        ],
        'branch-admin' => [
            'display'     => 'Branch Admin',
            'description' => 'Manages a specific branch within their company',
            'permissions' => [
                'dashboard.view',
                'branches.view',
                'branch.manage',
                'agents.view', 'agents.manage',
                'profiles.view', 'profiles.manage',
                'jobs.view', 'jobs.manage', 'jobs.retry',
                'users.view',
                'templates.view',
                'template-defaults.manage',
            ],
        ],
        'branch-operator' => [
            'display'     => 'Branch Operator',
            'description' => 'Day-to-day printing operations at their branch',
            'permissions' => [
                'dashboard.view',
                'agents.view',
                'profiles.view',
                'jobs.view', 'jobs.retry',
                'templates.view',
            ],
        ],
        'viewer' => [
            'display'     => 'Viewer',
            'description' => 'Read-only access to dashboard and job history',
            'permissions' => [
                'dashboard.view',
                'jobs.view',
                'agents.view',
            ],
        ],
    ];

    /**
     * Check if a role has a specific permission.
     */
    public static function hasPermission(string $role, string $permission): bool
    {
        $roleConfig = self::ROLES[$role] ?? null;
        if (!$roleConfig) return false;

        $permissions = $roleConfig['permissions'];

        // Wildcard: super-admin has all permissions
        if (in_array('*', $permissions)) return true;

        return in_array($permission, $permissions);
    }

    /**
     * Get all available role keys.
     */
    public static function roleKeys(): array
    {
        return array_keys(self::ROLES);
    }

    /**
     * Get role display names for dropdowns.
     */
    public static function roleOptions(): array
    {
        $options = [];
        foreach (self::ROLES as $key => $config) {
            $options[$key] = $config['display'];
        }
        return $options;
    }

    /**
     * Get all unique permissions.
     */
    public static function allPermissions(): array
    {
        $perms = [];
        foreach (self::ROLES as $config) {
            foreach ($config['permissions'] as $p) {
                if ($p !== '*') $perms[] = $p;
            }
        }
        return array_unique($perms);
    }
}
