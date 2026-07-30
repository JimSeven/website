# Security-Tooling-Landscape für Laravel / Statamic (OWASP-Ausrichtung)

> Research-Ticket #2 · Repo `JimSeven/website` · Laravel 13, Statamic v6, PHP 8.3+
> Deployment: Statamic-App produktiv, Control Panel **öffentlich unter `/cp`**
> Stand: 2026-07-30 · Alle Aussagen gegen Primärquellen (offizielle Docs, GitHub-Repos, Packagist, OWASP, GHSA) verifiziert.

## Zielsetzung

Welche Tools stützen eine OWASP-Ausrichtung und vor allem einen **wiederkehrenden, automatisierten Abgleich** (der mechanisierbare Teil des OWASP Laravel Cheat Sheet)? Bewertet nach Reife/Wartung, L13-/Statamic-v6-Kompatibilität, Abdeckung, maschinenlesbarem Output (JSON/SARIF) und Empfehlung.

## Installierter Ist-Stand (aus `composer.json` / `composer.lock`)

| Package | Version | Security-relevant? |
|---|---|---|
| `statamic/cms` | v6.26.0 | **>= 6.24.2** → gegen die Juli-2026-GHSA-Serie bereits gepatcht |
| `laravel/framework` | v13.23.0 | — |
| `roave/security-advisories` | dev-latest (dev-dep) | ✅ Prävention aktiv |
| `laravel/doctor` | v0.1.0 (dev-dep) | ✅ Config-/Deploy-Health inkl. Security-Kategorie |
| `larastan/larastan` | v3.10.0 (dev-dep) | ⚠️ Typsicherheit, **kein** Security-Scanner |
| `phpstan/phpstan` | 2.2.7 | (Basis für Larastan) |
| Composer | 2.10.1 | `composer audit` verfügbar |

Kernbefund vorab: Der Grundstock (`roave/security-advisories` + `composer audit` via Composer + `laravel/doctor`) ist bereits vorhanden. Es fehlt die **CI-Mechanisierung** (Exit-Code-Gates, JSON/SARIF-Ingest) und ein echter **Taint-/Injection-Scanner**.

---

## 1. Dependency-/CVE-Scanning

Alle Tools hier sind framework-agnostisch (arbeiten auf `composer.lock` / `package-lock.json`). L13-/Statamic-v6-/PHP-8.3-Kompatibilität ist bei allen trivial gegeben — sie hängt nur an Composer 2.x bzw. Node.

| Tool | Coverage | Reife/Wartung | Output | OWASP-recurring | Empfehlung |
|---|---|---|---|---|---|
| **`composer audit`** | Advisories (Packagist API → FriendsOfPHP + GitHub Advisory DB), abandoned & Malware-Flags | Composer-Kern seit 2.4, aktiv | `--format=json` (proprietär, **kein SARIF**), `plain`, `summary`; **Exit `1` bei Fund** | **Ja** — deterministischer CI-Gate, 0 Zusatz-Deps | **JA (Kern-Gate)** |
| **`roave/security-advisories`** | Präventiv via Composer-**Conflict-Rules**, blockt `require`/`update` verwundbarer Versionen; stündliches Update | Aktiv, bewusst nur `dev-latest` (Rolling) | **Keiner** (nur Install-Fehlschlag) | Teilweise — **scannt kein Lockfile**; `composer install` mit gültiger Lock umgeht es | **JA (nur Ergänzung)** |
| **`npm audit`** | npm-Registry-Advisories; relevant nur bei Frontend-/Vite-Deps | npm-CLI-Kern, aktiv | `--json`; `--audit-level` setzt Fail-Schwelle; Exit CI-tauglich | Bedingt — hohe Noise-Rate über transitive devDeps | **JA, falls Node-Deps** (mit `--omit=dev --audit-level=high`) |
| **GitHub Dependabot** | Alerts + Auto-PR Security Updates; **composer + npm**; GitHub Advisory DB | GitHub-nativ, aktiv | Alerts als JSON via REST/GraphQL (`/dependabot/alerts`); **kein SARIF** | Ja — kontinuierlicher serverseitiger Monitoring-Layer, Config `.github/dependabot.yml` | **JA (Monitoring)** |
| OWASP Dependency-Check | Composer-Lock-Analyzer **experimentell** (hohe FP/FN-Rate lt. Doku) | aktiv (aber PHP nur experimentell) | XML/JSON/SARIF/HTML | — | **NEIN** — `composer audit` deckt PHP nativer/genauer ab |
| OWASP Dependency-Track | SBOM-Aggregation (CycloneDX), kein PHP-Scanner | aktiv | — | — | **NEIN** — SBOM-Overkill für dieses Setup |

