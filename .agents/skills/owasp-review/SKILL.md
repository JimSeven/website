---
name: owasp-review
description: "Manual full-repo OWASP state-audit before a release, tracked in a report SSoT."
disable-model-invocation: true
---

# /owasp-review

A **state-audit**: judge the whole repo's current security posture against the project's OWASP checklist before a release. The living report `docs/security/owasp-review.md` is the **SSoT** — every run rewrites each item's Status + reason + date there.

## When to use

Run it by hand before a release, to refresh the full-repo posture snapshot. Cadence is per-release only.

**Abgrenzung — this is not `/security-review`.** That command scopes to one changeset; this audit covers the whole repo and wraps `/security-review` as a single step (step 4) inside that full pass. For the release-gate snapshot reach for `/owasp-review`; for a fast read on the current diff reach for `/security-review`.

## Scope

The audit covers exactly the checklist rows whose **Typ** is `Review` or `CI+Review` (the judgement-dependent ones). Pure `CI` rows are already gated by the CI pipeline (`.github/workflows/security-pr.yml`) — read their scanner output (step 1) but do not re-judge them by hand. The **Antlers-XSS grep** (step 3) is a fixed part of every run regardless of which rows it maps to.

## Inputs

1. **Checklist** — `docs/security/owasp-checklist.md`. The `Typ` column is the filter; the section letters (§B–§L) are the report's anchors.
2. **Scanner output** — from the last successful CI run, with an ad-hoc fallback so a missing/failed run never blocks the audit:

   | Source | From CI | Ad-hoc fallback |
   |--------|---------|-----------------|
   | composer advisories | artifact `composer-audit` (`composer-audit.json`) of `security-pr.yml` | `composer audit --locked --format=json` |
   | doctor security | artifact `doctor` (`doctor.json`) of `security-pr.yml` | `php artisan doctor --only=security --format=json` |
   | Larastan | `larastan` job status of `security-pr.yml` | `vendor/bin/phpstan analyse --error-format=json` |
   | Psalm taint (SARIF) | Code Scanning alerts, `tool_name=Psalm` (uploaded by `security-weekly.yml`) | `vendor-bin/psalm/vendor/bin/psalm --taint-analysis --no-cache --report=results.sarif` |

   Fetch CI artifacts with:
   ```
   gh run list --workflow=security-pr.yml --status=success --limit=1 --json databaseId --jq '.[0].databaseId'
   gh run download <id> --name composer-audit --name doctor --dir <tmp>
   gh api "repos/$(gh repo view --json nameWithOwner --jq .nameWithOwner)/code-scanning/alerts?tool_name=Psalm&state=open"
   ```

## Steps

Hold the findings in view through step 4 and only write the report at step 5.

1. **Assemble inputs.** Read the checklist. Pull the scanner output per the table above; on any miss, run the ad-hoc fallback and note in the report which source was used. Completion: checklist read and all four scanner sources resolved (CI artifact or fallback), each labelled with its provenance.

2. **Judgement pass.** For **every** `Review` and `CI+Review` row, inspect the current code/config it points at and assign a Status: `OK` / `Gap` / `N/A` / `Needs-infra` (infra-level, not verifiable from the repo), each with a one-line reason grounded in a concrete file, config key, or scanner finding. Completion: every `Review`/`CI+Review` row has a Status + reason — no row skipped.

3. **Antlers-XSS grep (fixed).** Grep `.antlers.html` templates for unescaped output of user-controlled content — the Antlers equivalents of Blade `{!! !!}`:
   ```
   rg -n --glob '*.antlers.html' -e '\|\s*raw\b' -e '\{\{\s*noparse' .
   ```
   For every hit, judge whether the output value can carry user/CP-editable content; a trusted-content hit is `OK`, an untrusted one is a `Gap`. Also grep Blade for the same class of defect:
   ```
   rg -n --glob '*.blade.php' -e '\{!!' .
   ```
   Completion: every match triaged trusted-vs-untrusted; feed Gaps into the report under §F.

4. **Diff-scoped `/security-review`.** Determine the base: latest release tag (`git tag --sort=-creatordate | head -1`, assuming tags mark releases); if the repo has no tags yet, fall back to `origin/main`. Run `/security-review` over the diff `base...HEAD` and merge its findings into the pass — map each to the checklist section it touches, or record it as an un-mapped finding. Completion: `/security-review` run against the resolved base and each of its findings either mapped to a checklist row or listed as un-mapped.

5. **Write the report (SSoT).** Update `docs/security/owasp-review.md` (create it from the template below on first run). One row per audited checklist item: Status, reason, date (today). Carry forward prior rows, overwriting Status/reason/date where this run changed them. Completion: report reflects this run for every audited row, dated today, and lists step-4 findings.

6. **File issues for new gaps.** Only for `Gap` rows that are **new / not already tracked**. First dedup against open issues to avoid duplicates:
   ```
   gh issue list --state open --label security-audit --json number,title
   gh issue list --state open --label wayfinder:task --json number,title
   ```
   Skip a gap if an open `security-audit` or `wayfinder:task` issue already covers it. For each genuinely new gap, create an issue with the `security-audit` label plus the readiness label — `ready-for-agent` for a mechanical fix, `ready-for-human` for judgement/infra work (create the `ready-for-human` label if absent). Title with the checklist section, e.g. `[§C] 2FA nicht erzwungen`. Completion: every new `Gap` either has a fresh issue or a named existing issue it maps to — no new gap left both untracked and un-filed.

## Report template

Used to initialise `docs/security/owasp-review.md` on the first run:

```markdown
# OWASP-Review — Zustands-Audit (SSoT)

**Quelle der Checkliste:** `docs/security/owasp-checklist.md`
**Prozess:** `/owasp-review` — manuell vor Release. Ein Lauf schreibt jeden Punkt (Status + Begründung + Datum) fort.
**Letzter Lauf:** <YYYY-MM-DD>
**Diff-Basis (`/security-review`):** <tag oder origin/main>

## Status je Checklisten-Punkt (Typ: Review / CI+Review)

| § | Punkt | Status | Begründung | Datum |
|---|-------|--------|------------|-------|
| §B | Trusted Proxies | Needs-infra | … | 2026-07-31 |

Status-Werte: `OK` · `Gap` · `N/A` · `Needs-infra`

## Antlers-/Blade-XSS-Grep

<Treffer + trusted/untrusted-Urteil, oder „keine Treffer">

## /security-review — Findings (Diff seit <Basis>)

<gemergte Findings, je auf Checklisten-§ gemappt oder als un-mapped gelistet>

## Angelegte / aktualisierte Issues

<Liste #nummer → Punkt, oder „keine neuen Lücken">
```
