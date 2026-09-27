<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
    // Trust every proxy: php artisan serve only ever binds to 127.0.0.1, so
    // the only way in is through whatever's in front of it (the cloudflared
    // tunnel today). Without this, Laravel never sees the tunnel's
    // X-Forwarded-Proto: https header, so it thinks every request is plain
    // HTTP -- route()/url() then generate http:// links on an https:// page
    // (the browser's "not secure" warning on login), and the session cookie
    // and the CSRF token end up disagreeing on the scheme, which is what
    // actually caused the "page expired" (419) right after logging in.
    $middleware->trustProxies(at: '*');
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'nocache' => \App\Http\Middleware\NoCacheMiddleware::class,
    ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
