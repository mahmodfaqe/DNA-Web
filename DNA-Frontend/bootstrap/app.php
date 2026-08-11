<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind the Apache container the app only ever sees the proxy address,
        // so trust the forwarded headers to get real client IPs in the logs and
        // correct scheme detection for generated URLs.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Route model binding runs before unprioritised route middleware, so a
        // stale /en/result/{id} would 404 before the language was known and the
        // not-found page would come back in the wrong one. The language is a
        // property of the URL, not of what the URL resolves to, so settle it
        // first.
        $middleware->prependToPriorityList(
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetLocale::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('health') || $request->expectsJson(),
        );
    })->create();
