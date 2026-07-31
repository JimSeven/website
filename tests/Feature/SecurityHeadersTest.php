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
    'X-Frame-Options' => 'DENY',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Permissions-Policy' => 'geolocation=(), camera=(), microphone=()',
    'X-XSS-Protection' => '0',
];

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
