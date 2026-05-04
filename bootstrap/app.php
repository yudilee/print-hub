<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'           => \App\Http\Middleware\CheckRole::class,
            'permission'     => \App\Http\Middleware\CheckPermission::class,
            'session.activity' => \App\Http\Middleware\UpdateSessionActivity::class,
            'auth.api-key'   => \App\Http\Middleware\AuthenticateApiKey::class,
            'force.tls'      => \App\Http\Middleware\ForceTls::class,
            'ip.whitelist'   => \App\Http\Middleware\IpWhitelist::class,
        ]);

        // Apply TLS enforcement to web routes in production
        $middleware->web(prepend: [
            \App\Http\Middleware\ForceTls::class,
        ]);

        // Apply IP whitelisting to API routes if configured
        $middleware->api(prepend: [
            \App\Http\Middleware\IpWhitelist::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
