<?php

declare(strict_types=1);

/**
 * Config/session baseline hardening (Issue #9).
 *
 * Covers the two mechanizable guarantees: 2FA is enforced for every user on the
 * public /cp, and the session cookie carries the hardened flags when the
 * production env values are in effect. Prod .env values themselves live in the
 * deploy checklist (docs/security/deploy-env-checklist.md), not in CI.
 */
test('enforces two-factor authentication for all roles', function () {
    expect(config('statamic.users.two_factor_enforced_roles'))->toBe(['*']);
});

test('session cookie carries the hardened flags under production config', function () {
    config([
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
    ]);

    $response = $this->get('https://localhost/cp/auth/login');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeTrue()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getSameSite())->toBe('lax');
});

test('does not mark the session cookie secure when disabled', function () {
    config(['session.secure' => false]);

    $response = $this->get('http://localhost/cp/auth/login');

    $cookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

    expect($cookie)->not->toBeNull()
        ->and($cookie->isSecure())->toBeFalse();
});
