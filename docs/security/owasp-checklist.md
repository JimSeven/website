# OWASP-Checkliste — auf dieses Projekt gemappt

**Ticket:** #3 „Anwendbare OWASP-Checkliste für dieses Projekt"
**Repo:** JimSeven/website — Laravel 13, Statamic v6 (PHP ^8.3), hybrides Deployment (Statamic-App produktiv, CP öffentlich unter `/cp`).
**Stand:** 2026-07-30

## Primärquellen

- OWASP Laravel Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/Laravel_Cheat_Sheet.html
- OWASP Top 10:2021 — https://owasp.org/Top10/2021/
- OWASP HTTP Headers Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html
- OWASP Content Security Policy Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html
- OWASP Authentication Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP File Upload Cheat Sheet — https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- Statamic v6 Docs — https://statamic.dev/users, https://statamic.dev/forms

## Threat Model (Scope-Filter)

Prägt jede „Gilt?"-Entscheidung unten:

1. **Hybrid:** App läuft produktiv als dynamische Laravel/Statamic-App. Kein reines SSG heute, aber `statamic/ssg ^4.1` ist installiert → Full Static Caching / SSG ist eine realistische spätere Option (aktuell `STATAMIC_STATIC_CACHING_STRATEGY=null`, also aus).
2. **CP öffentlich:** Control Panel unter `/cp` ohne Netz-/IP-Restriktion erreichbar → CP-Login ist eine öffentliche Angriffsfläche (Brute-Force, Credential Stuffing, Enumeration). Höchstes Auth-Risiko des Projekts.
3. **Flat-File statt DB:** Content, User (`repository => file`), Forms und Blueprints liegen als Markdown/YAML im Repo, nicht in der DB. Verschiebt Teile des klassischen Laravel-Advice: Kein Eloquent-Mass-Assignment auf Content, dafür Datei-/Pfad- und Git-Risiken.
4. **Öffentliche Schreibpfade:** Standardmäßig Forms (Submissions) und Asset-Uploads über das CP; `user:register_form` nur falls genutzt.

Legende Spalte „Typ": **CI** = mechanisierbar (statischer Scanner/Test) · **Review** = urteilsabhängig (Agent-/Human-Review) · **CI+Review** = beides.

---

## A. OWASP Top 10:2021 — Relevanz-Überblick

| # | Kategorie | Relevanz hier | Wo im Detail |
|---|-----------|---------------|--------------|
| A01 | Broken Access Control | **Hoch** — CP-Permissions/Roles, Live Preview, öffentliche Routes | §D Authorization, §C Auth |
| A02 | Cryptographic Failures | **Hoch** — TLS/HSTS, APP_KEY, Cookie/Session-Flags | §B TLS, §E Session |
| A03 | Injection | **Mittel** — wenig eigener DB-Code, aber XSS in Antlers/Blade + evtl. Raw-Queries | §F Injection/XSS |
| A04 | Insecure Design | **Mittel** — CP öffentlich ist Design-Entscheidung; Threat Model dokumentieren | dieses Kapitel |
| A05 | Security Misconfiguration | **Hoch** — APP_DEBUG/ENV, Security-Header, freigeschaltete Statamic-Features | §A Basics, §G Header |
| A06 | Vulnerable & Outdated Components | **Hoch** — Composer/npm-Deps, Statamic/Laravel-Updates | §L Dependencies |
| A07 | Identification & Auth Failures | **Hoch** — öffentliches CP-Login, 2FA, Brute-Force-Schutz | §C Auth |
| A08 | Software & Data Integrity Failures | **Mittel** — CI/CD, Git-Integration, Deserialization, npm-Supply-Chain | §L, §F |
| A09 | Logging & Monitoring Failures | **Mittel** — Login-Fehler, Fehlerreporting produktiv | §K Logging |
| A10 | SSRF | **Niedrig–Mittel** — nur falls Feeds/Glide-Remote/URL-Fetch genutzt | §J SSRF/Uploads |

---

## B. TLS / HTTPS / HSTS  (A02, A05)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| HTTPS erzwungen (Redirect http→https) | **Gilt** | CI+Review | Nicht im Repo erkennbar (Infra-/Webserver-Ebene). `APP_URL` in `.env.example` = `http://localhost` (nur Beispiel). Prod muss `https://` + Redirect erzwingen. |
| HSTS-Header `max-age=63072000; includeSubDomains; preload` | **Gilt** | CI | Kein Security-Header-Middleware im Repo (`bootstrap/app.php` `withMiddleware` leer, kein `app/Http/Middleware`). → Header fehlen aktuell. |
| Trusted Proxies (korrektes `https`-Scheme hinter Proxy/LB) | **Bedingt** | Review | Keine Trusted-Proxy-Konfig im Repo. Nötig, wenn hinter Reverse Proxy/CDN, damit `secure`-Cookies + HTTPS-Erkennung greifen. |

