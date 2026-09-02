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
        // Enforce HTTPS URLs in production or when APP_URL is HTTPS
        if ($this->app->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Super-admin bypasses all Gate checks
        Gate::before(function ($user, $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
        });

        // Register dynamic rate limiters for client and agent APIs
        \Illuminate\Support\Facades\RateLimiter::for('client-api', function (\Illuminate\Http\Request $request) {
            $limit = (int) (\App\Models\Setting::getValue('max_requests_per_minute_client', 60) ?: 60);
            $key = $request->header('X-API-Key') ?? $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($key);
        });

        \Illuminate\Support\Facades\RateLimiter::for('agent-api', function (\Illuminate\Http\Request $request) {
            $limit = (int) (\App\Models\Setting::getValue('max_requests_per_minute_agent', 120) ?: 120);
            $key = $request->bearerToken() ?? $request->header('X-Agent-Key') ?? $request->ip();
            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by($key);
        });

        // Register a Gate for each permission in the config
        foreach (PermissionConfig::allPermissions() as $permission) {
            Gate::define($permission, function ($user) use ($permission) {
                return PermissionConfig::hasPermission($user->role ?? 'viewer', $permission);
            });
        }
    }
}
