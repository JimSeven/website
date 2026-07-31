<?php

declare(strict_types=1);

/**
 * Baseline security headers (Issue #8).
 *
 * This is the single source of truth for the header values. The package's
 * service provider is intentionally NOT auto-discovered (see composer.json
 * `extra.laravel.dont-discover`) so its verbose defaults never merge in here —
 * otherwise the Permissions-Policy builder would emit ~40 directives instead of
 * the three below. Headers are applied by App\Http\Middleware\SecurityHeaders.
 *
 * CSP is deliberately omitted: it is judgement-dependent, frontend-only and
 * excludes /cp, so it lives in a separate package (spatie/laravel-csp) and
 * remains an ongoing /owasp-review surface — not part of this baseline.
 */
return [
    'x-content-type-options' => 'nosniff',

    'x-frame-options' => 'DENY',

    'x-xss-protection' => '0',

    'referrer-policy' => 'strict-origin-when-cross-origin',

    /**
     * Keys read unconditionally by the package's SecureHeaders::miscellaneous().
     * Empty strings are filtered out, so these headers are not emitted.
     */
    'x-download-options' => '',
    'x-permitted-cross-domain-policies' => '',
    'server' => '',

    /**
     * HTTP Strict Transport Security.
     *
     * Only emitted over HTTPS (enforced by the middleware) — never over plain
     * HTTP, per "nur mit vollständigem HTTPS".
     */
    'hsts' => [
        'enable' => true,

        'max-age' => 63072000,

        'include-sub-domains' => true,

        'preload' => true,
    ],

    /**
     * Permissions-Policy. Each key is emitted verbatim by the builder, so only
     * the directives we want to lock down are listed here.
     */
    'permissions-policy' => [
        'enable' => true,

        'geolocation' => ['none' => true],

        'camera' => ['none' => true],

        'microphone' => ['none' => true],
    ],
];
