<?php

namespace App\Providers;

use App\Auth\PermissionConfig;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super-admin bypasses all Gate checks
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Register a Gate for each permission in the config
        foreach (PermissionConfig::allPermissions() as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return PermissionConfig::hasPermission($user->role ?? 'viewer', $permission);
            });
        }
    }
}
