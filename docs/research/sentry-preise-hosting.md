# Sentry — Preise, Abrechnungseinheiten, Self-hosting, Region/DSGVO

**Fragestellung:** Faktenlage zu Sentry als Kandidat für Error-Monitoring in Produktion — **wie genau abgerechnet wird** (Kernfrage: getrennte Kategorien oder „alles über Events"?), aktuelle Plan-Zahlen, Status der self-hosted-Variante inkl. Lizenz, EU-Region und DSGVO-Papierlage, PII-Defaults des Laravel-SDK.
**Bezug:** Issue #31 („Error-Monitoring: ja/nein für Produktion (Sentry vs. Nightwatch)"). Gegenstück zu [`laravel-nightwatch-preise-hosting.md`](./laravel-nightwatch-preise-hosting.md) — gleiche Struktur, damit beide vergleichbar sind. Ergebnis der Grill-Session wird ADR-0002.
**Abrufdatum aller Quellen:** 2026-08-17
**Quellenklasse:** ausschließlich Primärquellen (sentry.io Pricing/Legal/Security, `docs.sentry.io`, `develop.sentry.dev`, `sentry.help`, `getsentry/*` auf GitHub, Packagist; für GlitchTip: glitchtip.com und das GitLab-Repo). Keine Vergleichsportale, keine Foren, keine Konkurrenz-Landingpages. Lücken stehen unter [Nicht belegbar](#nicht-belegbar).
**Kein Entscheidungsvorschlag** — dieses Dokument liefert nur Fakten und deren nüchterne Ableitung.

---

## 1. Abrechnungseinheiten und ihre Trennung

### 1.1 Getrennte Kategorien, getrenntes Inklusiv-Volumen

Sentry rechnet **nicht** über eine gemeinsame Event-Einheit ab, sondern **pro Datenkategorie mit je eigenem Inklusiv-Volumen und eigener Preisstaffel**. Wörtlich: „We bill based on the amount of data processed. Each paid plan comes with the below pre-set monthly event volume, which is included in the price" ([Pricing & Billing](https://docs.sentry.io/pricing/)).

Die Kategorien und ihr Zählmaß ([Pricing & Billing](https://docs.sentry.io/pricing/), [Billing Quota Management](https://docs.sentry.io/pricing/quotas/)):

| Kategorie | Zählmaß | Inklusiv (jeder bezahlte Plan) |
|---|---|---|
| **Errors** | pro Error-Event | 50.000 / Monat |
| Spans (Tracing) | pro Span | 5.000.000 / Monat |
| Session Replays | pro Replay | 50 / Monat |
| Logs | GB | 5 GB / Monat |
| Application Metrics | GB | 5 GB / Monat |
| Attachments | GB | 1 GB / Monat |
| Cron Monitors | pro Monitor (nicht pro Check) | 1 |
| Uptime Monitors | pro Monitor (nicht pro Check) | 1 |
| Continuous Profiling | Profile-Stunden | 0 — „available only through PAYG" |
| UI Profiling | Profile-Stunden | 0 — „available only through PAYG" |
| Size Analysis | Builds | 100 / Abrechnungsperiode |
| Seer (AI-Debugging) | Add-on, separat | 0 — nur mit Seer-Subscription |

„Each data category has its own quota that you can adjust. When Sentry tracks an event, log, metric, or attachment, it counts toward your quota for that type of data." ([Billing Quota Management](https://docs.sentry.io/pricing/quotas/)). Jede Kategorie hat außerdem eine **eigene Overage-Preistabelle** (siehe [§2.3](#23-overage-reserved-volume-payg)).

### 1.2 Kernfrage: reiner Error-Tracker möglich?

**Ja — und das ist der belegbare Kern des Unterschieds zu Nightwatch.**

Drei Belege zusammengenommen:

1. **Tracing ist im SDK per Default aus.** `traces_sample_rate` hat Default `null`, ebenso `traces_sampler`; die Doku sagt ausdrücklich: „Either this or `traces_sampler` must be defined **to enable tracing**" ([PHP/Laravel Configuration Options](https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/)). Die Tracing-Anleitung beginnt entsprechend mit „First, **enable** tracing and configure the sample rate" ([Set Up Tracing in PHP](https://docs.sentry.io/platforms/php/guides/laravel/tracing/)). Die publizierte Default-Config des Laravel-SDK enthält `'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE') === null ? null : …` ([`config/sentry.php`](https://github.com/getsentry/sentry-laravel/blob/master/config/sentry.php)).
2. **Logs sind per Default aus.** `enable_logs` Default `false` — „This option enables the logging integration … This is disabled by default" ([Options](https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/), bestätigt in [`config/sentry.php`](https://github.com/getsentry/sentry-laravel/blob/master/config/sentry.php)).
3. **Application Metrics sind per Default an (`enable_metrics` = `true`), senden aber nur explizit erzeugte Metriken.** „Metrics are enabled by default in the PHP SDK. After the SDK is initialized, you can send metric data through `\Sentry\traceMetrics()`" ([Application Metrics, Laravel](https://docs.sentry.io/platforms/php/guides/laravel/metrics/)). Ohne eigenen `traceMetrics()`-Aufruf entsteht kein Volumen.

Damit gilt: mit der Auslieferungs-Konfiguration des Laravel-SDK (nur DSN gesetzt) fließen **ausschließlich Error-Events** — Tracing, Logs, Replays, Profiling, Crons und Uptime sind entweder aus oder erfordern eigene Einrichtung. Bezahlt wird dann nur die Errors-Kategorie; die anderen Inklusiv-Volumina verfallen ungenutzt („Any unused reserved volume will expire at the end of each billing month", [Pricing & Billing](https://docs.sentry.io/pricing/)).

**Zählt ein fehlerfreier Request mit?** Nein. Ein Error-Event entsteht nur, wenn etwas geworfen/gemeldet wird: „Errors are sent every time an SDK catches a bug" ([Pricing-Kalkulator auf sentry.io/pricing](https://sentry.io/pricing/)); Errors sind „Exception-like occurrences captured automatically or manually" ([Billing Quota Management](https://docs.sentry.io/pricing/quotas/)). Requests, Queries, Cache-Zugriffe und Views werden nur dann zu Abrechnungseinheiten, wenn **Tracing aktiviert** ist — dann als Spans (`tracing.sql_queries`, `tracing.cache`, `tracing.views`, `tracing.http_client_requests` u. a. sind in `config/sentry.php` auf `true` vorbelegt, greifen aber nur bei gesetzter Sample-Rate).

**Was genau ein Error-Event kostet:** Nur *accepted* Events zählen — „Only accepted events affect your quota"; Events ohne gültige DSN/Projekt, nicht parsebare Events und Events ohne gültige Fingerprint-Information werden verworfen ([Manage Your Error Quota](https://docs.sentry.io/pricing/quotas/manage-event-stream-guide/), [Billing Quota Management](https://docs.sentry.io/pricing/quotas/)). Wichtig für die Kostenkurve: **jedes Vorkommen zählt**, nicht jedes Issue — auch bei „ignored" Alerts („a new event counts toward your quota because the event is still occurring") und bei Regressionen eines bereits resolvten Issues. Nur „Delete & Discard" stoppt die Zählung für die Zukunft ([Manage Your Error Quota](https://docs.sentry.io/pricing/quotas/manage-event-stream-guide/)).

Volumen-Dämpfer, jeweils serverseitig ohne Deploy: **Spike Protection** (per Projekt, droppt Ausschläge über dem Baseline-Schwellwert, „ensuring that you don't get charged for the excess volume"), **Inbound Filters** (vor dem Rate-Limit angewandt, gefilterte Events zählen nicht), **Rate Limits pro Projekt-Key** (droppt mit HTTP 429 — **erfordert Business oder Enterprise**) ([Manage Your Error Quota](https://docs.sentry.io/pricing/quotas/manage-event-stream-guide/), [Billing Quota Management](https://docs.sentry.io/pricing/quotas/)). SDK-seitig: `sample_rate` (Default `1.0`), `ignore_exceptions`, `before_send` ([Options](https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/)).

---

## 2. Konkrete Zahlen der aktuellen Pläne

Alle Werte von der [Pricing-Seite](https://sentry.io/pricing/) und aus [docs.sentry.io/pricing](https://docs.sentry.io/pricing/), abgerufen 2026-08-17. Währung USD, Preise exkl. Steuern („prices displayed on our pricing page don't include sales tax"); in EU/UK wird VAT erhoben, sofern keine gültige VAT-ID/Exemption vorliegt ([Pricing & Billing → Taxes](https://docs.sentry.io/pricing/)).

### 2.1 Pläne, Preise, Seats

| Plan | Preis/Monat (monatliche Zahlung) | Preis/Monat (jährliche Zahlung) | Jahressumme | Users |
|---|---|---|---|---|
| **Developer** (Free) | $0 | $0 | $0 | **1 User** |
| **Team** | **$29** | **$26** | $312/Jahr | Unlimited |
| **Business** | **$89** | **$80** | (nicht ausgewiesen) | Unlimited |
| **Enterprise** | Custom / „Contact Sales" | — | — | Unlimited |

**Seats kosten nichts extra.** Team, Business und Enterprise weisen „Unlimited users" und „Unlimited projects" aus; nur das Free-Tier ist auf einen User begrenzt ([Pricing](https://sentry.io/pricing/)). Der einzige nutzerbezogene Preis ist das **Seer-Add-on**: „$40 per active contributor per month, where an active contributor is defined as any user who makes 2 or more PRs to a Seer-Enabled repository" — separat berechnet, zählt **nicht** gegen das PAYG-Budget ([Pricing & Billing → Seer Pricing](https://docs.sentry.io/pricing/)).

**Monats- vs. Jahresbilling:** Die Pricing-Seite hat einen „Monthly/Annual"-Umschalter mit dem Hinweis „Save with annual"; die auf der Seite gerenderten $26/$80 sind ausdrücklich „When billed annually with default pre-paid data". Die Monatspreise $29 bzw. $89 stehen im Datensatz der Pricing-Seite selbst (`prices:{monthly:29,annual:26}` bzw. `{monthly:89,annual:80}` im Island-Bundle [`/_astro/PricingPage.B1RWnP2r.js`](https://sentry.io/_astro/PricingPage.B1RWnP2r.js), geladen von [sentry.io/pricing](https://sentry.io/pricing/)) — Rabatt also **ca. 10 %** (Team 10,3 %, Business 10,1 %). Abrechnungsmodus laut Doku: „Annual Subscriptions: You will be billed annually at the beginning of your billing cycle for your paid plan and reserved volume. If you use PAYG, you will receive a monthly bill for this usage." ([Pricing & Billing](https://docs.sentry.io/pricing/)). Der Checkout weist zusätzlich „Annual and prepaid billing options with additional discounts" aus, ohne Zahlen ([Pricing](https://sentry.io/pricing/)).

### 2.2 Inklusiv-Volumen und Limits pro Plan

| | Developer | Team | Business | Enterprise |
|---|---|---|---|---|
| Users | 1 | Unlimited | Unlimited | Unlimited |
| Projects | Unlimited | Unlimited | Unlimited | Unlimited |
| **Errors** | **5k** | **50k** | **50k** | Custom |
| Logs | 5 GB | 5 GB (+$0.50/GB) | 5 GB (+$0.50/GB) | Custom |
| Application Metrics | 5 GB | 5 GB (+$0.50/GB) | 5 GB (+$0.50/GB) | Custom |
| Tracing | 5M Spans | 5M Spans | 5M Spans | Custom |
| Session Replay | 50 | 50 | 50 | Custom |
| Uptime Monitors | 1 | 1 (+$1.00/Monitor) | 1 (+$1.00/Monitor) | Custom |
| Cron Monitors | 1 | 1 (+$0.78/Monitor) | 1 (+$0.78/Monitor) | Custom |
| Metric Monitors | 20 | 20 | 1.000 | Custom |
| UI Profiling | — | PAYG erforderlich, +$0.25/h | PAYG erforderlich, +$0.25/h | Custom |
| Continuous Profiling | — | PAYG erforderlich, +$0.0315/h | PAYG erforderlich, +$0.0315/h | Custom |
| Attachments | 1 GB | 1 GB | 1 GB | Custom |
| Size Analysis | 100 Builds | 100 Builds | 100 Builds | Custom |
| Custom Dashboards | 10 | 20 | Unlimited | Unlimited |
| Data retention (Anzeige) | 30-day lookback | Up to 90-day lookback | Up to 90-day lookback + additional sampled retention | Custom |
| Spend notifications / max. Spend-Threshold | – | ✓ | ✓ | ✓ |
| Inbound filtering | ✓ | ✓ | ✓ | ✓ |
| Advanced inbound filtering | – | – | ✓ | ✓ |
| SSO Google/GitHub | – | ✓ | ✓ | ✓ |
| SSO SAML2 / SCIM | – | – | ✓ | ✓ |
| Manage PII | ✓ | ✓ | ✓ | ✓ |
| SOC2 / ISO 27001 / Data Residency | ✓ | ✓ | ✓ | ✓ |
| BAA (HIPAA) | – | – | ✓ | ✓ |
| Relay | – | – | ✓ | ✓ |
| Support | Community (GitHub/Discord) + Email | + Email | + Email | + TAM, Premium CS |

Quelle: Vergleichstabelle „A closer look at each plan" auf [sentry.io/pricing](https://sentry.io/pricing/) (Checkmark-Belegung aus dem Datensatz derselben Seite, [PricingPage-Bundle](https://sentry.io/_astro/PricingPage.B1RWnP2r.js)).

**Verbindliche Retention** (eigene Doku-Seite, präziser als die Marketing-Tabelle) — „Retention periods are set at the time data is ingested, based on the then-current plan"; Plan-Wechsel wirken nur auf neue Daten ([Data Retention Periods](https://docs.sentry.io/security-legal-pii/security/data-retention-periods/)):

| Datenart | Developer | Team | Business/Enterprise |
|---|---|---|---|
| **Errors** | 30 Tage | **90 Tage** | **90 Tage** |
| Logs | 30 | 30 | 30 |
| Spans/Transactions | 30 | 30 | 30 + 13 Monate sampled |
| Session Replays | 30 | 90 | 90 |
| Profiles | 30 | 30 | 30 |
| Crons | 30 | 30 | 30 |
| Uptime | 30 | 90 | 90 |
| Attachments | 30 | 90 | 90 |
| Application Metrics | 30 | 30 | 30 |
| Size Analysis | 30 | 30 | 13 Monate |

### 2.3 Overage: Reserved Volume, PAYG

Zwei Mechanismen, beide über dem Inklusiv-Volumen ([Pricing & Billing](https://docs.sentry.io/pricing/)):

- **Reserved volume** — „A predetermined amount of data you pay for in advance at a discount, on a monthly or annual basis." Ungenutztes Reserved Volume verfällt monatlich.
- **Pay-as-you-go (PAYG) budget** — „A PAYG budget is shared among all categories on a first-come, first-served basis and covers any overages above your reserved volume. You will only be charged for what you use up to your PAYG budget."

**Harte Deckelung statt offener Overage:** „Any data sent after you've run through your reserved volume and PAYG budget will be **dropped and you won't be charged** for it. This means that you'll lose monitoring for the remainder of the billing cycle" ([Pricing & Billing](https://docs.sentry.io/pricing/)). Das PAYG-Budget **ist** der Spend-Cap; zusätzlich existieren „Spend notifications" und „Set maximum spend threshold" ab Team ([Pricing](https://sentry.io/pricing/)). Budget-Erhöhung wirkt mid-cycle „within minutes … maximum turnaround time of 24 hours"; Senkung unter den bereits verbrauchten Betrag wird auf den Verbrauch angehoben und erst zum nächsten Billing-Datum wirksam. Upgrade Team → Business rechnet ein bestehendes PAYG-Budget auf Business-Sätze um, „your PAYG budget will be consumed faster than it was before" ([Pricing & Billing](https://docs.sentry.io/pricing/)).

**Errors — Preis pro Event** (gerundet laut Doku, [Pricing & Billing → Errors Pricing](https://docs.sentry.io/pricing/)):

| Error-Volumen | Team Reserved | Team PAYG | Business Reserved | Business PAYG |
|---|---|---|---|---|
| >50k–100k | $0.0002900 | $0.0003625 | $0.0008900 | $0.0011125 |
| >100k–500k | $0.0001750 | $0.0002188 | $0.0005000 | $0.0006250 |
| >500k–10M | $0.0001500 | $0.0001875 | $0.0003000 | $0.0003750 |
| >10M–20M | $0.0001300 | $0.0001625 | $0.0002600 | $0.0003250 |
| >20M | $0.0001200 | $0.0001500 | $0.0002400 | $0.0003000 |

Zum Größenvergleich: 100k Errors/Monat auf Team = 50k Inklusiv + 50k × $0.0003625 ≈ **$18 PAYG** zusätzlich zu $26 Basis. Business ist pro Error-Event **rund 3× teurer** als Team (0,0011125 vs. 0,0003625 in der ersten Staffel).

Die übrigen Kategorien ([Pricing & Billing](https://docs.sentry.io/pricing/)):

| Kategorie | Satz |
|---|---|
| Logs | $0.50/GB (nur PAYG) |
| Application Metrics | $0.50/GB (nur PAYG) |
| Spans | >5M–100M: $0.0000016 reserved / $0.0000020 PAYG (Team); >100M: $0.0000014/$0.0000018. Business ~2× |
| Replays | >50–5k: $0.0030 reserved / $0.00375 PAYG (Team = Business), fallend bis $0.001962/$0.002453 ab 4,5M |
| Attachments | >1 GB: $0.2500 reserved / $0.3125 PAYG |
| Cron Monitors | $0.78/Monitor (nur PAYG) |
| Uptime Monitors | $1.00/Monitor (nur PAYG) |
| Continuous Profile Hours | $0.0315/h (nur PAYG) |
| UI Profile Hours | $0.25/h (nur PAYG) |
| Size Analysis > 100 Builds | nur über Enterprise-Plan, Preis per Sales |
| Seer | $40/active contributor/Monat, separate Rechnung, nicht aus dem PAYG-Budget |

### 2.4 Free-Tier und Trials

- **Developer-Plan:** dauerhaft $0, **1 User**, unbegrenzte Projekte, **5k Errors/Monat**, 5 GB Logs, 5 GB Metrics, 5M Spans, 50 Replays, 1 Uptime- + 1 Cron-Monitor, 1 GB Attachments, 10 Custom Dashboards, MCP-Zugang, **30 Tage Retention**, Community- + Email-Support. Kein „Set maximum spend threshold", keine Spend notifications, kein SSO, kein Advanced Inbound Filtering, kein Relay ([Pricing](https://sentry.io/pricing/)).
- **Trials:** „All new Sentry accounts come with a 14-day free trial period" (Business-Features). Zusätzlich einmalige **14-tägige Produkt-Trials** pro Produkt (z. B. Tracing, Session Replay) mit unbegrenztem Volumen während des Trials und ohne Nachberechnung; „each org can only trial each product once" ([Pricing & Billing → Plans and Free Trials](https://docs.sentry.io/pricing/)).
- **Aktion zum Abrufdatum:** „5,000 Session Replays on us — New users only: Get 5,000 free replays per month for your first 3 months." ([Pricing](https://sentry.io/pricing/)).
- **Plan-Wechsel:** „Plan upgrades take effect immediately. Plan downgrades and cancellations are processed at the end of the current contract cycle and cannot be refunded." ([Pricing & Billing](https://docs.sentry.io/pricing/)).

---

## 3. Self-hosted: existiert offiziell — aber nicht Open Source

### 3.1 Sentry self-hosted

**Existenz:** ja, offiziell und aktiv gepflegt. Repo [`getsentry/self-hosted`](https://github.com/getsentry/self-hosted) — „Sentry, feature-complete and packaged up for **low-volume deployments and proofs-of-concept**"; letzter Push 2026-08-17 ([GitHub-API](https://api.github.com/repos/getsentry/self-hosted)). Doku: [develop.sentry.dev/self-hosted](https://develop.sentry.dev/self-hosted/).

**Lizenz — nicht OSI-Open-Source.** Sowohl [`getsentry/sentry/LICENSE.md`](https://github.com/getsentry/sentry/blob/master/LICENSE.md) („Copyright 2008-2024 Functional Software, Inc. dba Sentry") als auch [`getsentry/self-hosted/LICENSE.md`](https://github.com/getsentry/self-hosted/blob/master/LICENSE.md) („Copyright 2016-2024") sind **Functional Source License, Version 1.1, Apache 2.0 Future License (`FSL-1.1-Apache-2.0`)**. Die GitHub-API klassifiziert beide als `NOASSERTION` / „Other" ([API sentry](https://api.github.com/repos/getsentry/sentry), [API self-hosted](https://api.github.com/repos/getsentry/self-hosted)). Kernbedingungen wörtlich aus der LICENSE:

- Erlaubt ist jede Nutzung außer einer „Competing Use": „making the Software available to others in a commercial product or service that: 1. substitutes for the Software; 2. substitutes for any other product or service we offer using the Software …; or 3. offers the same or substantially similar functionality."
- Ausdrücklich erlaubt: „for your **internal use and access**", non-commercial education/research, sowie „in connection with professional services that you provide to a licensee using the Software in accordance with these Terms and Conditions."
- **Zeitversetzte Öffnung:** „We hereby irrevocably grant you an additional license to use the Software under the Apache License, Version 2.0 that is effective on the **second anniversary** of the date we make the Software available."
- Patent-Retaliation-Klausel; Redistribution nur mit Lizenz-Beilage; keine Markenrechte.

Für den Fall #31 relevant: Eigenbetrieb für die eigene Website ist „internal use" und damit gedeckt; ein Betrieb *für Kunden* als Teil eines eigenen kommerziellen Angebots wäre an „professional services … provided to a licensee" zu prüfen — der Kunde müsste selbst Lizenznehmer sein. **Kein OSI-Open-Source, kein MIT/BSD.** (Zum Kontrast: das Laravel-**SDK** [`getsentry/sentry-laravel`](https://github.com/getsentry/sentry-laravel/blob/master/LICENSE) ist MIT.)

**Betriebsaufwand laut offizieller Doku** ([develop.sentry.dev/self-hosted](https://develop.sentry.dev/self-hosted/)):

- Mindest-Ressourcen: **4 CPU Cores, 16 GB RAM + 16 GB Swap, 20 GB freier Plattenplatz**; 32 GB RAM empfohlen.
- Docker ≥ 19.03.6, Docker Compose ≥ 2.32.2.
- Komponenten im Compose-Stack (Verzeichnisse/Dateien im Repo): `sentry`, `snuba`, **`clickhouse`**, `relay`, `symbolicator`, `taskbroker`, `redis`, `cron`, `nginx`, `geoip`, `certificates` ([Repo-Baum](https://github.com/getsentry/self-hosted)).
- **Kein Support:** „no guarantees or dedicated support" — Sentry-Engineers helfen gelegentlich, Support ist Community-Sache.
- Funktionsumfang: die Doku beschreibt self-hosted als „the Business plan without any software limitations and no paid tier". **Nicht enthalten:** Billing/Plan-Tiers, **Spike Protection** (an Billing gekoppelt), die AI-/Seer-Features (proprietär), iOS-Symbolication und PlayStation-Support, eingeschränkte Mobile-Stacktrace-Auflösung.

### 3.2 GlitchTip (Sentry-API-kompatible Alternative)

| Punkt | Fakt | Quelle |
|---|---|---|
| Betreiber | „GlitchTip was created by **Burke Software and Consulting**, a small team of software developers doing consulting work and supporting open source software development." | [glitchtip.com](https://glitchtip.com/) |
| Lizenz | **MIT** („MIT License, Copyright (c) 2019 GlitchTip") | [`glitchtip-backend/LICENSE`](https://gitlab.com/glitchtip/glitchtip-backend/-/blob/master/LICENSE) |
| Repos | `glitchtip/glitchtip-backend` (Django), `glitchtip/glitchtip-frontend` — beide aktiv (letzte Aktivität 2026-08-17 bzw. 2026-08-15); GitLab-API weist **kein** Lizenzfeld aus, die LICENSE-Datei ist MIT | [GitLab-API backend](https://gitlab.com/api/v4/projects/glitchtip%2Fglitchtip-backend), [frontend](https://gitlab.com/api/v4/projects/glitchtip%2Fglitchtip-frontend) |
| SDK-Kompatibilität | „Our app is **compatible with Sentry client SDKs**, but easier to run." / „GlitchTip can use Sentry's open source SDKs to receive error data from your application." | [glitchtip.com](https://glitchtip.com/) |
| Funktionsumfang | Error Tracking (inkl. Log-Messages und CSP-Violations), Performance Monitoring, Uptime Monitoring, Logs, Integrations, MCP, CLI (Beta). Positionierung: „No dashboard building and metrics hunting." | [glitchtip.com](https://glitchtip.com/), [Documentation](https://glitchtip.com/documentation/) |
| **Abrechnung (hosted)** | **Ein gemeinsamer Event-Zähler** — Free $0/1.000 Events/Mo · Small $15/100k · Medium $50/500k · Large $250/3M. Performance-Events zählen in denselben Topf: „Performance events are one of the most common sources of excess events causing organizations … to go over their monthly event limits." | [Pricing](https://glitchtip.com/pricing), [FAQ](https://glitchtip.com/documentation/frequently-asked-questions) |
| Self-hosting-Aufwand | PostgreSQL 14+, ein Service (oder getrennt web/worker), Valkey/Redis 7+ optional. „Recommended system requirements: **512 MB RAM**, x86 or arm64 CPU"; Minimum 256 MB im All-in-one-Setup; „a 1 million event per month instance may require 30GB of disk". Docker Compose, Migrationen laufen automatisch beim Start. „Major version upgrades happen roughly once a year and may include breaking changes." Non-Docker-Installation „is not recommended". | [Install](https://glitchtip.com/documentation/install) |
| DSGVO-Papier | „We offer **DPAs upon request**, based on the Proton Mail DPA"; „We **self-certify** our compliance". Keine SOC-2-/ISO-Aussage auf den offiziellen Seiten. BAA „available upon request" nur im Large-Plan. | [FAQ](https://glitchtip.com/documentation/frequently-asked-questions), [Pricing](https://glitchtip.com/pricing) |

Der Ressourcen-Unterschied ist die belegbare Kernaussage: **512 MB RAM empfohlen (GlitchTip) gegen 4 Cores / 16 GB RAM + 16 GB Swap (Sentry self-hosted)** — zwei Größenordnungen, weil GlitchTip ohne ClickHouse/Snuba/Symbolicator/Relay/Taskbroker auskommt.

---

## 4. Region, DSGVO, PII

### 4.1 EU-Region

**Existiert und ist wählbar — aber nur beim Anlegen der Organisation und danach nicht wechselbar** ([Data Storage Location (US or EU)](https://docs.sentry.io/organization/data-storage-location/)):

| Region | Standort | API-Domain |
|---|---|---|
| US | Iowa, USA | `us.sentry.io` |
| **EU** | **Frankfurt, Deutschland** | `de.sentry.io` |

- Wahl: „You can choose where to store your data when you're setting up your Sentry account by selecting from the dropdown menu under 'Data Storage Location'."
- **Nicht nachträglich wechselbar:** „Once selected, your data storage location **can't be changed**. The only way to switch it is by creating a new organization." Für bestehende SaaS-Orgs gibt es keine Migration.
- In der gewählten Region: „Error events, activity, and issue links, Transactions, Spans, Profiles, Logs, Metrics, Release health, Releases, debug symbols, and source maps".
- **Immer in den USA, unabhängig von der Regionswahl:** „User accounts, notification settings, and 2FA authenticators … Organization integration metadata … Access tokens … Organization settings, configurations, and teams".
- Uptime-Checks laufen global aus mehreren Geolokationen.
- Data Residency ist laut Plan-Vergleich in **allen** Plänen inkl. Developer verfügbar ([Pricing](https://sentry.io/pricing/)).

### 4.2 Vertragspartner, DPA/AVV, Sub-Processors, Zertifizierungen

- **Vertragspartner:** **Functional Software, Inc. d/b/a Sentry** (US). Anwendbares Recht der ToS: „This Agreement is governed by the laws of the State of **California** and the United States"; Gerichtsstand San Francisco. ToS Version 3.0.0, Stand **2024-02-12** ([Terms of Service](https://sentry.io/terms/), Übersicht [sentry.io/legal](https://sentry.io/legal/)).
- **DPA/AVV: öffentlich im Volltext lesbar** — Version **5.1.0 (2024-05-29)**, inkl. Versionshistorie zurück bis 1.0.0 (2018) ([DPA](https://sentry.io/legal/dpa/)). Das ist der deutlichste Unterschied zu Nightwatch, wo der DPA nur „upon request" existiert.
  - Abschluss ist **self-service**: „entered into by and between Functional Software, Inc. d/b/a Sentry … and the party that **electronically accepts** or otherwise agrees or opts-in to this DPA". Praktisch über „Legal & Compliance" in den Organization-Settings, unterschreibbar nur von Owner/Billing-Rollen, alternativ per DocuSign; gilt für **alle Pläne** ([Help-Center: How do I sign your Data Processing Addendum](https://www.sentry.help/en/articles/13965008-how-do-i-sign-your-data-processing-addendum)). Kein Sales-Kontakt, keine Latenz.
  - **Transfer-Mechanismus:** primär EU-U.S. Data Privacy Framework (plus Swiss- und UK-Extension); fällt DPF weg, greifen die **EU-Standardvertragsklauseln**: Modul 2 (Controller→Processor) bzw. Modul 3, „by entering into this DPA, each party is deemed to have signed the SCCs (including their Annexes)"; SCC-Recht = Irland, Gerichtsstand irische Gerichte; UK-Addendum und Swiss-Anpassungen sind eingebaut ([DPA Schedule 3](https://sentry.io/legal/dpa/)).
  - **Datenstandort vertraglich:** §6.1 erlaubt ausdrücklich US-Verarbeitung: „You agree that we may … store and process Customer Data in the **United States and any other country** in which we or our Subprocessors maintain data processing operations." Die technische EU-Region schränkt das im Vertragstext nicht ein.
  - **Sub-Processor-Wechsel:** „We will provide **thirty (30) days' prior written notice** to you via email"; Widerspruchsrecht mit Kündigung als „sole remedy" ([DPA §7](https://sentry.io/legal/dpa/)).
- **Sub-Processor-Liste: öffentlich**, Stand **2026-06-01**, mit RSS-Feed für Änderungen ([Subprocessors](https://sentry.io/legal/subprocessors/)):

| Sub-Processor | Standort | Zweck |
|---|---|---|
| Amazon Web Services, Inc. | EU, US | Cloud-Infrastruktur |
| Google LLC (GCP) | EU, US | Cloud-Infrastruktur |
| Cloudflare, Inc. | EU, US | Cloud-Infrastruktur |
| Anthropic, PBC | US | AI/ML |
| OpenAI, L.L.C. | US | AI/ML |
| Intercom, Inc. | US | Support/Messaging |
| Sinch Email (Mailgun) | EU | E-Mail-Versand |
| Twilio Inc. (SendGrid) | US | E-Mail-Versand |
| *Affiliates:* Functional Software GmbH | Österreich | Leistungserbringung/Support |
| *Affiliates:* Sentry Software Netherlands B.V. | Niederlande | Leistungserbringung/Support |
| *Affiliates:* Sentry Software Canada Inc. | Kanada | Leistungserbringung/Support |

- **Zertifizierungen:** die Security-Seite nennt **SOC2 Type I**, **SOC2 Type II**, **HIPAA Attestation** und **ISO 27001** als vorhanden ([Security & Compliance](https://sentry.io/security/)). Zugriff: „You can find a copy of Sentry's latest SOC2 report and ISO 27001 certificate by visiting *Your Organization's Settings > Legal & Compliance*" ([SOC 2 Docs](https://docs.sentry.io/security-legal-pii/security/soc2/)) — also für Kunden im Produkt, für Nicht-Kunden per Sales-Anfrage. **Der Scope der Reports ist öffentlich nicht einsehbar.** Weitere belegte Angaben: „All data in Sentry servers is encrypted at rest" (AES-256), Transport ausschließlich über HTTPS/TLS, Key-Management über GCP, „annual penetration testing conducted by an independent, third-party agency" (Summary auf Anfrage).
- **Verbotene Datenarten (ToS):** ohne BAA (Business+) kein PHI; „Customer must not use the Service with **Sensitive Personal Information**" außer nach §4.5; personenbezogene Daten setzen einen abgeschlossenen DPA voraus. Nach Vertragsende: „Sentry will delete Service Data in accordance with its standard schedule and procedures" ([Terms of Service](https://sentry.io/terms/)).

### 4.3 Was das Laravel-SDK per Default erfasst

`send_default_pii` ist **`false`** (Default in [Options](https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/) und in der publizierten [`config/sentry.php`](https://github.com/getsentry/sentry-laravel/blob/master/config/sentry.php)) — „If this flag is enabled, certain personally identifiable information (PII) is added by active integrations. This option is turned off by default."

| Datenart | Default | Quelle/Schalter |
|---|---|---|
| HTTP-Header | **nicht gesendet** — „By default, the Sentry SDK doesn't send any HTTP headers" | `send_default_pii=true` nötig |
| Cookies | **nicht gesendet**; zusätzlich „Sentry tries to remove any cookies that contain sensitive information (such as the Laravel Session, Remember Token and CSRF Token cookies)" | `send_default_pii=true` |
| Angemeldeter User (E-Mail, ID, Username) | **nicht gesendet** | `send_default_pii=true` |
| **IP-Adresse** | **nicht gesendet** | `send_default_pii=true` |
| **Request-URL** | **immer gesendet** — „always sent to Sentry. Depending on your application, this could contain PII data" | serverseitiges Scrubbing / `before_send` |
| **Request-Query-String** | **immer gesendet** | s. o. |
| **Request-Body** | JSON- und Form-Bodies **werden gesendet**, sofern klein genug: `max_request_body_size` Default **`medium`** (typisch 10 KB). Raw Bodies werden immer entfernt, Uploads „never sent" | `max_request_body_size='never'` |
| **Source-Code-Snippet** um die Fehlerzeile | **an** | `context_lines=0` |
| **Lokale Variablen im Stacktrace** (Namen + Werte) | **an** | `zend.exception_ignore_args=1` in der `php.ini` |
| Breadcrumbs: Logs, Cache, SQL-Queries, Queue, Commands, HTTP-Client, Notifications, Livewire | **an** (`max_breadcrumbs` 100) | `SENTRY_BREADCRUMBS_*_ENABLED=false` |
| **SQL-Query-Bindings** (Parameterwerte) | **aus** — `breadcrumbs.sql_bindings` und `tracing.sql_bindings` beide Default `false` | opt-in |
| Logs als eigene Kategorie | **aus** (`enable_logs=false`) | opt-in |
| Tracing/Spans | **aus** (`traces_sample_rate=null`) | opt-in |
| Health-Route `/up` | per Default ignoriert (`ignore_transactions => ['/up']`) | — |

Belege: [Data Collected (Laravel)](https://docs.sentry.io/platforms/php/guides/laravel/data-management/data-collected/), [Configuration Options](https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/), [`config/sentry.php`](https://github.com/getsentry/sentry-laravel/blob/master/config/sentry.php).

**Serverseitiges Scrubbing ist per Default aktiv:** „Data scrubbing is enabled by default and we highly recommend you keep it that way." Automatisch redigiert werden kreditkartenähnliche Werte (Regex) sowie Werte, deren Key oder Inhalt eines der folgenden enthält: `password`, `secret`, `passwd`, `api_key`, `apikey`, `auth`, `credentials`, `mysql_pwd`, `privatekey`, `private_key`, `token`, `bearer`. Konfiguration unter *Settings → Security & Privacy* (org-weit oder pro Projekt), dort auch die Option, IP-Adressen nicht zu speichern; darüber hinaus „Advanced Data Scrubbing" ([Server-Side Data Scrubbing](https://docs.sentry.io/security-legal-pii/scrubbing/server-side-scrubbing/)). Wichtig: dieses Scrubbing läuft **nach** der Übertragung an Sentry. Wer vorher scrubben will, braucht entweder SDK-Hooks (`before_send`) oder **Relay** als Middle-Layer in eigener Infrastruktur — „acts as a middle layer between your application and sentry.io … scrubs PII before forwarding any data" ([Relay](https://docs.sentry.io/product/relay/)); Relay ist laut Plan-Vergleich **Business/Enterprise** ([Pricing](https://sentry.io/pricing/)).

**Paket-Stand:** `sentry/sentry-laravel` **4.27.0** (2026-07-15), **MIT**, `illuminate/support: ^6.0 | … | ^13.0`, `php: ^7.2 | ^8.0`, benötigt `sentry/sentry ^4.28.0` ([Packagist-Metadaten](https://repo.packagist.org/p2/sentry/sentry-laravel.json), [Release 4.27.0](https://github.com/getsentry/sentry-laravel/releases/tag/4.27.0)). **Laravel 13 wird unterstützt.** Kein separater Agent-Prozess: das SDK sendet direkt aus der App.

---

## 5. Direkter Vergleich zur Nightwatch-Abrechnung

| | **Laravel Nightwatch** | **Sentry** |
|---|---|---|
| Abrechnungseinheit | **ein** Event-Topf über den ganzen Stack | **je Kategorie ein eigener Topf** |
| Was als Einheit zählt | Requests, Outgoing Requests, Notifications, Jobs, Queries, Mail, Commands, Cache, Scheduled Tasks, Exceptions, Logs — „jedes davon zählt einzeln" | Errors · Spans · Replays · Logs (GB) · App Metrics (GB) · Attachments (GB) · Profile-Stunden · Cron-Monitore (pro Monitor) · Uptime-Monitore (pro Monitor) · Size-Analysis-Builds |
| Zählt ein fehlerfreier Request? | **Ja** — der Request selbst ist ein Event, plus jede Query, jeder Cache-Hit | **Nein**, solange Tracing aus ist (SDK-Default). Mit Tracing an: ja, als Spans |
| Kostenkurve | skaliert mit **Traffic** | skaliert mit **Fehlerhäufigkeit** (errors-only) bzw. mit Traffic, sobald Tracing an ist |
| Inklusiv im Free-Tier | 300.000 Events/Monat, 14 Tage Lookback | 5.000 Errors + 5M Spans + 50 Replays + 5 GB Logs + 5 GB Metrics + 1 GB Attachments, 30 Tage Retention, 1 User |
| Inklusiv im günstigsten bezahlten Plan | Pro $20/Mo: 7,5M Events, 30 Tage | Team $26–29/Mo: 50k Errors + 5M Spans + 50 Replays + 5 GB Logs, 90 Tage (Errors) |
| Overage | $0.35/100k Events (Pro/Team), Blöcke von 100k, in arrears | pro Kategorie eigene Staffel; Errors ab $0.0003625/Event (Team PAYG) = $36,25/100k |
| Verhalten bei Budget-Ende | Ingestion pausiert, gespeicherte Daten bleiben abrufbar | Daten werden gedroppt, keine Berechnung; „you'll lose monitoring for the remainder of the billing cycle" |
| Spend-Cap | Overage opt-in mit selbstgesetztem Limit | PAYG-Budget **ist** der Cap; zusätzlich Spend-Threshold/Notifications ab Team |
| Seats | unbegrenzt in allen Tiers | unbegrenzt ab Team; Free = 1 User |
| Jahresbilling | öffentlich nicht dokumentiert | ja, ca. 10 % Rabatt ($26 statt $29 / $80 statt $89) |

### Antwort auf die Streitfrage

**Die Annahme „Sentry rechnet genauso über Events" ist falsch — in der Default-Konfiguration.** Der Unterschied ist nicht die Höhe des Preises, sondern **an welcher Größe die Rechnung hängt**:

- Nightwatch hat **eine** Abrechnungseinheit für den gesamten Laravel-Stack. Exceptions sind dort ein Event-Typ unter elf. Kosten korrelieren mit Traffic; ein Monat ohne einen einzigen Fehler kostet dasselbe wie ein Monat mit tausend Fehlern.
- Sentry hat **elf getrennte Kategorien** mit je eigenem Inklusiv-Volumen und eigener Preisstaffel. Der Laravel-SDK-Default liefert **nur** die Errors-Kategorie: `traces_sample_rate = null` (Tracing aus), `enable_logs = false` (Logs aus), Replays/Profiling/Crons/Uptime erfordern eigene Einrichtung. Ein erfolgreicher Request erzeugt dann **null** Abrechnungseinheiten.

**Die Einschränkung, die dazu gehört:** sobald Tracing aktiviert wird (also eine Sample-Rate gesetzt ist), nähert sich Sentry strukturell dem Nightwatch-Modell an — die im Laravel-SDK vorbelegten `tracing.*`-Schalter erzeugen dann Spans für SQL-Queries, Cache-Operationen, Views, Livewire-Komponenten, HTTP-Client-Requests und Queue-Jobs. Die Einheit heißt dann nur anders (Span statt Event) und ist mit 5M/Monat inklusive in **jedem** Plan sehr großzügig bemessen — sie ist aber, anders als bei Nightwatch, ein **separater** Topf, der das Error-Budget nicht anfasst, und sie ist mit einem Config-Wert abschaltbar.

Zweiter belegter Unterschied, der praktisch schwerer wiegt als die Zähleinheit: **das Free-Tier**. 300k Nightwatch-Events reichen bei einer Statamic-Site mit ein paar Queries pro Request für eine niedrige fünfstellige Zahl an Requests pro Monat; 5k Sentry-Errors reichen für 5.000 Exceptions pro Monat, unabhängig vom Traffic. Umgekehrt: bei einem Fehler-Ausbruch (Loop, kaputter Deploy) ist Sentrys Kostenrisiko das größere — dagegen stehen Spike Protection und Rate Limits (letztere erst ab Business).

---

## Nicht belegbar

| Offene Frage | Was fehlt / warum |
|---|---|
| **Monatspreise $29/$89 aus der gerenderten Seite** | Die Pricing-Seite rendert im HTML nur die Jahres-Variante ($26/$80). Die Monatswerte stehen im Datensatz des Pricing-Islands derselben Seite ([`PricingPage.B1RWnP2r.js`](https://sentry.io/_astro/PricingPage.B1RWnP2r.js): `prices:{monthly:29,annual:26}` / `{monthly:89,annual:80}`) — also Primärquelle, aber nicht aus dem sichtbaren Seitentext. **Fehlend:** eine Doku-Seite, die die Monatspreise ausschreibt; `docs.sentry.io/pricing` nennt keine Basispreise. |
| **„Additional discounts" bei Prepaid/Annual** | Checkout-Hinweis „Annual and prepaid billing options with additional discounts are available during checkout" ([Pricing](https://sentry.io/pricing/)) — ohne Prozentzahlen. Liegt hinter dem Checkout. |
| **Enterprise-Preis, -Volumen, -Retention** | Durchgehend „Custom"/„Contact Sales". Auch Size-Analysis-Builds > 100 nur per `sales@sentry.io`. Nicht ohne Sales-Kontakt. |
| **PAYG für den Developer-Plan** | Alle Rate-Tabellen in [docs.sentry.io/pricing](https://docs.sentry.io/pricing/) haben nur „Team"- und „Business"-Spalten; „Spend notifications" und „Set maximum spend threshold" sind laut Plan-Tabelle erst ab Team verfügbar. Ein expliziter Satz „der Developer-Plan kann kein PAYG" fehlt aber. Belegt ist nur: über dem Budget werden Daten gedroppt. |
| **Scope der Zertifizierungen** (SOC 2 Type/Berichtszeitraum/Auditor, ISO-27001-Zertifizierungsstelle und Geltungsbereich, ob die EU-Region mit abgedeckt ist) | [Security-Seite](https://sentry.io/security/) nennt SOC2 Type I+II und ISO 27001 nur als vorhanden; Report und Zertifikat liegen im Produkt unter *Legal & Compliance* bzw. hinter Sales ([SOC2-Doku](https://docs.sentry.io/security-legal-pii/security/soc2/)). **Fehlend:** die Dokumente selbst. |
| **Aktueller Status der EU-U.S.-DPF-Selbstzertifizierung von Functional Software, Inc.** | Der [DPA](https://sentry.io/legal/dpa/) nennt DPF als primären Transfer-Mechanismus und verspricht Benachrichtigung bei Wegfall, führt aber keinen Zertifizierungsnachweis. Verifikation wäre nur über eine nicht-Sentry-Quelle (dataprivacyframework.gov) möglich — nach Quellenregel hier nicht verwendet. |
| **Ob die EU-Region strikte Datenresidenz bedeutet** | Zwei Aussagen begrenzen das: Doku listet Account-/Org-/Token-/Integrations-Daten als „stored in the US regardless" ([Data Storage Location](https://docs.sentry.io/organization/data-storage-location/)), und DPA §6.1 erlaubt Speicherung/Verarbeitung „in the United States and any other country". Zu Support-Zugriffen und Backups aus den USA sagt keine öffentliche Quelle etwas. |
| **Datierte Historie der Pricing-Umbauten** (Transactions/Performance Units → Spans) | [docs.sentry.io/pricing](https://docs.sentry.io/pricing/) belegt nur, *dass* Alt-Pläne existieren („older plans that include transactions or performance units based billing") und dass diese Informationen nur im eingeloggten Subscription-Bereich stehen. Eine Suche im [Changelog](https://sentry.io/changelog/) nach Pricing-Einträgen lieferte auf der ersten Seite keine Billing-Ankündigungen. **Fehlend:** der datierte Hersteller-Post zur Umstellung. |
| **Legacy-Seer-Preise nach Januar 2026** | Doku dokumentiert die Ablösung („As of January 2026, Legacy Seer pricing will no longer be offered as an add-on") und nennt Alt-Sätze; ob Bestandskunden migriert werden, steht nicht da. Für #31 irrelevant. |
| **GlitchTip: offizieller Feature-Vergleich zu Sentry** | Weder [Doku-Index](https://glitchtip.com/documentation/) noch [FAQ](https://glitchtip.com/documentation/frequently-asked-questions) noch die [Startseite](https://glitchtip.com/) enthalten eine Gegenüberstellung „welche Sentry-Features fehlen"; ebenso **kein** Marken-/Affiliation-Disclaimer gegenüber Sentry. Belegbar ist nur der eigene Funktionsumfang (Errors, Performance, Uptime, Logs, Integrations, MCP, CLI) — jede Aussage „GlitchTip kann X nicht" wäre unbelegt. |
| **GlitchTip self-hosted: Event-Limits** | Die [Pricing-Seite](https://glitchtip.com/pricing) nennt die self-hosted-Variante („Run on your own server"), ohne sie als kostenlos/unbegrenzt zu bezeichnen. Aus der MIT-Lizenz folgt keine Limitierung, eine ausdrückliche Zusage fehlt aber. |
| **Event-/Error-Volumen dieses Projekts** | Nicht recherchierbar. Im Repo liegen keine Traffic- oder Fehlerdaten. Nur messbar. |

---

## Konsequenz für #31

Nüchterne Ableitung aus den Fakten, ohne Empfehlung:

1. **Die Prämisse der Ticket-Diskussion („beide rechnen über Events") ist widerlegt und verändert den Vergleich.** Sentry als reiner Error-Tracker koppelt Kosten an Fehlerhäufigkeit, Nightwatch an Traffic. Für eine im Wesentlichen statische Statamic-Site, deren Fehlerrate im Normalbetrieb nahe null ist, sind das zwei qualitativ verschiedene Risikoprofile — nicht zwei Preispunkte derselben Mechanik.
2. **Das kostenlose Sentry-Tier ist für den Zweck von #31 belastbar, mit zwei harten Kanten.** 5.000 Errors/Monat und 30 Tage Retention genügen dem Ticket-Ziel (5xx, ungefangene Exceptions), sind traffic-unabhängig — aber: **1 User** (bei zwei Co-Foundern relevant) und **kein Spend-Threshold/keine Spend-Notifications**. Der Sprung auf Team ($26–29/Monat) kauft unbegrenzte Users, 50k Errors und 90 Tage Retention. Rate Limits pro Projekt-Key gibt es erst ab Business.
3. **Der DSGVO-Papierweg ist bei Sentry kürzer als bei Nightwatch.** DPA im Volltext öffentlich (v5.1.0), self-service im Produkt akzeptierbar, SCC-Module 2/3 und UK/Swiss-Addenda eingebaut, Sub-Processor-Liste öffentlich mit 30-Tage-Vorlauf und RSS. Bei Nightwatch ist der AVV nur auf Anfrage und die Sub-Processor-Liste gated — dort ist die Vorbedingung ein Vorgang mit Latenz, hier ein Klick. **Gegengewichte:** Vertragspartner ist ein US-Unternehmen (kalifornisches Recht), DPA §6.1 erlaubt vertraglich US-Verarbeitung trotz EU-Region, Account-/Org-Metadaten liegen unabhängig von der Region in den USA, und die Region ist **nach Anlegen der Organisation nicht mehr wechselbar** — die Wahl „EU/Frankfurt" muss also beim ersten Setup richtig sein, sonst kostet sie eine neue Organisation.
4. **PII-Baseline ist konservativ, aber nicht so konservativ wie bei Nightwatch.** Header, Cookies, User-Daten und IP sind default aus (`send_default_pii=false`), SQL-Bindings default aus. **Immer** übertragen werden Request-URL, Query-String, Source-Code-Snippets und **lokale Variablen im Stacktrace** — letztere sind der Punkt, an dem in einem Laravel-Request unbeabsichtigt Formularwerte landen können; abschaltbar nur per `php.ini` (`zend.exception_ignore_args=1`). Request-Bodies (JSON/Form) werden bis ~10 KB gesendet, solange `max_request_body_size` nicht auf `never` steht. Das Server-Scrubbing greift erst **nach** der Übertragung; Pre-Transfer-Scrubbing über Relay ist ein Business-Feature.
5. **Self-hosted ist bei Sentry eine reale, aber teure Option — und nicht Open Source.** FSL-1.1-Apache-2.0 deckt „internal use" ausdrücklich; Weiterverkauf/Konkurrenzangebot nicht; jede Version wird 2 Jahre später Apache-2.0. Betriebspreis: 4 Cores / 16 GB RAM + 16 GB Swap, ein Compose-Stack mit ClickHouse, Snuba, Relay, Symbolicator und Taskbroker, „no guarantees or dedicated support". Gegenüber der Nullkosten-Variante SaaS-Free-Tier ist das für eine Agentur-Website ein Missverhältnis. **GlitchTip** (MIT, 512 MB RAM empfohlen, Postgres + ein Service) ist die einzige der drei Optionen, bei der self-hosted ressourcenseitig proportional zum Projekt ist — dafür mit kleinem Maintainer (Burke Software), DPA nur auf Anfrage, ohne SOC 2/ISO und mit einem *gemeinsamen* Event-Zähler im Hosted-Tarif (also derselben Mechanik wie Nightwatch).
6. **Kein neuer Betriebsposten.** Sentry ist ein SDK im App-Prozess (`sentry/sentry-laravel` 4.27.0, MIT, Laravel 13 unterstützt) — kein dauerhaft laufender Agent, kein Process-Monitor, kein eigener Health-Check. Das ist der direkte Kontrast zum Nightwatch-Agent und berührt die `needs-infra`-Restpunkte (§B/§I/§J) des Tickets nicht.
7. **Wechselkosten sind asymmetrisch.** Sentrys Ingest-API ist die De-facto-Schnittstelle, die GlitchTip nachbaut („compatible with Sentry client SDKs") — ein Wechsel Sentry-SaaS → GlitchTip-self-hosted ist eine DSN-Änderung, kein Umbau. Ein Wechsel in Richtung Nightwatch bedeutet dagegen ein anderes Package, einen Agent-Prozess und ein anderes Datenmodell. Die Klassenentscheidung aus #31 ist damit in Richtung Error-Tracking leichter revidierbar als in Richtung Full-Stack-Observability.

**Zur Schwesterdatei:** Beim Lesen von [`laravel-nightwatch-preise-hosting.md`](./laravel-nightwatch-preise-hosting.md) ist kein sachlicher Fehler aufgefallen. Der dort selbst markierte Widerspruch „Lookback (14/30/60/90 Tage) vs. FAQ ‚we store data for 90 days'" bleibt bestehen; der Hinweis, `nightwatch.laravel.com` liefere ein 301 auf `laravel.com/nightwatch`, während `nightwatch.laravel.com/docs/*` weiter genutzt wird, ist korrekt (Root 301, `/docs/*` 200 — am 2026-08-17 nachgeprüft).

---

## Quellen

Alle URLs abgerufen am **2026-08-17**.

**Preis- und Produktseiten (sentry.io)**
- Pricing inkl. Plan-Vergleichstabelle und Kostenschätzer: https://sentry.io/pricing/
- Datensatz des Pricing-Islands (Monatspreise, Checkmark-Belegung): https://sentry.io/_astro/PricingPage.B1RWnP2r.js
- Security & Compliance: https://sentry.io/security/

**Dokumentation (`docs.sentry.io`)**
- Pricing & Billing (Kategorien, alle Rate-Tabellen, Billing-Zyklen, Trials, Steuern): https://docs.sentry.io/pricing/
- Billing Quota Management (was gegen die Quota zählt): https://docs.sentry.io/pricing/quotas/
- Manage Your Error Quota: https://docs.sentry.io/pricing/quotas/manage-event-stream-guide/
- Manage Your Span Quota: https://docs.sentry.io/pricing/quotas/manage-transaction-quota/
- Data Storage Location (US oder EU): https://docs.sentry.io/organization/data-storage-location/
- Data Retention Periods: https://docs.sentry.io/security-legal-pii/security/data-retention-periods/
- Server-Side Data Scrubbing: https://docs.sentry.io/security-legal-pii/scrubbing/server-side-scrubbing/
- SOC 2 (Bezugsweg): https://docs.sentry.io/security-legal-pii/security/soc2/
- Relay: https://docs.sentry.io/product/relay/
- Laravel-SDK Configuration Options: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
- Laravel-SDK Data Collected: https://docs.sentry.io/platforms/php/guides/laravel/data-management/data-collected/
- Set Up Tracing in PHP/Laravel: https://docs.sentry.io/platforms/php/guides/laravel/tracing/
- Application Metrics (Laravel): https://docs.sentry.io/platforms/php/guides/laravel/metrics/
- Changelog (nach Pricing-Einträgen durchsucht, ohne Treffer auf S. 1): https://sentry.io/changelog/

**Rechtstexte (Functional Software, Inc. d/b/a Sentry)**
- Legal-Übersicht: https://sentry.io/legal/
- Terms of Service v3.0.0 (2024-02-12): https://sentry.io/terms/
- Data Processing Addendum v5.1.0 (2024-05-29), Volltext öffentlich: https://sentry.io/legal/dpa/
- Sub-Processor-Liste (Stand 2026-06-01, mit RSS): https://sentry.io/legal/subprocessors/
- Help-Center: DPA-Abschluss (self-service, Owner/Billing): https://www.sentry.help/en/articles/13965008-how-do-i-sign-your-data-processing-addendum

**Self-hosted / Quellcode**
- Self-hosted-Doku (Ressourcen, Support-Policy, Feature-Abweichungen): https://develop.sentry.dev/self-hosted/
- `getsentry/self-hosted` (Compose-Stack, FSL): https://github.com/getsentry/self-hosted · LICENSE: https://github.com/getsentry/self-hosted/blob/master/LICENSE.md
- `getsentry/sentry` LICENSE (FSL-1.1-Apache-2.0): https://github.com/getsentry/sentry/blob/master/LICENSE.md
- GitHub-API-Metadaten (Lizenz = NOASSERTION): https://api.github.com/repos/getsentry/sentry · https://api.github.com/repos/getsentry/self-hosted
- `getsentry/sentry-laravel` (MIT), Default-Config: https://github.com/getsentry/sentry-laravel · https://github.com/getsentry/sentry-laravel/blob/master/config/sentry.php · LICENSE: https://github.com/getsentry/sentry-laravel/blob/master/LICENSE
- Packagist-Metadaten (`sentry/sentry-laravel` 4.27.0, 2026-07-15, MIT, Laravel `^13`): https://repo.packagist.org/p2/sentry/sentry-laravel.json

**GlitchTip (offizielle Quellen)**
- Startseite (Betreiber, Sentry-SDK-Kompatibilität, Funktionsumfang): https://glitchtip.com/
- Pricing (Hosted-Tarife, gemeinsamer Event-Zähler): https://glitchtip.com/pricing
- Dokumentation (Index): https://glitchtip.com/documentation/
- Installation/Self-hosting (Ressourcen, Upgrade-Politik): https://glitchtip.com/documentation/install
- FAQ (Event-Reduktion, GDPR/DPA-Aussage): https://glitchtip.com/documentation/frequently-asked-questions
- `glitchtip-backend` LICENSE (MIT): https://gitlab.com/glitchtip/glitchtip-backend/-/blob/master/LICENSE
- GitLab-API-Metadaten: https://gitlab.com/api/v4/projects/glitchtip%2Fglitchtip-backend · https://gitlab.com/api/v4/projects/glitchtip%2Fglitchtip-frontend

**Nicht verwendet:** Vergleichsportale, „Sentry alternatives"-Landingpages, Reddit/HN, Blogposts ohne Preis-/Lizenzbezug, KI-Zusammenfassungen — gemäß Quellenregel der Aufgabe.
