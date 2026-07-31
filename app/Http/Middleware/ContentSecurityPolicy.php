<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Csp\AddCspHeaders;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emits the frontend Content-Security-Policy (Issue #21, checklist §G).
 *
 * Wraps spatie/laravel-csp's AddCspHeaders so the Control Panel is excluded: /cp is
 * an Inertia/Vue 3 app and Live Preview renders drafts in iframes/modules, both of
 * which the strict frontend policy (App\Csp\FrontendPolicy) would break. Every CP
 * route is named statamic.cp.* and served under the configured cp route prefix, so
 * we skip on either signal and delegate everything else to spatie — which still
 * honours the csp.enabled flag and its own Vite-hot-reload skip.
 */
final class ContentSecurityPolicy
{
    public function __construct(private readonly AddCspHeaders $addCspHeaders) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isControlPanelRequest($request)) {
            return $next($request);
        }

        $response = $this->addCspHeaders->handle($request, $next);

        // On the frontend, frame-ancestors 'self' is the framing control and
        // supersedes X-Frame-Options (set globally by SecurityHeaders, Issue #8).
        // Drop XFO wherever CSP actually shipped: DENY would also forbid the
        // *same-origin* iframe that Statamic Live Preview renders the frontend in,
        // breaking preview — while frame-ancestors 'self' still blocks cross-origin
        // framing. When CSP is disabled the #8 baseline (XFO: DENY) stays untouched.
        if ($response->headers->has('Content-Security-Policy')) {
            $response->headers->remove('X-Frame-Options');
        }

        return $response;
    }

    private function isControlPanelRequest(Request $request): bool
    {
        if ($request->routeIs('statamic.cp.*')) {
            return true;
        }

        $cpRoute = mb_trim((string) config('statamic.cp.route', 'cp'), '/');

        return $cpRoute !== '' && $request->is($cpRoute, $cpRoute.'/*');
    }
}