**Overlap Dependabot ↔ `composer audit`:** gleiche Advisory-Basis, aber nicht redundant. Dependabot = serverseitig/kontinuierlich/Auto-PR (fängt CVEs zwischen Builds); `composer audit` = synchroner CI-Gate mit hartem Exit-Code (blockt Merge/Deploy). Beide zusammen.

**Gap:** Kein Composer-Tool liefert SARIF; JSON-Schemata sind proprietär → für einheitliches Agent-Format selbst mappen.

Quellen: getcomposer.org/doc/03-cli.md · github.com/Roave/SecurityAdvisories · docs.npmjs.com/cli/v10/commands/npm-audit · docs.github.com/en/code-security/dependabot · owasp.org/www-project-dependency-check

---

## 2. Security-Analyse-Suites & Static Analysis

| Tool | L13 / PHP 8.3 | Security-Fokus | JSON/SARIF | Reife/Wartung | Empfehlung |
|---|---|---|---|---|---|
| **Enlightn (OSS)** | **Nein** (composer.json `^9\|^10\|^11`, max L11) | Ja, breit (Debug, HTTPS/HSTS, CSRF, XSS-Escaping, Cookie/Session, Hash, CVE-Checker) | schwach (Reports v. a. in Pro) | **Archiviert 01/2026**, letztes Release v2.10.0 (04/2024) | **NEIN** — Dead End, kein L12/13 |
| **`laravel/doctor`** | **Ja, nativ** (`php ^8.3`, `illuminate ^12\|^13`) | nur Basis-Hygiene: Debug-Mode passend zur Env, `.env` in `.gitignore`, App-Key gesetzt, Composer-Vuln-Audit | **JSON + GitHub-Annotations + Agent-Format** | v0.1.0 (22.07.2026, sehr neu, API instabil) | **JA (Config-/Deploy-Health-Gate, kein Security-Scanner)** |
| **Larastan / PHPStan** | **Ja** (Larastan 3.x: `php ^8.2`, `illuminate ^11.44\|^12\|^13`) | **Nein** — Type/Static, keine Taint-Analyse (Feature-Request `phpstan#8038` closed/nicht umgesetzt) | `--error-format=json\|checkstyle\|github\|gitlab…`; **kein natives SARIF** | aktiv | **JA (Code-Qualität/Typsicherheit, nicht als Security-Tool zählen)** |
| **`vimeo/psalm` + Taint** | **Ja** (`psalm/plugin-laravel` 3.x → L11–13/Psalm 6 stabil; 4.x → L12–13/Psalm-7-beta) | **Ja** — echte Taint-Analyse: SQLi, XSS, Command-Injection, LDAP, **SSRF**, LFI/RFI, unserialize | **SARIF nativ** (`--report=results.sarif`) + JSON | aktiv (Stable 6.16.1 / 7.0-beta 2025), PHP 8.3/8.4 | **JA — einziger echter OWASP-Taint-Scanner hier** |

