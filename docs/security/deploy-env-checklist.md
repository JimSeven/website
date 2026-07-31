# Deploy `.env` hardening checklist

Baseline hardening for the production environment (Issue #9, from checklist #3 §C/§E/§H/§L).

These are **operator-owned runtime values**. They live in the production `.env`, not in
committed config, so they cannot be enforced by tests or by the CI doctor gate — CI runs
against the CI environment, never against production. This checklist is the guarantee.

## Required in production

| Variable | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Disables debug-only behaviour; drives framework hardening. |
| `APP_DEBUG` | `false` | Prevents stack traces / config leakage on errors (OWASP A05). |
| `APP_KEY` | set (`base64:…`) | Required for session/cookie encryption integrity. |
| `LOG_LEVEL` | `warning` | Keeps `debug`/`info` noise (and potential PII) out of prod logs. |
| `SESSION_SECURE_COOKIE` | `true` | Session cookie sent over HTTPS only. |
| `SESSION_LIFETIME` | `30` (15–30) | Shorter idle window for the public `/cp`. |
| `STATAMIC_TWO_FACTOR_ENABLED` | `true` | Leave 2FA on; enforcement is set in config (see below). |

`config/statamic/users.php` hardcodes `two_factor_enforced_roles => ['*']`, so 2FA is
**enforced for every user** as long as `STATAMIC_TWO_FACTOR_ENABLED=true`. Do not set the
enable flag to `false` in production — that would silently disable enforcement.

## Relationship to the doctor gate (#6) — no double-gating

The PR gate runs `php artisan doctor --only=security --fail-on=fail`. Its three checks are:

- **Debug matches environment** — flags an `APP_ENV=production` + `APP_DEBUG=true` *mismatch*.
- **`.env` is gitignored** — the env file is not committed.
- **Composer audit passes** — no known-vulnerable dependencies.

Doctor runs in **CI**, against the CI environment. It verifies *consistency and code/dependency
posture*, not the live production `.env`. It cannot see prod's `APP_DEBUG`, `LOG_LEVEL`, or
session flags. So:

- The debug/env values above are **owned by this checklist**, not re-tested in CI — that would
  be a redundant gate that still couldn't observe production.
- `.env`-gitignored and `composer audit` are **owned by doctor**; this checklist does not repeat
  them.

## Verify after deploy

Run against the live environment (not CI):

```bash
php artisan doctor --only=security   # composer audit + env consistency
php artisan config:show app.debug    # => false
php artisan config:show app.env      # => production
```

Then confirm the session cookie carries `Secure`, `HttpOnly`, and `SameSite=Lax` in the browser
dev tools, and that logging in to `/cp` requires the second factor.
