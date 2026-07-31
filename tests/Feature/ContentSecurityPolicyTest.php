<?php

declare(strict_types=1);

/**
 * Frontend Content-Security-Policy (Issue #21, checklist §G).
 *
 * Regression coverage for the policy emitted by App\Http\Middleware\ContentSecurityPolicy:
 * strict default-src 'self' on the frontend, and never on /cp (the Inertia/Vue
 * Control Panel + Live Preview, which a strict policy would break).
 */
test('emits a strict CSP on the frontend', function () {
    $csp = $this->get('up')->headers->get('Content-Security-Policy');

    expect($csp)->not->toBeNull()
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("frame-ancestors 'self'")
        ->and($csp)->toContain("form-action 'self'")
        ->and($csp)->toContain("object-src 'none'");
});

test('never weakens the frontend CSP with unsafe-inline or unsafe-eval', function () {
    $csp = $this->get('up')->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('unsafe-inline')
        ->and($csp)->not->toContain('unsafe-eval');
});

test('carries a per-request nonce on script and style so inline tags never need unsafe-inline', function () {
    $csp = $this->get('up')->headers->get('Content-Security-Policy');

    expect($csp)->toMatch("/script-src[^;]*'nonce-/")
        ->and($csp)->toMatch("/style-src[^;]*'nonce-/");
});

test('does not send CSP on the control panel', function () {
    $response = $this->get('cp/auth/login');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('can be disabled via config', function () {
    config(['csp.enabled' => false]);

    expect($this->get('up')->headers->has('Content-Security-Policy'))->toBeFalse();
});
