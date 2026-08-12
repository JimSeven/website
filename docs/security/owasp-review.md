# OWASP-Review — Zustands-Audit (SSoT)

**Quelle der Checkliste:** `docs/security/owasp-checklist.md`
**Prozess:** `/owasp-review` — manuell vor Release. Ein Lauf schreibt jeden Punkt (Status + Begründung + Datum) fort.
**Letzter Lauf:** 2026-07-31
**Letzte Fortschreibung:** 2026-08-10 — Umsetzungs-Merges §G (#21), §C (#22/#29), §K/§L-Logging (#23/#32); Sentry-Teil nach #31 ausgelagert. Kein voller Re-Audit, nur Status-Fortschreibung der betroffenen Punkte.
**Diff-Basis (`/security-review`):** `origin/main` (kein Release-Tag vorhanden)

## Scanner-Input (Provenance)

| Quelle | Bezug | Ergebnis |
|--------|-------|----------|
| composer advisories | CI-Artefakt `composer-audit` (`security-pr.yml` run 30631258232) | `advisories: []`, `abandoned: []` — sauber |
| doctor security | CI-Artefakt `doctor` (run 30631258232) | Security-Gruppe komplett `pass` (Debug↔Env, `.env` gitignored, composer audit). Nicht-Security-Fails (DB/SQLite/composer.lock) = CI-Umgebungsartefakte, nicht security-relevant |
| Larastan | Job-Status `larastan` (run 30631258232) | `success` |
| Psalm taint (SARIF) | Code Scanning `tool_name=Psalm` | `unavailable` — noch kein `security-weekly.yml`-Lauf (Code Scanning leer, HTTP 404). Erwartbar vor erstem Wochenlauf |

Zusatz: `gitleaks`- und `npm-audit`-Jobs des Laufs ebenfalls `success`.

## Status je Checklisten-Punkt (Typ: Review / CI+Review)

| § | Punkt | Status | Begründung | Datum |
|---|-------|--------|------------|-------|
| §B | HTTPS erzwungen (Redirect http→https) | Needs-infra | TLS terminiert an Laravel-Cloud-LB; http→https-Redirect ist CDN-/Webserver-Ebene. App-seitige Voraussetzung erfüllt: `trustProxies(at: '*')` in `bootstrap/app.php`, HSTS shippt bei `$request->secure()` | 2026-07-31 |
| §B | Trusted Proxies | OK | `bootstrap/app.php` `trustProxies(at: '*')` — `secure()` erkennt HTTPS hinter dem LB, `secure`-Cookies + HSTS greifen | 2026-07-31 |
| §C | Battle-tested Auth (keine Eigenbau-Crypto) | OK | Statamic-CP-Auth + Laravel-Guards; kein Eigenbau. `routes/web.php` leer → keine Custom-Auth | 2026-07-31 |
| §C | Brute-Force-/Lockout-Schutz am CP-Login | OK | Statamic-Default-Login-Throttling aktiv; nicht aufgeweicht. Exponentieller kontobezogener Lockout nicht zusätzlich konfiguriert (Baseline vertretbar) | 2026-07-31 |
| §C | Passwort-Policy | OK | 2FA für alle Rollen erzwungen (`config/statamic/users.php` `two_factor_enforced_roles => ['*']`) → OWASP-Baseline ≥8 vertretbar; keine schwächenden Composition-Rules | 2026-07-31 |
| §C | Generische Login-Fehlermeldungen | OK | Statamic-Default generisch; keine Custom-Login-/Register-/Reset-Flows (`routes/web.php` leer) | 2026-07-31 |
| §C | Session-ID nach Login regeneriert | OK | Laravel/Statamic-Default | 2026-07-31 |
| §C | Impersonation absichern | OK | `users.php` `impersonate.enabled` default `true`, nur für Super-Users; bewusst im Threat Model | 2026-07-31 |
| §C | CP-Route härten (Rename/Restriktion) | OK | Entscheidung #22 (PR #29, 2026-08-10): IP-Allowlist/Basic-Auth bewusst verworfen (Forge, CP-Zugriff von beliebigen IPs; kaum Mehrwert über erzwungenes 2FA). Gewählt: unratbarer `CP_ROUTE` via Forge-Env als Obscurity-/Rausch-Reduktion. Realer Schutz = erzwungenes 2FA + verifizierter Login-Throttle (`statamic.cp.auth` 4/min/IP). Session-Lifetime erledigt (`SESSION_LIFETIME=30`) | 2026-08-10 |
| §D | CP-Permissions/Roles nach Least Privilege | OK | Rollen in `resources/users/` auditierbar, Super-User sparsam; keine Custom-Eskalation | 2026-07-31 |
| §D | Keine unautorisierten öffentlichen Routes | OK | `routes/web.php` leer (nur `use`-Import) → keine eigene Fläche | 2026-07-31 |
| §D | Content-Protection für nicht-öffentliche Inhalte | N/A | `config/statamic/protect.php` `default => null`; keine geschützten Bereiche im Scope | 2026-07-31 |
| §D | Live Preview nicht als Info-Leak | OK | Rendert Entwürfe nur für authentifizierte CP-User; keine Custom-Draft-Route | 2026-07-31 |
| §E | Session-Lifetime angemessen | OK | `.env.example` `SESSION_LIFETIME=30` (OWASP 15–30 für sensibles CP) | 2026-07-31 |
| §F | Blade `{!! !!}` nur für vertrauenswürdigen Inhalt | OK | Grep `{!!` über Non-Vendor-Blade (3 Dateien): keine Treffer | 2026-07-31 |
| §F | Antlers-Ausgabe von User-Content escaped | OK | Grep `\|raw` / `noparse` über 12 `.antlers.html`-Templates: keine Treffer | 2026-07-31 |
| §G | Content-Security-Policy | OK | Umgesetzt #21 (PR #25, 2026-08-10): `spatie/laravel-csp` + `ContentSecurityPolicy`-Middleware mit `FrontendPolicy` (`config/csp.php`), CP-kompatibel (Nonce via `Vite::useCspNonce`). Frontend liefert strikte CSP inkl. `frame-ancestors 'self'`; Feature-Tests decken Header + Nonce ab | 2026-08-10 |
| §H | Keine Stacktraces/Solutions-Pages öffentlich | OK | `spatie/laravel-error-solutions` ist `require-dev` (in Prod nicht geladen); `APP_DEBUG` über `deploy-env-checklist.md` gedeckt; `bootstrap/app.php` rendert JSON für `api/*` | 2026-07-31 |
| §I | Dateirechte (Dirs ≤775, Files ≤664) | Needs-infra | Host-/Filesystem-Ebene, nicht im Repo verifizierbar | 2026-07-31 |
| §J | Assets außerhalb Webroot / kontrollierter Zugriff | N/A | Kein `secure`-geflaggter Container in `content/assets/`; alle Assets öffentlich by design, kein privater Container heute | 2026-07-31 |
| §J | Größenlimits (DoS) | Needs-infra | `upload_max_filesize`/`post_max_size` sind PHP-/Webserver-Ebene; kein Blueprint-`max_filesize`-Override | 2026-07-31 |
| §J | SSRF: keine unvalidierten URL-Fetches | N/A | Keine Feeds/Glide-Remote/eigener URL-Fetch im Code | 2026-07-31 |
| §J | ZIP/XML-Verarbeitung (XXE/Zip-Bomb) | N/A | Form-Exporter sind Ausgaben, kein Untrusted-Input-Parsing | 2026-07-31 |
| §K | CP-Login-Throttling | OK | Statamic-Default aktiv (siehe §C Brute-Force) | 2026-07-31 |
| §K | Öffentliche Forms rate-limited | OK | `statamic.forms`-Limiter default 10/min pro IP; nicht aufgeweicht. Honeypot nicht gesetzt (keine aktiven Custom-Forms heute) | 2026-07-31 |
| §L | Ausreichendes Logging (Login-Fehler, Access-Control-Fails) | OK | Umgesetzt #23 (PR #32, ADR-0001, 2026-08-10): Event-Subscriber loggt `Failed`/`Lockout`/`TwoFactorAuthenticationFailed`/`Login` strukturiert in dedizierten `security`-Channel (`daily`, 90 Tage); Lockout-Alert via Slack-Webhook, env-gated (`LOG_SLACK_WEBHOOK_URL`). Passwort/`credentials` werden nie geloggt (per Test abgesichert) | 2026-08-10 |
| §L | Fehler-Monitoring (Sentry o.ä.) | Gap | Kein Error-Monitoring im Repo (A09). Aus #23 herausgelöst → eigenes Ticket **#31** (`ready-for-human`): ja/nein-Entscheidung für Produktion noch offen | 2026-08-10 |
| §L | Statamic/Laravel-Updates zeitnah | OK | Laravel `^13`, Statamic `^6`; Dependabot (composer+npm) + `composer audit`-Gate (PR + Wochenlauf) sichern Update-Kadenz | 2026-07-31 |
| §L | Integrity/Supply-Chain (CI/CD, Git-Integration) | OK | `STATAMIC_GIT_ENABLED=false` (kein Auto-Commit produktiver Änderungen); CI mit gepinnten Actions (aktuelle Dependabot-Bumps) | 2026-07-31 |

Status-Werte: `OK` · `Gap` · `N/A` · `Needs-infra`

## Antlers-/Blade-XSS-Grep

- `rg -n --glob '*.antlers.html' -e '\|\s*raw\b' -e '\{\{\s*noparse'` über 12 Templates → **keine Treffer**.
- `rg -n --glob '*.blade.php' -e '\{!!'` über 3 Non-Vendor-Blades → **keine Treffer**.

Kein unescaped Output von potenziell User-/CP-editierbarem Content. §F sauber.

## /security-review — Findings (Diff seit `origin/main`)

Kein Release-Tag vorhanden → Basis = `origin/main`. `origin/main...HEAD` ist **leer** (HEAD == origin/main, Working Tree sauber) → kein un-released Changeset zu prüfen, keine Findings. Beim nächsten Release gegen den dann gesetzten Tag erneut laufen.

## Angelegte / aktualisierte Issues

- Fog-Punkt 6 (§G CSP) → #21 → **gemergt** (PR #25, 2026-08-10)
- Fog-Punkt 7 (§C CP-Zugang härten) → #22 → **gemergt** (PR #29, 2026-08-10)
- Fog-Punkt 9 (§K/§L Security-Logging + Alerting) → #23 → **gemergt** (PR #32, ADR-0001, 2026-08-10)
- §L Fehler-Monitoring/Sentry → #31 (`security-audit`, `ready-for-human`) — **offen**, aus #23 ausgelagert

### Offene Restpunkte (kein Agent-Build)

- #31 Sentry: ja/nein-Entscheidung für Produktion (`ready-for-human`).
- `Needs-infra` (Host-/Laravel-Cloud-Ebene, nicht im Repo lösbar): §B http→https-Redirect, §I Dateirechte, §J Upload-Größenlimits.