Wichtige Klarstellungen:
- **Larastan/PHPStan ist kein Security-Tool.** Security-Nähe nur über Zusatzpakete (`phpstan-strict-rules`, `spaze/phpstan-disallowed-calls` gegen unsichere Funktionen) — kein Taint-Ruleset.
- **Psalm** braucht typisierte Codebase + Annotations und Tuning gegen False-Positives; für stabile Prod-CI Plugin **3.x + Psalm 6** (4.x zieht Psalm-7-beta mit `min-stability: dev`).
- **Statamic-Blindspot:** Kein Tool hat Statamic-spezifische Regeln; alle greifen nur über die Laravel-Ebene. **Antlers-Templates (`.antlers.html`) liegen außerhalb der PHP-Static-Analysis** — XSS dort deckt keines dieser Tools ab.

Quellen: github.com/enlightn/enlightn (archiviert) · github.com/laravel/doctor · github.com/larastan/larastan · phpstan.org/user-guide/output-format · github.com/phpstan/phpstan/issues/8038 · psalm.dev/docs/security_analysis · github.com/psalm/psalm-plugin-laravel

---

## 3. Security-Header / CSP

Weder Laravel 13 noch Statamic v6 liefern Security-Header/CSP out of the box (L13 `web`-Group hat nur `throttle`, TrustProxies/TrustHosts — **keine** SecurityHeaders-Middleware; anderslautende Sekundär-Blogs sind unbelegt). Ein Header-Paket ist also sinnvoll.

| Tool | L13 / PHP 8.3 | Deckt | Testbarkeit | CP-Caveat (`/cp` öffentlich!) | Empfehlung |
|---|---|---|---|---|---|
| **`bepsvpt/secure-headers`** | Ja (v9.1.1, Laravel 5.1–13.x) | HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, CSP | Config-basiert, in Feature-Tests prüfbar | Statische Header CP-unkritisch; **CSP-Teil dort leer lassen** | **JA** — Basis-Header, schnellster ROI |
| **`spatie/laravel-csp`** | Ja (v3.26.0, `php ^8.3`, `illuminate ^11.36\|^12\|^13`) | CSP als PHP-Policy-Klassen, 30+ Presets, Nonce (`@cspNonce`, Vite-Integration, `strict-dynamic`), Report-Only | Hoch — Policies Unit-testbar, Report-Only zum gefahrlosen Rollout | **CSP bricht das CP** (Inertia/Vue-SPA braucht Inline-JS) | **JA, aber nur Frontend** |

**Statamic-offizielle Vorgabe** (statamic.dev/tips/content-security-policy): CSP **nicht** aufs CP anwenden — „Statamic needs to be able to run inline JavaScript". CSP-Middleware nur in die **`web`**-Middleware-Group hängen (nicht global), dann sind CP-Routen ausgenommen. Statamic nennt `spatie/laravel-csp` namentlich.

**Regel:** CSP-Zuständigkeit auf **ein** Paket bündeln (nicht secure-headers *und* spatie gleichzeitig CSP setzen lassen). Empfohlen: secure-headers für die statischen Header, spatie/laravel-csp für CSP nur auf dem Frontend, `/cp` zwingend ausgeschlossen.

CI-Relevanz: gering (kein Scanner), aber Header-Response ist per Feature-Test verifizierbar → als Regressions-Test in CI.

Quellen: github.com/spatie/laravel-csp · github.com/bepsvpt/secure-headers · statamic.dev/tips/content-security-policy · laravel.com/docs/13.x/middleware

---

## 4. Statamic-spezifische Security

Für ein **öffentlich erreichbares `/cp`** liegt die eigentliche Härtung nicht in Header-Paketen, sondern in Statamic-Core-Features und Patch-Disziplin.

### Control-Panel-Hardening
- `config/statamic/cp.php`: `enabled` (`CP_ENABLED`) und `route` (`CP_ROUTE`) sind env-steuerbar. `auth.enabled = false` deaktiviert nur die CP-**Login-Seiten**, nicht den CP-Zugang.
- statamic.dev dokumentiert **kein** offizielles CP-URL-Renaming / IP-Allowlisting als Security-Feature → per eigener Middleware bzw. **Reverse-Proxy/Webserver** lösen (IP-Allowlist am Proxy).
- Repo hat `config/statamic/protect.php` mit Schemes (`ip_address`, `auth`, `password`); `default => null`. Der `ip_address`-Scheme (`allowed`) lässt sich für Frontend-Bereiche nutzen — schützt aber **nicht** das CP.