**Statamic-Besonderheit:** Bei späterem Full Static Caching liefert der Webserver Files direkt aus → HSTS/Redirect müssen dann auf Webserver-/CDN-Ebene sitzen, nicht in PHP-Middleware, da PHP teils gar nicht mehr durchlaufen wird.

---

## C. Authentifizierung & CP-Zugang & 2FA  (A07)

Höchstes Projektrisiko wegen öffentlichem CP.

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| Battle-tested Auth (keine Eigenbau-Crypto) | **Gilt (erfüllt)** | Review | Statamic-CP-Auth + Laravel-Guards. `BCRYPT_ROUNDS=12`. Kein Eigenbau erkennbar. |
| 2FA verfügbar | **Gilt (erfüllt)** | CI | `config/statamic/users.php` `two_factor_enabled` = `env(STATAMIC_TWO_FACTOR_ENABLED, true)`, `.env.example`=`true`. |
| **2FA erzwungen** für CP-User/Super-Users | **Gilt (offen)** | CI | `two_factor_enforced_roles => []` → **nicht erzwungen**. Bei öffentlichem CP dringend `['*']` oder mindestens `super_users`. Statamic verschlüsselt 2FA-Secrets mit `APP_KEY`. |
| Brute-Force-/Lockout-Schutz am CP-Login | **Gilt** | Review | Statamic wendet Login-Throttling an; Härtegrad projektseitig prüfen. OWASP: kontobezogener, exponentieller Lockout (Start 1s, verdoppelnd). |
| Passwort-Policy (mit MFA ≥8, ohne MFA ≥15; keine Composition-Rules; Breach-Check) | **Gilt** | Review | Keine projektseitige Policy im Repo. Falls 2FA erzwungen → ≥8 vertretbar; sonst ≥15. |
| Generische Login-Fehlermeldungen (Anti-Enumeration) | **Gilt** | Review | Statamic-Default generisch; bei Custom-Login-/Register-/Reset-Flows selbst prüfen. |
| Session-ID nach Login regeneriert, unvorhersagbar | **Gilt (erfüllt)** | Review | Laravel/Statamic-Default. |
| Elevated Sessions für sensible CP-Aktionen | **Gilt (erfüllt)** | CI | `elevated_sessions_enabled => true`. |
| Impersonation absichern | **Bedingt** | Review | `impersonate.enabled => true` (Default). Nur für vertrauenswürdige Super-Users; im Threat Model bewusst halten. |
| CP-Route härten (Rename/Restriktion) | **Bedingt** | Review | `cp.route` = `env(CP_ROUTE, 'cp')`, `cp.enabled` = `env(CP_ENABLED, true)`. Öffentlich per Scope-Entscheidung. Optional: unratbarer Pfad, IP-Allowlist, Basic-Auth vorschalten (Defense in Depth, kein Ersatz für 2FA). |
| Öffentliche Nutzer-Registrierung deaktiviert/abgesichert | **Bedingt** | CI | `new_user_roles => []`, `registration_form_honeypot_field => null`. `user:register_form` scheint ungenutzt (leere `routes/web.php`). Falls genutzt: Honeypot setzen + Rollen bewusst vergeben. |

**Statamic-Besonderheit:** User liegen als Flat-Files (`repository => file`). Keine `users`-DB-Tabelle → SQL-Auth-Angriffe entfallen, dafür Datei-/Git-Exposure der User-Files beachten (Passwort-Hashes im Repo, wenn User eingecheckt werden — prüfen, dass `users/` nicht öffentlich servierbar und Hashing korrekt).

---

