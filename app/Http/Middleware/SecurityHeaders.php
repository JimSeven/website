<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Bepsvpt\SecureHeaders\SecureHeaders;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the baseline security headers (Issue #8) to every response,
 * including the Control Panel.
 *
 * Wraps bepsvpt/secure-headers rather than using its bundled middleware so we
 * can gate Strict-Transport-Security on HTTPS: HSTS must never be sent over
 * plain HTTP ("nur mit vollständigem HTTPS").
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /** @var array<string, mixed> $config */
        $config = config('secure-headers', []);

        if (! $request->secure()) {
            unset($config['hsts']);
        }

        foreach ((new SecureHeaders($config))->headers() as $header => $value) {
            $response->headers->set($header, $value, true);
        }

        // SecureHeaders::headers() runs the set through array_filter(), which
        // drops the falsy string '0' — so X-XSS-Protection: 0 never survives.
        // Set it explicitly to keep config as the single source of truth.
        if (isset($config['x-xss-protection']) && $config['x-xss-protection'] !== '') {
            $response->headers->set('X-XSS-Protection', $config['x-xss-protection'], true);
        }

        return $response;
    }
}
