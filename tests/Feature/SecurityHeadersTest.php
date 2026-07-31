<?php

declare(strict_types=1);

/**
 * Baseline security headers (Issue #8).
 *
 * Regression coverage for the static headers emitted by the global
 * SecurityHeaders middleware on every response, including the Control Panel.
 * CSP is intentionally out of scope here (handled separately).
 */
$staticHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
    'X-XSS-Protection' => '0',
];

// X-Frame-Options is asserted separately: it stays DENY on the CP, but the
// ContentSecurityPolicy middleware drops it on the frontend, where the strict
// CSP's frame-ancestors 'self' supersedes it (Issue #21).

$routes = [
    'frontend' => 'up',
    'control panel' => 'cp/auth/login',
];

foreach ($routes as $label => $uri) {
    foreach ($staticHeaders as $header => $expected) {
        test("sets {$header} on the {$label} route", function () use ($uri, $header, $expected) {
            $response = $this->get($uri);

            expect($response->headers->get($header))->toBe($expected);
        });
    }
}

test('keeps X-Frame-Options: DENY on the control panel', function () {
    $response = $this->get('cp/auth/login');

    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
});

test('drops X-Frame-Options on the frontend in favour of CSP frame-ancestors', function () {
    $response = $this->get('up');

    expect($response->headers->has('X-Frame-Options'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
});

test('does not send HSTS over plain HTTP', function () {
    $response = $this->get('http://localhost/up');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('sends the full HSTS policy over HTTPS', function () {
    $response = $this->get('https://localhost/up');

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=63072000; includeSubDomains; preload');
});

test('sends HSTS behind a TLS-terminating proxy via X-Forwarded-Proto', function () {
    $response = $this->get('http://localhost/up', ['X-Forwarded-Proto' => 'https']);

    expect($response->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=63072000; includeSubDomains; preload');
});