### 2FA / WebAuthn (v6 Core, kein Addon)
- `config/statamic/users.php`: `two_factor_enabled` default `true`, **aber `two_factor_enforced_roles => []` → 2FA aktuell NICHT erzwungen.**
- **Empfehlung:** `two_factor_enforced_roles => ['*']` (oder mind. `'super_users'`) setzen — bei öffentlichem `/cp` die wichtigste Einzelmaßnahme.
- Passkeys/WebAuthn built-in (`config/statamic/webauthn.php`). Repo-Default `allow_password_login_with_passkey => true` → Passwort-Login bleibt trotz Passkey möglich; für hartes „passwordless" auf `false`.
- Weitere CP-Härtung im Repo bereits gesetzt: `elevated_sessions_enabled => true` (Re-Auth für sensible Aktionen). Hinweis: `impersonate.enabled => true` — für Prod prüfen, ob nötig.

### Rate Limiting (IP-basiert, via `RateLimiter`-Facade überschreibbar)
- `statamic.auth` 4/min (Frontend), `statamic.cp.auth` erbt davon (CP-Login), `statamic.passkeys` 30/min, `statamic.cp.passkeys` erbt.
- Für öffentliches `/cp`: `statamic.cp.auth` strenger definieren.

### Asset-/Upload-Security (`config/statamic/assets.php`, Repo-Stand)
- **SVG-Sanitization aktiv** (`svg_sanitization_on_upload => true`) — bei untrusted Uploads so lassen.
- **Glide-Security-Token aktiv** (`image_manipulation.secure => true`) — schützt vor Mass-Resize-Angriffen.
- `additional_uploadable_extensions => []` (nur Default-Whitelist). Pro Container zusätzlich Laravel-Validation (`mimes`, `max`) in `content/assets/{handle}.yaml`.

### Bekannte Advisories (github.com/statamic/cms/security/advisories, Juli-2026-Serie, alle in v6 gefixt)

| GHSA | Schwere | Kurz | Gefixt in v6 ab |
|---|---|---|---|
| GHSA-93qh-5269-9wcf | **High 8.1** | OAuth Account-Takeover (ungeprüfte E-Mail-Verifikation) | 6.24.0 |
| GHSA-j2vp-f2pv-5rj4 | Mod 6.5 | Antlers unsafe method invocation → Datenzerstörung (unauth) | 6.24.0 |
| GHSA-qhr7-v3xp-vw9m | Mod 5.3 | Frontend-Forms umgehen Upload-Restriktionen (CWE-434) | 6.24.2 |
| GHSA-v5c4-wcpj-x73m | Mod | Glide SSRF via DNS-Rebinding | 6.20.1 |
| GHSA-225x-3jhx-wh4q | Mod | Missing authz: CP-Endpoint → User-Existenz-Disclosure | (CP-relevant) |
| GHSA-2497-6pwj-pwg7 | Mod | Missing authz: CP-Fieldtype-Endpoints → Ressourcen-Disclosure | (CP-relevant) |
| GHSA-qh8c-7588-qfrv | Mod | Missing authz: Navigation-Endpoint gibt restricted Entries preis | — |
| GHSA-vx89-p3j7-8xqc | Mod | Stored XSS in Form-Notification-Email-Template | — |
| GHSA-h77m-qrj7-jxcw | Mod | CSV Formula Injection in Form-Export | — |
| GHSA-7mqq-4v55-88gh | Low | Live-Preview: view-only User submitten Editor-Content | — |

**Aktueller Repo-Stand v6.26.0 ist über 6.24.2 → gegen diese Serie gepatcht.** Mehrere Advisories betreffen direkt das CP (Authz-Disclosure) → bei öffentlichem `/cp` ist Patch-Disziplin der größte Hebel. `composer audit` + Dependabot auf `statamic/cms` fangen genau das mechanisch ab.