## D. Authorization / Access Control / Policies  (A01)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| CP-Permissions/Roles nach Least Privilege | **Gilt** | Review | Statamic Permissions→Roles→Users/Groups. Super-User sparsam. Rollen im Repo (`resources/users/`) auditierbar. |
| Keine unautorisierten öffentlichen Routes/Endpunkte | **Gilt** | Review | `routes/web.php` leer (nur auskommentiertes Beispiel) → geringe eigene Fläche heute. Bei neuen Routes Access-Control mitdenken. |
| Content-Protection für nicht-öffentliche Inhalte | **Bedingt** | CI+Review | `config/statamic/protect.php` `default => null` → kein globaler Schutz. Nur relevant, falls es geschützte Bereiche geben soll (driver `auth`/`password`/`ip_address`). |
| Live Preview nicht als Info-Leak | **Bedingt** | Review | Live Preview rendert Entwürfe nur für authentifizierte CP-User; sicherstellen, dass keine Draft-Route unauth erreichbar wird. |
| Statamic API/GraphQL Zugriff kontrolliert | **N/A heute** | CI | `STATAMIC_API_ENABLED=false`, `STATAMIC_GRAPHQL_ENABLED=false`. Falls aktiviert → Auth-Token + Middleware + Feld-Whitelisting nötig. |

---

## E. Session- & Cookie-Flags  (A02, A05)

Basis: `config/session.php`, `.env.example`.

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| `EncryptCookies`-Middleware aktiv | **Gilt (erfüllt)** | CI | Laravel-`web`-Default. |
| `http_only => true` | **Gilt (erfüllt)** | CI | `env(SESSION_HTTP_ONLY, true)`. |
| `same_site => lax/strict` | **Gilt (erfüllt)** | CI | Default `lax`. Für CP evtl. `strict` erwägen. |
| **`secure => true`** in Produktion | **Gilt (offen)** | CI | `secure => env('SESSION_SECURE_COOKIE')` → Default `null` (auto). `.env.example` setzt es **nicht** → in Prod explizit `SESSION_SECURE_COOKIE=true` setzen. |
| `domain => null` | **Gilt (erfüllt)** | CI | Default `null` (`SESSION_DOMAIN=null`). |
| Session-Lifetime angemessen | **Gilt** | Review | `SESSION_LIFETIME=120` min. OWASP: für sensibles CP kürzer (15–30 min) erwägen. |
| `SESSION_ENCRYPT` | **Bedingt** | CI | `false`. Für File-Driver vertretbar; bei Bedarf `true`. |

---

## F. Injection / XSS / SQLi / Mass Assignment  (A03)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| Blade `{!! !!}` nur für vertrauenswürdigen Inhalt | **Gilt** | CI+Review | Grep-bar auf `{!! ... !!}` mit User-Input. Aktuell keine eigenen Views auffällig; bei neuen Templates prüfen. |
| Antlers-Ausgabe von User-Content escaped | **Gilt** | Review | **Statamic-Besonderheit:** Antlers `{{ }}` escaped standardmäßig; `\|raw` / `noparse` sind die Antlers-Pendants zu `{!! !!}` → nur für vertrauenswürdigen Content. |
| Raw-SQL nur mit Bindings; keine User-gesteuerten Spaltennamen | **Bedingt** | CI | Wenig eigener DB-Code (Flat-File). Falls `whereRaw`/`orderBy` mit Input dazukommt: Bindings + Whitelist. Grep-bar. |
| Mass Assignment: `validated()`/`only()` statt `all()`; kein `$guarded=[]` | **Bedingt** | CI | Kaum eigene Eloquent-Models heute. **Statamic-Besonderheit:** Content-Writes laufen über Blueprints/Fieldsets, nicht Eloquent-Fill → klassisches Mass Assignment weitgehend N/A. Blueprint-Felder = die eigentliche Feld-Whitelist. |
| Kein `unserialize()`/`eval()`/`extract()` auf Input; `escapeshellarg()` bei exec | **Gilt** | CI | Grep-bar. Aktuell kein eigener Code auffällig. |
| Open Redirect vermeiden | **Gilt** | CI | Keine `redirect($request->input(...))`-Stellen (Routes leer); bei neuen Redirects validieren. |

---

## G. Security-Header & CSP  (A05, A03)

Kein Header-Setzen im Repo (keine Middleware). Alle unten **offen**.

