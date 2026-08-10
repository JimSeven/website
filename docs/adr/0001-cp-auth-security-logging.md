---
status: accepted
---

# Security-Event-Logging & Alerting für CP-Auth

Das öffentlich erreichbare Statamic-Control-Panel (`/cp`) hatte kein Sicherheits-Logging für Auth-Ereignisse (Issue #23, OWASP A09). Wir loggen die CP-Auth-Events `Failed`, `Lockout`, `TwoFactorAuthenticationFailed` und `Login` (Erfolg) über einen Event-Listener strukturiert in einen dedizierten `security`-Log-Channel (`daily`-Driver, 90 Tage Retention) und alarmieren **nur** beim `Lockout`-Event per Slack-Webhook. Erfasst werden `event`, versuchter Username/E-Mail, IP, User-Agent, Guard, Zeitstempel (bei Erfolg zusätzlich die User-ID) — **niemals** das Passwort bzw. das `credentials`-Array des `Failed`-Events.

## Considered Options

- **Alert-Auslöser:** `Lockout` (gewählt) vs. Cache-basierter Schwellenwert-Zähler vs. Alert bei jedem Fehlschlag. `Lockout` (Statamics 4/min-Throttle greift) ist das rauschärmste echte Brute-Force-Signal ohne eigenen Zähler. Bewusst akzeptierte Grenze: ein verteilter Angriff mit rotierenden IPs trippt den Per-IP-Lockout nie — fängt das erzwungene 2FA ab (siehe #22).
- **Alert-Sink:** Slack-Webhook (gewählt, Gerüst in `config/logging.php` vorhanden, keine neue Dependency) vs. E-Mail vs. Sentry. Env-gated über `LOG_SLACK_WEBHOOK_URL`: ohne Webhook nur Log, kein Fehler.
- **Log-Ziel:** dedizierter `security`-Channel (gewählt, isoliert/greppbar, eigene Retention, SIEM-ready) vs. Default-`stack` mit Tag.

## Consequences

- Der versuchte Username wird bei Fehlschlägen mitgeloggt (milde PII/Enumeration im nicht-öffentlichen Logfile) — bewusster Trade-off für die Erkennung gezielter Konto-Angriffe.
- Generelles Error-Monitoring (Sentry o.ä.) ist **bewusst ausgeklammert** und als eigenes `ready-for-human`-Ticket ausgelagert.
- Aktivierung des Alertings erfordert einen Infra-Schritt: Slack-Webhook anlegen + `LOG_SLACK_WEBHOOK_URL` in Forge setzen.