Quellen: statamic.dev/users · statamic.dev/control-panel/users · statamic.dev/assets · statamic.dev/knowledge-base/tips/disabling-cp-authentication · github.com/statamic/cms/security/advisories

---

## 5. Agent-taugliche Formate (JSON/SARIF)

| Tool | Format | Agent-/CI-Eignung |
|---|---|---|
| `vimeo/psalm` | **SARIF nativ** + JSON | Best-in-Class (GitHub Code Scanning direkt) |
| `laravel/doctor` | JSON + GitHub-Annotations + **Agent-Format** | Sehr gut |
| Larastan/PHPStan | JSON/checkstyle/github (kein natives SARIF) | Gut |
| `composer audit` | JSON (proprietär, kein SARIF) | OK — Mapping nötig |
| Dependabot | Alerts-JSON via API (kein SARIF) | OK — API-Pull statt CI-Artefakt |
| `npm audit` | JSON | OK |

**Einziger echter Gap:** Für Composer-Deps liefert kein Tool SARIF. Für ein einheitliches Agent-Review-Format müssen `composer audit`-JSON und Dependabot-Alerts selbst auf ein gemeinsames Schema (idealerweise SARIF) gemappt werden.

---

## Empfehlung: Baseline vs. CI-Automation

### In die Baseline (Config/Repo, dauerhaft aktiv)
1. **`roave/security-advisories:dev-latest`** — bereits vorhanden, behalten. Blockt Updates auf bekannte CVEs.
2. **Statamic-Config härten** (größter Hebel bei öffentlichem `/cp`):
   - `two_factor_enforced_roles => ['*']` (aktuell leer!)
   - `statamic.cp.auth`-Rate-Limit strenger, IP-Allowlist am Reverse-Proxy
   - SVG-Sanitization & Glide-Token bleiben `true` (bereits gesetzt)
   - `impersonate.enabled` für Prod prüfen
3. **Security-Header**: `bepsvpt/secure-headers` (statische Header) + `spatie/laravel-csp` (CSP nur `web`-Group, `/cp` ausgeschlossen) — mit Feature-Tests für die Header-Response.

### In die CI-Automation (Gates + wiederkehrender Abgleich)
1. **`composer audit --locked --format=json`** — harter Exit-Code-Gate. Das ist der eigentliche mechanisierte OWASP-Dependency-Check.
2. **`laravel/doctor` (JSON/Agent-Format)** — Config-/Deploy-Security-Hygiene (Debug-Mode, `.env`, App-Key) als wiederkehrender Gate.
3. **`vimeo/psalm --taint-analysis --report=results.sarif`** — der einzige echte OWASP-Injection/XSS/SSRF-Scanner; SARIF → GitHub Code Scanning + Agent-Review. (Plugin 3.x + Psalm 6 für stabile Prod-CI.)
4. **Larastan 3.x (JSON)** — als Code-Qualitäts-Gate (nicht als Security-Metrik werten).
5. **`npm audit --omit=dev --audit-level=high`** — nur falls Frontend-/Node-Deps vorhanden.

### Als serverseitiges Monitoring
- **GitHub Dependabot** (`.github/dependabot.yml`, `composer` + ggf. `npm`) — Alerts + Auto-PR-Security-Updates, komplementär zu `composer audit`. Fängt neue `statamic/cms`-GHSAs zwischen Builds.

### Explizit NICHT
- **Enlightn** (archiviert, kein L13), **OWASP Dependency-Check** (PHP nur experimentell), **OWASP Dependency-Track** (SBOM-Overkill).

### Bekannte Blindspots
- **Antlers-Templates** werden von keinem Static-Analysis-Tool auf XSS geprüft → bleibt manuelles Review / Output-Escaping-Disziplin.
- Kein SARIF für Composer-Deps → Mapping-Layer für einheitliches Agent-Format nötig.