| Header | Empfohlener Wert (OWASP) | Typ | Anmerkung |
|--------|--------------------------|-----|-----------|
| Strict-Transport-Security | `max-age=63072000; includeSubDomains; preload` | CI | Nur mit vollständigem HTTPS setzen. |
| X-Content-Type-Options | `nosniff` | CI | Trivial, hoher Nutzen. |
| X-Frame-Options | `DENY` | CI | Auf `/cp` gesetzt; auf dem Frontend durch CSP `frame-ancestors 'self'` ersetzt (sonst bräche same-origin Live Preview). |
| Referrer-Policy | `strict-origin-when-cross-origin` | CI | |
| Permissions-Policy | `geolocation=(), camera=(), microphone=()` | CI | Ungenutzte Features abschalten. |
| Content-Security-Policy | Baseline `default-src 'self'; frame-ancestors 'self'; form-action 'self'` | **Review (umgesetzt, #21)** | Frontend-only via `spatie/laravel-csp` (Basic-Preset + `frame-ancestors`, Nonces), `/cp` ausgenommen. |

**CSP-Besonderheit (urteilsabhängig) — umgesetzt (#21):** Striktes CSP ohne `unsafe-inline`/`unsafe-eval`; Nonce-basiert (Laravel Vite via `Vite::useCspNonce()` an denselben spatie-Nonce gekoppelt). Policy nur aufs Frontend (`App\Http\Middleware\ContentSecurityPolicy` überspringt alle `statamic.cp.*`-Routes/`/cp`), CP + Live Preview bleiben unberührt. Browser-verifiziert gegen Frontend, CP und same-origin-Framing. `X-XSS-Protection: 0` bleibt.

---

## H. APP_DEBUG / APP_ENV / Fehlerseiten  (A05)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| `APP_DEBUG=false` in Produktion | **Gilt** | CI | `config/app.php` Default `false` (gut). `.env.example`=`true` (nur lokal). Prod-`.env` verifizieren. |
| `APP_ENV=production` | **Gilt** | CI | `config/app.php` Default `production`. `.env.example`=`local`. Prod verifizieren. |
| Debugbar aus in Prod | **Gilt (erfüllt)** | CI | `DEBUGBAR_ENABLED=false`; `fruitcake/laravel-debugbar` ist `require-dev`. |
| `APP_KEY` gesetzt | **Gilt (erfüllt)** | CI | `key:generate` in `composer setup`/`post-create-project-cmd`. Prüfen, dass Prod-Key existiert & stabil (2FA/Cookies hängen daran). |
| Keine Stacktraces/Solutions-Pages öffentlich | **Gilt** | Review | `spatie/laravel-error-solutions` ist `require-dev` → in Prod nicht geladen. `bootstrap/app.php` rendert JSON für `api/*`. |

---

## I. Secrets / .env  (A05, A02)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| `.env` nicht im Repo / nicht webservable | **Gilt** | CI | `.env.example` vorhanden (ok). Prüfen: `.env` in `.gitignore`, `public/` = Docroot. |
| Keine Secrets im Repo (auch Content/YAML) | **Gilt** | CI | Secret-Scanner sinnvoll. **Statamic-Besonderheit:** Content/User als Files → versehentlich eingecheckte Tokens/Hashes möglich. `STATAMIC_LICENSE_KEY`, `STATAMIC_API_AUTH_TOKEN`, `recache_token` gehören in `.env`. |
| Dateirechte: Dirs ≤775, Files ≤664 | **Bedingt** | Review | Infra-Ebene, nicht im Repo. |

---

## J. File-Uploads / Assets / SSRF  (A03/A08 Uploads, A10 SSRF)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| Extension-Allowlisting statt Blocklist | **Gilt (teilw. erfüllt)** | CI | **Statamic-Besonderheit:** Statamic erlaubt nur approved Extensions; `additional_uploadable_extensions => []` (nicht aufgeweicht — gut). Keine ausführbaren Extensions ergänzen. |
| SVG-Sanitization | **Gilt (erfüllt)** | CI | `svg_sanitization_on_upload => true`. |
| Dateinamen entschärfen (basename, Zeichen-Replacement) | **Gilt (erfüllt)** | CI | Statamic macht native Filename-Replacements; `additional_filename_replacements => []`. |
| Assets außerhalb Webroot / kontrollierter Zugriff | **Bedingt** | Review | Asset-Container `secure => true` gesetzt (ein Container). Container-Pfade prüfen: private Container nicht öffentlich servieren. |
| Größenlimits (DoS) | **Gilt** | Review | PHP/Webserver-`upload_max_filesize`, `post_max_size`; ggf. Blueprint-`max_filesize`. |
| Path Traversal bei Downloads/Reads | **Gilt** | CI | Kein eigener Download-Code heute; bei `response()->download()` mit Input → `basename()`. |
| SSRF: keine unvalidierten URL-Fetches | **Bedingt** | Review | Nur relevant bei Feeds, Glide-Remote-Images oder eigenem URL-Fetch. Heute nicht erkennbar. |
| ZIP/XML-Verarbeitung meiden (XXE/Zip-Bomb) | **Bedingt** | Review | Form-Exporter (CSV/JSON) sind Ausgaben, kein Untrusted-Input-Parsing. |

---

## K. Rate Limiting / Brute-Force  (A07, A04)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| CP-Login-Throttling | **Gilt** | Review | Statamic-seitig vorhanden; Schwellen/Härte prüfen (siehe §C). |
| Öffentliche Forms rate-limited | **Gilt (erfüllt)** | Review | **Statamic-Besonderheit:** `statamic.forms`-Limiter default 10 Submissions/min pro IP; anpassbar im `AppServiceProvider`. Zusätzlich Honeypot (`honeypot: field`) empfohlen. |
| Throttle auf eigene POST-/API-Routes | **Bedingt** | CI | Keine eigenen Routes heute. Bei neuen: `throttle:x,1`. |
| `recache_token` schützt Background-Recache-Endpoint | **Bedingt** | CI | `recache_token => env(STATAMIC_RECACHE_TOKEN)` — nur relevant bei aktivem Background-Recache/Static Caching. |

---

## L. Logging / Monitoring & Dependencies  (A09, A06, A08)

| Punkt | Gilt? | Typ | Ist-Beobachtung |
|-------|-------|-----|-----------------|
| Ausreichendes Logging (Login-Fehler, Access-Control-Fails) | **Gilt** | Review | `LOG_CHANNEL=stack`. Auth-/Sicherheitsereignisse (fehlgeschlagene CP-Logins) sollten geloggt + alarmiert werden. |
| `LOG_LEVEL` in Prod nicht `debug` | **Gilt** | CI | `.env.example`=`debug` (lokal). Prod auf `warning`/`error` setzen. |
| Fehler-Monitoring (Sentry o.ä.) | **Bedingt** | Review | Nicht im Repo. Für A09 empfehlenswert. |
| Composer-Deps ohne bekannte Lücken | **Gilt (teilw. erfüllt)** | CI | `roave/security-advisories: dev-latest` blockiert verwundbare Installs — **aber nur `require-dev`**, greift also v.a. lokal/CI, nicht zwingend im Prod-Build. `composer audit` in CI ergänzen. |
| npm-Deps auditiert | **Gilt** | CI | `npm audit` in CI. |
| Statamic/Laravel-Updates zeitnah | **Gilt** | Review | Laravel ^13, Statamic ^6. Update-Kadenz + Changelog-Watch. |
| Integrity/Supply-Chain (CI/CD, Git-Integration) | **Bedingt** | Review | `STATAMIC_GIT_ENABLED=false` (kein Auto-Commit produktiver Content-Änderungen). CI/CD-Pipeline absichern (A08). |

---

## Zusammenfassung — abgeleitete Baseline-Hardening-Tickets

**Sofort (CI-mechanisierbar, hoher Nutzen):**
1. Security-Header-Middleware (HSTS, `nosniff`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`) — §B/§G.
2. Prod-`.env` verifizieren/erzwingen: `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning` — §E/§H/§L.
3. `two_factor_enforced_roles => ['*']` (mind. `super_users`) — §C.
4. `composer audit` + `npm audit` als CI-Gate; `roave/security-advisories` auch für Prod-Builds greifen lassen — §L.
5. Secret-Scanner über Repo inkl. Content/User-Files — §I.

**Urteilsabhängig (Agent-/Human-Review):**
6. CSP für Frontend + separat `/cp` (Inertia/Vue + Live Preview kompatibel) — §G.
7. CP-Zugang härten (IP-Allowlist/Basic-Auth vorschalten, Route umbenennen, Session-Lifetime) — §C.
8. TLS/Redirect + Trusted Proxies auf Infra-Ebene — §B.
9. Sicherheits-Logging + Monitoring/Alerting für fehlgeschlagene CP-Logins — §K/§L.

**Bei künftigem Full Static Caching / SSG neu bewerten:** Header/HSTS/Redirect wandern auf Webserver/CDN (PHP wird teils umgangen); `recache_token` absichern; Cache-Control-Header für geschützte Inhalte — §B/§K.

**Heute N/A (bei Aktivierung reaktivieren):** Statamic API & GraphQL (`*_ENABLED=false`), Content-Protection (`protect.default=null`), öffentliche User-Registrierung, Git-Integration.
