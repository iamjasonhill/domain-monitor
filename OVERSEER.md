# OVERSEER

## Current Controller

`domain-monitor` is the canonical active controller for the domain-management department.

Supporting evidence confirmed during issue `#131`:

- this repo is a tracked git repository with origin `iamjasonhill/domain-monitor`
- repo documentation already describes `domain-monitor` as the source of truth for operational state
- active planning docs in this repo already treat the older Next.js project as a prior reference implementation

## Related Prototype Status

Related local folder reviewed:
`domain-manage-project` in the local non-Laravel projects area

Classification:
`domain-manage-project` is operationally obsolete as a controller and should not be treated as a second source of truth.

What it still is:

- a local, non-git Next.js and Render prototype
- a reference source for older implementation ideas
- something that may still contain a few notes worth preserving before archive or removal

Keep-worthy items named during review:

- Render deployment and CLI notes in `README.md`, `RENDER_CLI.md`, and `.render-setup.md`
- earlier Synergy and uptime-monitoring implementation ideas captured in the prototype README
- the fact that this prototype used a separate Next.js and Drizzle stack, which is now superseded here

What remains open:

- decide whether any of the Render-specific notes should be copied into this repo before cleanup
- after that review, archive or remove the local prototype folder

## Rename Direction

Issue `#133` confirmed that this system is a keeper and a pivotal operational dependency.

Current recommendation:

- keep `domain-monitor` as the product-facing and live contract name for now
- do not perform an immediate standards rename across the live estate
- if standards alignment is still desired, use an alias-first staged migration path before any canonical rename

Why this is the current call:

- the live hostname and API identity already use `https://monitor.again.com.au`
- this repo exposes stable `source_system` values such as `domain-monitor`, `domain-monitor-issues`, `domain-monitor-runtime-analytics`, and `domain-monitor-priority-queue`
- adjacent systems already consume those names directly, including `fleet-control` and `MM BRAIN`
- package, job, user-agent, artifact-path, and documentation references inside this repo still assume `domain-monitor` is the canonical operational name

## Change Log

### 2026-04-22 07:35:43 AEST

- Trigger: issue `#131`
- What changed: created `OVERSEER.md` and documented `domain-monitor` as the canonical active controller for this department
- What was fixed: removed ambiguity between this repo and the local `domain-manage-project` prototype by classifying the prototype as operationally obsolete
- What remains open: confirm whether any Render setup notes are worth copying first, then archive or remove the local `domain-manage-project` folder

### 2026-04-22 10:52:11 AEST

- Trigger: issue `#133`
- Product-name decision: keep `domain-monitor` as the current product-facing and live contract identity; do not rename it immediately to an `MM-` form
- If a future rename is approved, the blast radius includes at least:
  - local folder/workspace name
  - GitHub repo name and clone remote
  - deployment identifiers and any app-name references
  - live hostname and API clients currently using `https://monitor.again.com.au`
  - API contract values such as `service=domain-monitor` and `source_system=domain-monitor*`
  - user-agent strings, artifact paths, package metadata, and automation labels in this repo
  - downstream docs, scripts, tests, and contract validators in adjacent repos including `fleet-control`, `MM-Google`, and `MM BRAIN`
- Safe staged migration plan:
  1. decide the target standard name, but keep `domain-monitor` as the canonical live identifier during planning
  2. add an internal alias map and compatibility policy so both old and new names are explicitly recognized in docs and handoff planning
  3. update adjacent repos to accept dual identifiers without removing `domain-monitor` contract support
  4. add dual-read or alias coverage for repo references, service labels, and any machine-read contract validators
  5. introduce any hostname or API alias only after downstream consumers are verified against both names
  6. switch the preferred human-facing standard name in docs and workflow prompts
  7. only then evaluate whether the GitHub repo, local folder, and live hostname should actually be renamed
- Bossman verification checklist before considering the rename complete:
  - all current API consumers still work against the existing live contract
  - `fleet-control` sync and contract validation pass against the chosen alias strategy
  - `MM BRAIN` snapshot ingestion, validators, and operator views still read the same payloads successfully
  - `MM-Google` docs and handoff references are updated where they rely on the old canonical naming
  - GitHub clone, local workspace instructions, and deployment runbooks all point to the intended final identity
  - no hard-coded `domain-monitor` value that must remain canonical was changed accidentally
  - live smoke checks confirm the hostname, health routes, and integration metadata behave as expected
- Best immediate action: `alias-first`, which effectively means defer any canonical rename until dual-name compatibility is verified end to end

### 2026-04-22 17:19:34 AEST

- Trigger: issue `#135`
- What changed: classified the dirty `main` worktree as one coherent workspace-root relocation, updating absolute local paths from `/Users/jasonhill/Projects/websites` and `/Users/jasonhill/Projects/2026 Projects/MM-Google` to the current `Business` workspace layout across config, docs, migration seed data, and related tests
- What was fixed: removed old-path residue inside this repo, confirmed the new local roots exist, and verified the touched PHP and focused test slices pass
- What remains open: no in-repo residue remains; the only remaining decision is the owner-facing merge/close step for this coherent change set

