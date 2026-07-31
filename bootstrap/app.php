<?php

declare(strict_types=1);

use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Laravel Cloud's load balancer TLS is terminated at the proxy,
        // so the app sees plain HTTP + X-Forwarded-Proto. Trust it, otherwise
        // $request->secure() is always false and HSTS would never ship.
        $middleware->trustProxies(at: '*');

        // Frontend Content-Security-Policy (Issue #21). Appended globally but skips
        // /cp internally — the CP is an Inertia/Vue app + Live Preview that a strict
        // policy would break. Ordered BEFORE SecurityHeaders so its response phase
        // runs last: it removes the X-Frame-Options that SecurityHeaders sets, which
        // frame-ancestors 'self' supersedes on the frontend.
        $middleware->append(ContentSecurityPolicy::class);

        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
