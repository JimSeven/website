# Laravel Nightwatch — Preise, Self-hosting, Hosting-Region, DSGVO

**Fragestellung:** Faktenlage zu Laravel Nightwatch als Kandidat für Error-/Application-Monitoring in Produktion — Preismodell, Self-hosted-Option, Datenstandort/DSGVO, Funktionsumfang.
**Bezug:** Issue #31 („Error-Monitoring: ja/nein für Produktion (Sentry vs. Nightwatch)") — die dort als *unbelegt* markierten Punkte „Preismodell, Event-/Retention-Limits, Hosting-Region, DSGVO-/AVV-Lage, ob es überhaupt eine self-hosted-Variante gibt". Ergebnis der Grill-Session wird ADR-0002.
**Abrufdatum aller Quellen:** 2026-08-17
**Quellenklasse:** ausschließlich Primärquellen (Laravel-/Nightwatch-Produkt- und Doku-Seiten, offizielle Rechtstexte, `laravel/nightwatch` auf GitHub/Packagist, offizieller Changelog). Sekundärquellen sind nicht verwendet; Lücken stehen unter [Nicht belegbar](#nicht-belegbar).
**Kein Entscheidungsvorschlag** — dieses Dokument liefert nur Fakten und deren nüchterne Ableitung.

## 1. Preismodell

Abrechnungseinheit ist das **Event**, nicht Seat, nicht App, nicht Exception. Ein Event ist laut Hersteller „requests, outgoing requests, notifications, jobs, queries, mail, commands, cache, scheduled tasks, and exceptions" — jedes davon zählt einzeln ([Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs), [Events-Overview](https://nightwatch.laravel.com/docs/events)). Seats, Applications und Environments sind in **allen** Tiers unbegrenzt ([Pricing](https://laravel.com/nightwatch/pricing)).

| Tier | Preis/Monat | Inklusiv-Events | Overage | Lookback | Performance-Monitors (Thresholds/App) | Support |
|------|-------------|-----------------|---------|----------|----------------|---------|
| Free | $0 | 300k | $0.50 / 100k | 14 Tage | 1 | Community/Email |
| Pro | $20 | 7.5m | $0.35 / 100k | 30 Tage | 10 | Email |
| Team | $60 | 30m | $0.35 / 100k | 60 Tage | 20 | Email |
| Business | $300 | 180m | $0.20 / 100k | 90 Tage | 30 | Priority Email |
| Enterprise | „Contact sales" | — | — | — | — | — |

Alle Werte aus der [Pricing-Seite](https://laravel.com/nightwatch/pricing) (Tier-Karten + Feature-Vergleichstabelle). Währung USD; die [Terms of Service](https://laravel.com/legal/nightwatch/terms) legen fest: „all payments shall be made in United States Dollars". USt./VAT wird nach Rechnungsadresse automatisch berechnet, Tax-ID kann eingetragen werden ([Subscriptions](https://nightwatch.laravel.com/docs/subscriptions)).

Weitere belegte Konditionen:

- **Free-Tier:** dauerhaft, kein Trial nötig. Einschränkungen: „Free projects pause after 30 days of app inactivity. Max 3 free orgs per user." ([Pricing](https://laravel.com/nightwatch/pricing))
- **Quota-Überschreitung ohne Overage-Freigabe:** Ingestion pausiert für den Rest der Abrechnungsperiode, bereits gespeicherte Daten bleiben abrufbar ([Additional Events](https://nightwatch.laravel.com/docs/additional-events), [Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs)).
- **Overage-Mechanik:** Additional Events werden in Blöcken von 100.000 gemessen und *in arrears* am Zyklusende abgerechnet ([Additional Events](https://nightwatch.laravel.com/docs/additional-events)).
- **Spending-Cap:** Standardmäßig ist das monatliche Maximum die Subscription-Fee; Additional Events sind opt-in und mit einem selbstgesetzten Limit gedeckelt, bei dessen Erreichen die Ingestion automatisch stoppt ([Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs), [Pricing-FAQ](https://laravel.com/nightwatch/pricing)).
- **Billing-Zyklus:** monatlich, Anniversary-Billing pro Organization. Upgrade sofort mit Pro-rata-Gutschrift, Downgrade zum Periodenende ([Subscriptions](https://nightwatch.laravel.com/docs/subscriptions)).
- **Kostensteuerung:** Sampling pro Entry-Point (`NIGHTWATCH_REQUEST_SAMPLE_RATE` etc., Default jeweils `1.0`) und Filtering einzelner Event-Typen (`NIGHTWATCH_IGNORE_QUERIES`, `NIGHTWATCH_IGNORE_CACHE_EVENTS`, …) reduzieren das Event-Volumen; die Doku empfiehlt ausdrücklich „starting with the global sample rate of `0.1` or lower on requests" ([Filtering](https://nightwatch.laravel.com/docs/filtering), [Environment Variables](https://nightwatch.laravel.com/docs/environment-variables)).
- **Retention vs. Lookback:** Die Pricing-Tabelle nennt tierabhängige „Lookback period" (14/30/60/90 Tage); die FAQ auf derselben Seite sagt „By default, we store data for 90 days". Die beiden Angaben sind nicht deckungsgleich; die Terms behalten sich zudem vor, Retention-Zeiträume jederzeit zu ändern („We reserve the right to modify our retention periods and practices at any time"). Belegbar ist damit nur: **abfragbar** ist der Tier-Lookback ([Pricing](https://laravel.com/nightwatch/pricing), [Terms §13](https://laravel.com/legal/nightwatch/terms)).
- **Historie:** Der Changelog dokumentiert eine Anhebung („recent 50% event increase across all plans") und eine Senkung der Additional-Event-Preise, Eintrag vom 30.08.2025 ([Changelog](https://nightwatch.laravel.com/docs/changelog)). Die Tabelle oben ist der heute abgerufene Stand.

Ein **Jahres-/Annual-Plan** ist auf der Pricing-Seite und in der Subscriptions-Doku nicht erwähnt — siehe [Nicht belegbar](#nicht-belegbar).

### 1.1 Lässt sich das Volumen nach Response-Status filtern? (Nein)

Naheliegender Kostenhebel für eine überwiegend statische Site: Requests mit `200` gar nicht erfassen, nur Fehlerfälle. Das ist **nicht vorgesehen**:

| Mechanismus | Was er ist | Status-bedingt? |
|---|---|---|
| `sample_rate.*` (`requests`, `commands`, `exceptions`, `scheduled_tasks`) | feste Floats, Default `1.0` | **nein** — Zufallsrate, kein Prädikat, kein Sampler-Callback mit Request-/Response-Kontext |
| `ignore_queries`, `ignore_cache_events`, `ignore_mail`, `ignore_notifications`, `ignore_outgoing_requests` | Booleans pro Event-**Typ** | **nein** — global an/aus, nicht pro Vorgang |
| `Nightwatch::ignore(fn () => …)` | Scope-Wrapper: „filter out any events occurring **within the callback**" | **nein** — beim Betreten des Callbacks ist der Response-Status noch unbekannt; kein Post-hoc-Prädikat |
| `Nightwatch::pause()` / `resume()` | manuelles Aussetzen ab Aufrufzeitpunkt | **nein** — wirkt vorwärts, nicht rückwirkend |

Belege: [`config/nightwatch.php`](https://github.com/laravel/nightwatch/blob/1.x/config/nightwatch.php) (vollständige Schlüsselliste; kein Schlüssel referenziert Status, Response oder Outcome), [Filtering](https://nightwatch.laravel.com/docs/filtering) (Status-basiertes Filtern kommt dort nicht vor).

**Warum das konsistent ist:** Nightwatch aggregiert Requests zu Baseline-Metriken (Durchsatz, p95-Latenz, langsamste Routes). Erfolgreiche Requests sind dort die Bezugsgröße, nicht Rauschen — ohne sie fällt das Produkt auf Error-Tracking zurück. Der dokumentierte Hebel ist daher **Sampling statt Selektion**: Verteilung erhalten, Volumen senken. Offizielle Empfehlung: `NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1` bei `NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0` ([Filtering](https://nightwatch.laravel.com/docs/filtering)).

Damit ist die dem Ziel nächstliegende Konfiguration „alle Exceptions, ein Zehntel der Requests" — nicht „keine 200er". Ob ausgesampelte Events fürs Quota zählen, ist nicht dokumentiert; da der Agent sie nicht überträgt, ist Nicht-Abrechnung plausibel, aber **unbelegt**.

## 2. Self-hosted: nein (Service), ja (Client)

Die Unterscheidung, auf die #31 zielt, ist hier eindeutig belegt und fällt in zwei Teile auseinander.

**Backend/Ingest-Service: nicht self-hostbar.** Auf die FAQ-Frage „Do you have self-hosting options?" lautet die Antwort wörtlich: „Nightwatch is a fully-managed product." ([Pricing-FAQ](https://laravel.com/nightwatch/pricing), identisch in der [Docs-FAQ](https://nightwatch.laravel.com/docs/guides/faqs)). Das Produkt wird selbst als „hosted application monitoring platform" bezeichnet ([README](https://github.com/laravel/nightwatch)); die Architektur-Sektion der Produktseite nennt „Hosted Data Pipelines" als Laravel-Komponente ([Produktseite](https://laravel.com/nightwatch)).

**Client-Seite: MIT, quelloffen — beide Teile.**

| Komponente | Paket | Lizenz | Belegt durch |
|-----------|-------|--------|--------------|
| Laravel-Package (in der App) | `laravel/nightwatch`, aktuell `v1.28.7` (2026-08-13), 85 Releases seit `v1.0.0` (2025-02-13) | MIT | [Packagist-Metadaten](https://repo.packagist.org/p2/laravel/nightwatch.json), [GitHub-API](https://api.github.com/repos/laravel/nightwatch) |
| Agent (eigener Prozess) | `laravel/nightwatch-agent`, im Verzeichnis `agent/` desselben Repos, ausgeliefert als `agent/build/agent.phar` | MIT (`agent/LICENSE.md`) | [Repo-Baum `1.x`](https://github.com/laravel/nightwatch/tree/1.x/agent) |

Der Changelog-Launcheintrag führt „Open-sourced our Nightwatch agent" explizit als Feature ([Changelog](https://nightwatch.laravel.com/docs/changelog)). Die Terms definieren „Agent" entsprechend als „the open source software package provided by Laravel that is installed on a customer's systems … for the purpose of collecting and transmitting application monitoring data to Laravel Nightwatch" ([Terms](https://laravel.com/legal/nightwatch/terms)).

**Zum konfigurierbaren Ingest-Endpoint — Vorsicht vor einem Fehlschluss.** `NIGHTWATCH_INGEST_URI` (Default `127.0.0.1:2407`) ist *nicht* der Backend-Endpoint, sondern Host/Port des **lokalen** Agent-Prozesses, an den die App ihre Events schickt ([Environment Variables](https://nightwatch.laravel.com/docs/environment-variables), [`config/nightwatch.php`](https://github.com/laravel/nightwatch/blob/1.x/config/nightwatch.php)). Der Upload-Zielort steht nicht in der App-Config: der Agent authentifiziert sich gegen eine Base-URL und erhält die eigentliche `ingest_url` samt Bearer-Token als Antwort vom Server (`agent/src/IngestDetailsRepository.php`, `agent/src/Ingest.php`). Diese Base-URL ist im Agent-Quellcode über die **undokumentierte** Variable `NIGHTWATCH_BASE_URL` überschreibbar, Default `https://nightwatch.laravel.com` ([`agent/src/agent.php` Zeile 55](https://github.com/laravel/nightwatch/blob/1.x/agent/src/agent.php), vorhanden in [`agent/.env.example`](https://github.com/laravel/nightwatch/blob/1.x/agent/.env.example)). Das ist ein Umschalt-Punkt für den *Client*, kein Self-hosting-Pfad — es existiert keine bezieh- oder installierbare Server-Seite, die dort antworten würde.

**Betriebsvoraussetzung:** Der Agent ist ein dauerhaft laufender Prozess (`php artisan nightwatch:agent`), für den ein Process-Monitor „strongly recommended" ist; Health-Check über `php artisan nightwatch:status` (Exit-Code ≠ 0 bei Fehler). Pro Laravel-App ein eigener Agent-Prozess mit eigenem Port. Für serverlose Setups (Vapor) wird eine separate VM benötigt ([Start Guide](https://nightwatch.laravel.com/docs/start-guide), [Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs)). Es gibt ein offizielles Docker-Image für den Agent ([Changelog 30.08.2025](https://nightwatch.laravel.com/docs/changelog)).

**Anforderungen:** Laravel ≥ 10.0, PHP ≥ 8.2 ([Requirements](https://nightwatch.laravel.com/docs/requirements)). `composer.json` von `v1.28.7` deklariert `laravel/framework: ^10.0|^11.0|^12.0|^13.0` und `php: ^8.2` ([Packagist](https://repo.packagist.org/p2/laravel/nightwatch.json)) — Laravel 13 ist also unterstützt. Ein Teil der Features ist an höhere Framework-Minor-Versionen gebunden (z. B. Cache-Events ab Laravel 11.11) ([Requirements](https://nightwatch.laravel.com/docs/requirements)).

## 3. Hosting-Region, DSGVO/AVV, PII

### Region / Datenresidenz

Die **Data Region wird pro Application beim Anlegen gewählt** und „determines where all telemetry data for that application is stored". Verfügbar sind ([Organizations](https://nightwatch.laravel.com/docs/organizations), bestätigt im [Start Guide](https://nightwatch.laravel.com/docs/start-guide)):

| Region | Standort |
|--------|----------|
| US | Northern Virginia |
| EU | **Frankfurt** |
| Australia | Sydney |

Eine **EU-Region (Frankfurt) existiert also und ist wählbar.** Historie: Launch mit US- und EU-Residenz, Sydney per Changelog-Eintrag vom 16.09.2025 nachgezogen ([Changelog](https://nightwatch.laravel.com/docs/changelog)). Die FAQ auf der Pricing-Seite ist an dieser Stelle veraltet (nennt nur US/EU und Australien als „coming"); die Doku ist die aktuellere Primärquelle. Weitere Regionen auf Anfrage ([Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs)).

### Vertragspartner, AVV/DPA

- **Vertragspartner:** Laravel Holdings Inc., 60 Broad Street, 24th Floor #1559, New York, NY 10004, USA. Anwendbares Recht: State of New York; Streitbeilegung per Binding Arbitration (J.A.M.S., Venue New York) mit 30-Tage-Opt-out ([Terms §14](https://laravel.com/legal/nightwatch/terms)).
- **AVV/DPA:** Kein öffentlich abrufbares Dokument. Die Terms verweisen auf „Laravel's Data Processing Agreement, **which is available upon request**" und binden den Kunden vorab daran ([Terms §6](https://laravel.com/legal/nightwatch/terms)). Ein „Data Processing Agreement" ist im [Laravel Trust Center](https://trust.laravel.com) gelistet, aber hinter „Get access" / Access-Request gated.
- **Sub-Processor-Liste:** Im Trust Center unter „Legal → Subprocessors" vorhanden, ebenfalls nicht offen einsehbar. Öffentlich sichtbar sind auf der Übersichtsseite die Logos von **Sentry, Postmark, Cloudflare, Snowflake, Stripe**; Infrastruktur ist als **Amazon Web Services** ausgewiesen ([trust.laravel.com](https://trust.laravel.com)).
- **Scope-Vorbehalt:** Das Trust Center überschreibt sich selbst mit „Welcome to **Laravel Cloud's** Trust Center". Ob die dort geführten Nachweise (SOC 2 Type 2, GDPR, EU-US DPF, Pentest-Report) Nightwatch mit abdecken, geht aus der Seite nicht hervor — und steht im Widerspruch zur Nightwatch-eigenen Aussage (siehe nächster Punkt).
- **Zertifizierungen (Nightwatch-eigene Angabe):** „Nightwatch is actively pursuing SOC 2 Type 1 and Type 2 certifications" ([Pricing-FAQ](https://laravel.com/nightwatch/pricing), [Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs)). Die [Compliance-Doku](https://nightwatch.laravel.com/docs/compliance) nennt einen SOC-2-Type-1-Report „available by request in the coming weeks" und ISO 27001 als Ziel „H2 2025" — beides zum Abrufdatum nicht als erteilt ausgewiesen. Kontakt: nightwatch@laravel.com.
- **Privacy Policy:** greift für Nightwatch **nicht** als Regelwerk für die eingelieferten Telemetriedaten. Sie stellt explizit klar: „This Privacy Policy does not apply to any personal information customers provide in connection with their use of the services. Such personal information is subject to the data processing terms set forth in the customer agreement." Für die eigenen (Account-/Website-)Daten nennt sie US-Verarbeitung und Drittlandtransfers aus EWR/UK/CH „relying on appropriate safeguards"; Stand „Last updated: February 25, 2025" ([Privacy Policy](https://laravel.com/legal/nightwatch/privacy-policy)).
- **Sensible Daten vertraglich untersagt:** Die Terms verbieten das Einliefern von PHI/HIPAA-Daten und von Art.-9-DSGVO-Kategorien (Ethnie, politische Meinung, Religion, Gewerkschaft, Genetik, Biometrie, Gesundheit, Sexualleben, Vorstrafen) ohne vorherige schriftliche Zustimmung und legen die Filterpflicht beim Kunden ab: „You are solely responsible for implementing and maintaining appropriate data filtering controls within your application and Agent configuration" ([Terms §12](https://laravel.com/legal/nightwatch/terms)).

### Was an PII erfasst wird — und Scrubbing

Die Redaction läuft im MIT-lizenzierten Package **vor** der Übertragung: „This happens in the open source package installed in your application, so you have complete control over what data is sent" ([Pricing-FAQ](https://laravel.com/nightwatch/pricing)).

| Datenart | Default | Konfiguration |
|----------|---------|---------------|
| Request-URL, Route, Method, Status, **IP-Adresse**, Headers | erfasst | `NIGHTWATCH_REDACT_HEADERS`, Redaction-Callback für Requests (URL, IP, Headers, Payload) |
| Request-Header | erfasst, Default-Redaction: `Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN` | `NIGHTWATCH_REDACT_HEADERS` |
| **Request-Body/Payload** | **aus** — „Due to the possible sensitive nature of payload data, this setting is disabled by default" | opt-in per `NIGHTWATCH_CAPTURE_REQUEST_PAYLOAD=true`; dann Feld-Redaction `NIGHTWATCH_REDACT_PAYLOAD_FIELDS` (Default `_token,password,password_confirmation`) |
| **SQL-Query-Bindings** (Parameterwerte) | **nie erfasst**, by design: „Nightwatch does **not** capture or display these bound values by design … to protect your application from unintentionally leaking sensitive or personally identifiable information" | — (SQL-Statement selbst per `Nightwatch::redactQueries()` manipulierbar) |
| **User-Daten** authentifizierter Nutzer: `id`, `name`, `email` | erfasst | `Nightwatch::user()`-Callback; Felder weglassbar, aber „`id` will always be captured" |
| **Source-Code-Snippets** in Stacktraces | **an** | `NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE=false` |
| Exception-Message | erfasst | `Nightwatch::redactExceptions()` |
| Queries / Cache-Keys / Mail / Notifications / Outgoing Requests | erfasst | `NIGHTWATCH_IGNORE_*` (komplett aus) bzw. gezielte Filter- und Redaction-Callbacks |
| Logs | ab `NIGHTWATCH_LOG_LEVEL` (Default `env('LOG_LEVEL')`, sonst `debug`) | Log-Level |

Belege: [Environment Variables](https://nightwatch.laravel.com/docs/environment-variables), [`config/nightwatch.php`](https://github.com/laravel/nightwatch/blob/1.x/config/nightwatch.php), [Requests](https://nightwatch.laravel.com/docs/requests), [Queries](https://nightwatch.laravel.com/docs/queries), [Users](https://nightwatch.laravel.com/docs/user), [Exceptions](https://nightwatch.laravel.com/docs/exceptions), [Filtering](https://nightwatch.laravel.com/docs/filtering).

Zusätzlich: `Nightwatch::ignore(fn () => …)` sowie `Nightwatch::pause()` / `Nightwatch::resume()` schalten die Erfassung punktuell im Code ab ([Filtering](https://nightwatch.laravel.com/docs/filtering)).

## 4. Funktionsumfang (nur klassenrelevant)

Nightwatch erfasst **Exceptions und den restlichen Laravel-Stack in einem Modell**. Die belegten Event-Typen ([Events-Overview](https://nightwatch.laravel.com/docs/events)):

Requests · Queries (inkl. Transaktionen) · Outgoing Requests · Jobs (Queue) · Scheduled Tasks · Commands (Artisan) · Cache · Mail · Notifications · **Exceptions** · **Logs**

Verbindendes Konstrukt ist der „execution context": HTTP-Request, Artisan-Command oder Scheduled Task als Ursprung, alle Folge-Events als Kinder in einer Timeline. Erfassung ohne manuelle Instrumentierung ([Events-Overview](https://nightwatch.laravel.com/docs/events)).

Error-Tracker-typische Features sind vorhanden, nicht nur Rohdaten:

- **Issue-Tracking:** „Nightwatch automatically creates a new issue for each unhandled exception that is reported, and will associate any new occurrences of the same exception with the existing issue" — Gruppierung über Klasse + Code + File/Line (**nicht** über die Message, gleiche Stelle mit anderer Message = anderes Issue). Occurrence-Counts und betroffene User über 24h/7d/30d ([Exceptions](https://nightwatch.laravel.com/docs/exceptions)).
- **Release-/Deploy-Zuordnung:** `NIGHTWATCH_DEPLOY`, auto-detected auf Laravel Cloud, Forge und Vapor ([Environment Variables](https://nightwatch.laravel.com/docs/environment-variables)).
- **Alerting/Integrationen:** Slack, Linear, Custom Webhooks, MCP-Server ([Changelog](https://nightwatch.laravel.com/docs/changelog), Docs-Index [llms.txt](https://nightwatch.laravel.com/docs/llms.txt)).
- **Auto-Resolve** von Issues ohne Aktivität nach 7/14/30 Tagen ([Changelog](https://nightwatch.laravel.com/docs/changelog)).

**Ersatz oder Ergänzung?** Der Hersteller positioniert es als Ersatz-fähig — die Produktseite führt „error tracking" als Kernfunktion und hat eine eigene Sektion „Track exceptions and performance issues" ([Produktseite](https://laravel.com/nightwatch)). Parallelbetrieb mit einem anderen APM/Error-Tracker ist ausdrücklich möglich, aber mit kumulativem Overhead: „running multiple monitoring tools will have some cumulative performance impact that will need to be mitigated" ([Pricing-FAQ](https://laravel.com/nightwatch/pricing)). Telescope und Pulse werden weiter unterstützt und sind ein anderes Produkt ([Pricing-FAQ](https://laravel.com/nightwatch/pricing)). Overhead-Angabe: „typically less than 3ms per request", Agent läuft außerhalb des App-Prozesses ([Docs FAQ](https://nightwatch.laravel.com/docs/guides/faqs)).

Nicht Laravel-fremd nutzbar: „Nightwatch is purpose-built for Laravel … Supporting generic PHP would mean compromising on that experience" ([Pricing-FAQ](https://laravel.com/nightwatch/pricing)).

## Nicht belegbar

| Offene Frage | Was fehlt / warum |
|---|---|
| **AVV/DPA-Inhalt** (Standort der Verarbeitung vertraglich zugesichert? SCCs? EU-US-DPF-Zertifizierung für Nightwatch? TOMs? Sub-Processor-Change-Notification?) | Kein öffentliches Dokument. Terms sagen nur „available upon request" ([Terms §6](https://laravel.com/legal/nightwatch/terms)); im [Trust Center](https://trust.laravel.com) hinter Access-Request. **Fehlende Primärquelle:** der DPA-Text selbst — bei Laravel anzufordern (nightwatch@laravel.com bzw. Trust-Center-Access-Request). Vor einer produktiven Nutzung ohnehin nötig. |
| **Vollständige Sub-Processor-Liste** | Nur Logos auf der Trust-Center-Übersicht (Sentry, Postmark, Cloudflare, Snowflake, Stripe) + „Amazon Web Services". Die eigentliche Liste ist gated. **Fehlend:** die Subprocessors-Seite hinter dem Access-Request. |
| **Gilt das Trust Center (SOC 2 Type 2, GDPR, EU-US DPF) für Nightwatch?** | Die Seite bezeichnet sich als „Laravel Cloud's Trust Center", während Nightwatchs eigene FAQ/Compliance-Doku SOC 2 nur als „actively pursuing" führt. Widerspruch nicht aus Primärquellen auflösbar. **Fehlend:** eine Nightwatch-spezifische Zertifikatsaussage bzw. der SOC-2-Report-Scope. |
| **Ob die EU-Region strikte Datenresidenz bedeutet** (keine Verarbeitung/Support-Zugriff/Backups in den USA) | Die Doku sagt nur, die Region bestimme „where all telemetry data … is stored" ([Organizations](https://nightwatch.laravel.com/docs/organizations)). Zu Zugriff, Backups und Support-Zugriffen von den USA aus keine Aussage. **Fehlend:** DPA/TOM-Dokument. |
| **Jahres-/Annual-Billing und Rabatt darauf** | Nicht auf der [Pricing-Seite](https://laravel.com/nightwatch/pricing), nicht in [Subscriptions](https://nightwatch.laravel.com/docs/subscriptions) (dort nur monatliches Anniversary-Billing). Nicht widerlegt, nur nicht dokumentiert. |
| **Enterprise-Preis, -Volumen und -Retention** | „Contact sales"; Retention über 90 Tage nur „available on our enterprise plans" ohne Zahlen ([Pricing](https://laravel.com/nightwatch/pricing)). Nicht ohne Sales-Kontakt zu klären. |
| **Verbindliche Retention** | Pricing-Tabelle (Lookback 14/30/60/90 Tage) und Pricing-FAQ („we store data for 90 days") widersprechen sich; Terms behalten Änderungen jederzeit vor ([Terms §13](https://laravel.com/legal/nightwatch/terms)). Es gibt keine vertraglich fixierte Zahl in einer öffentlichen Quelle. |
| **Event-Volumen dieses Projekts** | Nicht aus Primärquellen zu Nightwatch beantwortbar — hängt an Traffic und Sampling. Im Repo liegen keine Traffic-Daten. Nur messbar, nicht recherchierbar. |
| **`NIGHTWATCH_BASE_URL` als unterstützter Umschaltpunkt** | Nur im Agent-Quellcode und in `agent/.env.example` vorhanden, in der offiziellen [Environment-Variables-Doku](https://nightwatch.laravel.com/docs/environment-variables) **nicht** dokumentiert. Als Feature also unbelegt — Implementierungsdetail, keine Zusage. |
| **Greift `exceptions`-Sampling unabhängig vom `requests`-Sampling?** | Entscheidend für die empfohlene Kombination `REQUEST_SAMPLE_RATE=0.1` + `EXCEPTION_SAMPLE_RATE=1.0` (siehe [1.1](#11-lässt-sich-das-volumen-nach-response-status-filtern-nein)): Wird eine Exception in einem **nicht** gesampelten Request trotzdem erfasst? Weder [Filtering](https://nightwatch.laravel.com/docs/filtering) noch [Exceptions](https://nightwatch.laravel.com/docs/exceptions) noch [Environment Variables](https://nightwatch.laravel.com/docs/environment-variables) sagen dazu etwas. Falls **nein**, erkauft `0.1` ein 90-%-Loch in der Fehlererfassung — also das Gegenteil des Zwecks. **Fehlend:** eine Herstelleraussage zur Interaktion der Sample-Rates; vor einer Ja-Entscheidung beim Support zu klären. |
| **Zählen ausgesampelte Events fürs Quota?** | Nicht dokumentiert ([Filtering](https://nightwatch.laravel.com/docs/filtering), [Additional Events](https://nightwatch.laravel.com/docs/additional-events)). Der Agent überträgt sie nicht, Nicht-Abrechnung ist daher plausibel — aber eine Inferenz, kein Beleg. |

## Konsequenz für #31

Nüchterne Ableitung aus den Fakten, ohne Empfehlung:

1. **Self-hosted vs. SaaS ist für Nightwatch keine offene Alternative, sondern entschieden.** „Nightwatch is a fully-managed product." Damit fällt der Trade-off „Betriebsaufwand gegen Datenhoheit" aus #31 für Nightwatch weg: wer Datenhoheit will, landet zwangsläufig in der anderen Kandidatenklasse (GlitchTip self-hosted). Dass Package *und* Agent MIT sind, ändert daran nichts — es gibt keine beziehbare Server-Seite.
2. **Der DSGVO-Blocker ist nicht die Region, sondern das Papier.** Eine EU-Region (Frankfurt) existiert und ist pro Application wählbar; der Vertragspartner ist ein US-Unternehmen (Laravel Holdings Inc., Recht New York). Der AVV ist nur auf Anfrage erhältlich, die Sub-Processor-Liste gated, und Nightwatch führt selbst keine erteilte SOC-2-/ISO-Zertifizierung. Für eine Ja-Entscheidung ist der DPA-Text also vorher anzufordern — das ist ein Vorgang mit Latenz, kein Prüfpunkt in der Session.
3. **Die Abrechnungseinheit passt nicht zur Fragestellung von #31.** Bezahlt werden Events des ganzen Stacks (Requests, Queries, Cache …), nicht Exceptions. Ein „nur Fehlerfälle erfassen"-Schalter existiert nicht (siehe [1.1](#11-lässt-sich-das-volumen-nach-response-status-filtern-nein)) — wer die Erfassung auf Fehler reduzieren will, will kein Observability-Produkt, und hat die Klassenfrage damit implizit beantwortet. Bei einer im Wesentlichen statischen Statamic-Site heißt das: die Kosten skalieren mit Traffic, nicht mit Fehlerhäufigkeit — genau umgekehrt zur Kostenkurve eines reinen Error-Trackers. Steuerbar ist das nur über Sampling/Filtering, d. h. über bewussten Sichtbarkeitsverzicht. Das Free-Tier-Budget von 300k Events/Monat und 14 Tagen Lookback ist die relevante Vergleichsgröße; welches Volumen dieses Projekt erzeugt, ist unbekannt und müsste gemessen werden.
4. **Nightwatch ist funktional ein Superset, nicht eine Ergänzung.** Exception-Grouping, Issue-Lifecycle, Occurrence-/User-Counts, Deploy-Zuordnung, Slack/Webhook-Alerting sind vorhanden — die Error-Tracking-Funktionen von #31 sind abgedeckt. Ein Parallelbetrieb mit Sentry ist möglich, aber laut Hersteller mit kumulativem Overhead. Die Klassenfrage aus #31 ist damit keine Funktions-, sondern eine Kosten-/Betriebs-/Datenschutz-Frage.
5. **Neuer Betriebsposten, der bei Sentry entfällt:** ein dauerhaft laufender Agent-Prozess pro App inkl. Process-Monitor und eigenem Health-Check (`nightwatch:status`). Das gehört auf dieselbe Deploy-/Infra-Ebene wie die `needs-infra`-Restpunkte (§B/§I/§J) im Ticket — und ist ein Punkt, den ein reines SDK-basiertes Error-Tracking nicht hat.
6. **PII-Baseline ist konservativ** und spricht nicht gegen Nightwatch: Query-Bindings werden per Design nie übertragen, Request-Payloads sind default-off, sensible Header werden default redigiert, Redaction läuft clientseitig im MIT-Package. Bewusst zu entscheiden bleiben zwei Defaults: erfasste User-`id` (nicht abschaltbar) sowie IP-Adressen und Source-Code-Snippets in Stacktraces (beide default an).

## Quellen

Alle URLs abgerufen am **2026-08-17**.

**Produkt- und Preisseiten (Laravel)**
- Produktseite: https://laravel.com/nightwatch (`nightwatch.laravel.com` → 301 auf diese URL)
- Pricing inkl. Feature-Vergleichstabelle und FAQ: https://laravel.com/nightwatch/pricing

**Offizielle Dokumentation (`nightwatch.laravel.com/docs`, jeweils auch als `.md`)**
- Doku-Index: https://nightwatch.laravel.com/docs/llms.txt
- Start Guide: https://nightwatch.laravel.com/docs/start-guide
- Requirements: https://nightwatch.laravel.com/docs/requirements
- Events (Overview): https://nightwatch.laravel.com/docs/events
- Requests: https://nightwatch.laravel.com/docs/requests
- Queries: https://nightwatch.laravel.com/docs/queries
- Exceptions: https://nightwatch.laravel.com/docs/exceptions
- Users: https://nightwatch.laravel.com/docs/user
- Filtering (Sampling/Filtering/Redaction): https://nightwatch.laravel.com/docs/filtering
- Environment Variables: https://nightwatch.laravel.com/docs/environment-variables
- Organizations (Data Regions): https://nightwatch.laravel.com/docs/organizations
- Subscriptions: https://nightwatch.laravel.com/docs/subscriptions
- Additional Events: https://nightwatch.laravel.com/docs/additional-events
- Compliance: https://nightwatch.laravel.com/docs/compliance
- FAQs: https://nightwatch.laravel.com/docs/guides/faqs
- Changelog: https://nightwatch.laravel.com/docs/changelog

**Rechtstexte (Laravel Holdings Inc.)**
- Terms of Service Nightwatch: https://laravel.com/legal/nightwatch/terms
- Privacy Policy: https://laravel.com/legal/nightwatch/privacy-policy (Stand 2025-02-25)
- Trust Center (Dokumente überwiegend gated): https://trust.laravel.com

**Paket / Quellcode**
- GitHub-Repo (Default-Branch `1.x`, MIT): https://github.com/laravel/nightwatch
- README: https://github.com/laravel/nightwatch/blob/1.x/README.md
- LICENSE (MIT): https://github.com/laravel/nightwatch/blob/1.x/LICENSE.md
- Config: https://github.com/laravel/nightwatch/blob/1.x/config/nightwatch.php
- Agent-Package (`laravel/nightwatch-agent`, MIT): https://github.com/laravel/nightwatch/tree/1.x/agent
- Agent-Entrypoint (`NIGHTWATCH_BASE_URL`, Zeile 55): https://github.com/laravel/nightwatch/blob/1.x/agent/src/agent.php
- Agent-Ingest-Auflösung (`ingest_url` vom Server): https://github.com/laravel/nightwatch/blob/1.x/agent/src/IngestDetailsRepository.php
- Packagist-Metadaten (`v1.28.7`, 2026-08-13, MIT, Laravel `^13` unterstützt): https://repo.packagist.org/p2/laravel/nightwatch.json · https://packagist.org/packages/laravel/nightwatch
- GitHub-API-Repo-Metadaten (Lizenz, Default-Branch): https://api.github.com/repos/laravel/nightwatch

**Nicht verwendet:** Blogposts (inkl. `blog.laravel.com`-Ankündigung), Foren-, Social-Media- und Video-Quellen sowie KI-Zusammenfassungen — gemäß Quellenregel in der Recherche-Aufgabe.