### 2026-04-23 13:26:29 AEST

- Trigger: MM-Google Search Console replacement export handoff
- What changed: wired `domain-monitor` to ingest the MM-Google `search-console-coverage-baseline-v1` export, normalized coverage and baseline records into the existing domain-level tables, and taught `WebProperty` to report MM-Google-backed Search Console coverage as a real status
- What was fixed: removed the last hard Matomo-only dependency from the Search Console status summary path, added a focused import test, and updated the SEO baseline / API integration docs to treat MM-Google as the preferred producer
- What remains open: legacy Matomo imports still exist for recovery/backfill, but the active control-plane path should now come from MM-Google export files

### 2026-04-28 09:01 AEST

- Trigger: Bossman daily overseer review after dashboard / parked-domain alert cleanup
- What changed: merged and deployed commit `8a8f54e`, which retuned marketing indexability and parked-domain GA4 findings away from the urgent bucket, added a dashboard `Detected Must Fix` panel backed by the same detected-issues feed as `/api/issues`, and backfilled the one real live GA4 config gap for `backloadingremovals.com.au`
- What was fixed: reduced detected `must_fix` noise from stale indexability and parked/no-fetch GA domains, made real monitoring-lane `must_fix` findings visible on `/dashboard`, and left the current urgent queue focused on conversion-surface GA mismatches, quote-handoff gaps, redirect-policy issues, one email baseline issue, and one Search Console robots finding
- What remains open: conversion surfaces still commonly report `G-NG8LKXCLVE` instead of the owning site GA4 ID, several live sites still need quote-handoff link correction, and three redirect-policy findings remain real enough for follow-up triage

### 2026-04-28 09:37 AEST

- Trigger: route the largest remaining detected `must_fix` bucket to its owning application repo
- What changed: opened `iamjasonhill/moveroocombined#1748` for the 13 conversion-surface GA4 mismatches and the related 12 quote-handoff mismatches surfaced by Domain Monitor
- What was fixed: moved the common quote/portal-host attribution problem out of chat-only triage and into the Moveroo Combined repo with affected hostnames, expected GA4 IDs, detected default `G-NG8LKXCLVE`, handoff route expectations, and a narrow acceptance checklist
- What remains open: Moveroo Combined needs to confirm the app-side host/site identity path, emit per-site GA4 IDs where safe, classify marketing-site-only handoff fixes back to Fleet/Bossman, and then Domain Monitor should rerun `marketing_integrity` to verify the urgent bucket shrinks

### 2026-04-28 11:18 AEST

- Trigger: verify Moveroo Combined issue `#1748` after PR `#1750` merged
- What changed: reran `marketing_integrity` for the affected quote/conversion domains and refreshed the detected issue snapshot
- What was fixed: `marketing.conversion_surface_ga4` dropped from 13 urgent findings to 0, confirming the Moveroo Combined app-side quote-host GA4 fallback issue is resolved
- What remains open: 12 `marketing.quote_handoff_integrity` findings remain on marketing sites; those are now routed to Fleet as `iamjasonhill/MM-fleet-program#20` rather than left on the Moveroo Combined app issue

### 2026-04-28 12:04 AEST

- Trigger: Fleet issue `#20` surfaced GA4 and handoff noise on `removalist.net` and `vehicle.net.au`
- What changed: classified both apex domains as operational app-shell properties and added explicit monitoring policy so homepage GA4 and quote-handoff checks are not required on those apex shells
- What was fixed: production `marketing_integrity` reruns now recover/suppress `marketing.ga4_install` and `marketing.quote_handoff_integrity` for the two app-shell apex domains while preserving conversion-surface GA4 checks for the real quote subdomains
- What remains open: `vehicle.net.au` still has a separate `critical.redirect_policy` must-fix finding, and Fleet `#20` should continue with the remaining 10 marketing-site quote handoff fixes rather than chasing the two app shells

### 2026-04-28 12:27:09 AEST

- Issue or trigger: issue `#149` to expose a canonical hostname link policy that Moveroo hostname admin can consume
- What changed: added a derived `hostname_link_policy` block to the existing web property summary export, backed by a new builder that classifies marketing domains, quote or portal hosts, and operational app-shell apexes from the current Domain Monitor property targets and conversion surfaces; also documented the new field in the API integration guide and added focused API coverage
- What was fixed: Domain Monitor can now emit one consistent hostname-level policy for expected household quote, vehicle quote, booking, contact, and customer portal links, including `required` or `optional` or `suppressed` or `unknown` slot statuses and explicit app-shell suppression for apex domains like `removalist.net`
- What remains: the issue is implemented in-repo but still needs the usual commit or push or consumer adoption step before the linked Moveroo admin screen can rely on it end to end
