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

### 2026-04-29 14:26:40 AEST

- Issue or trigger: issue `#151` to make Domain Monitor the Fleet lookup surface for GA4 rollout codes sourced from MM-Google
- What changed: extended the existing MM-Google GA4 sync so it records `source_system`, `last_synced_at`, `switch_ready`, and `provisioning_state` on GA4 analytics source rows; also expanded `/api/web-properties-summary` so both `analytics_sources` and the normalized `analytics.ga4` block expose GA4 measurement IDs, property and stream IDs, MM-Google provenance, and live detection state where monitoring evidence exists
- What was fixed: Fleet website domains can now read the expected GA4 code and rollout state directly from Domain Monitor even when Matomo remains the historical primary source, and properties still waiting on a measurement ID now surface as explicit provisioning state instead of disappearing behind Matomo-only summary fields
- What remains: the in-repo contract is ready, but the live `supercheapcartransport.com.au` example still depends on MM-Google supplying its measurement ID through the upstream sync input before Domain Monitor can show it as switch-ready rather than provisioning

### 2026-04-29 04:57:55 AEST

- Issue or trigger: verify and close issue `#150` during the daily Bossman overseer run
- What changed: confirmed the Fleet Astro technical SEO weekly lane is already implemented on `main` in commit `5a97123`, rechecked the scheduled lane and focused coverage, and reran `php artisan test tests/Feature/RunMonitoringLaneCommandTest.php --filter=fleet_astro_technical_seo`
- What was fixed: removed issue drift between GitHub and the repo by verifying the implementation is live in this checkout and ready for the GitHub issue to be closed
- What remains: no in-repo follow-up is required for issue `#150`; any future expansion should be tracked as a new narrow slice rather than reopening the baseline lane

### 2026-04-28 19:18 AEST

- Trigger: Bossman asked whether Fleet's Astro technical SEO standard should be
  verified here rather than rewritten in `MM-Google`
- What changed: added
  [`/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md`](/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md)
  to record the ownership split between Fleet, `MM-Google`, and
  `domain-monitor`, and to define the first narrow weekly verification slice
- What was fixed: made it explicit that `domain-monitor` should verify live
  technical SEO drift against the Fleet Astro baseline instead of inventing a
  parallel standards framework
- What remains open: open and execute a repo issue for the first weekly
  verification slice, starting with low-noise checks like PageSpeed snapshots,
  robots, sitemap, canonical presence, key-route indexability, and redirect
  sanity

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

### 2026-04-28 13:08:41 AEST

- Trigger: Fleet issue `#20` follow-up on the remaining `backloading-services.com.au` handoff mismatch
- What changed: verified the live homepage still exposes distinct household quote, booking, contact, and vehicle quote links on `mymovehub.backloading-services.com.au`; also reproduced the current scanner result locally and added a focused regression test so this header pattern keeps classifying quote and booking into separate slots
- What was fixed: ruled out the current Domain Monitor scanner as the cause of the `backloading-services.com.au` mismatch, because the live page and scanner both resolve `household_quote` to `/quote/household` and `household_booking` to `/booking/create`
- What remains open: production Domain Monitor should be rerun against the live property or its stored finding refreshed, because any remaining `backloading-services.com.au` handoff alert now looks like stale production state or an outdated issue note rather than a live website bug

### 2026-04-28 13:24:42 AEST

- Issue or trigger: Bossman follow-up to refresh production Domain Monitor after multiple live fixes landed
- What changed: used direct SSH access to the production host at `/home/forge/monitor.again.com.au/current` and reran `php artisan domains:refresh-should-fix`, `php artisan monitoring:run-lane critical_live --timeout=15`, and `php artisan monitoring:run-lane marketing_integrity --timeout=15`; `critical_live` finished with `0` findings opened, `3` updated, and `1` recovered, while `marketing_integrity` finished with `0` opened, `2` updated, and `5` recovered
- What was fixed: refreshed the live queue from production rather than the empty local database, confirmed the urgent dashboard bucket is now `must_fix_count = 0`, and re-exposed the current queue through the usual production dashboard helper with `should_fix_count = 44`
- What remains: the live queue is now dominated by should-fix follow-up rather than urgent breakage; the main remaining review cluster is security headers plus SEO on a smaller set of domains, along with email-security plus security-headers review on several quote or portal subdomains, while redirect-policy review still remains worth watching on hosts like `redirection.com.au`, `vehicle.net.au`, and `cartransport.movingagain.com.au`

### 2026-04-28 19:24:07 AEST

- Issue or trigger: issue `#150` to add the first weekly Fleet Astro technical SEO verification slice in `domain-monitor`
- What changed: added a new `fleet_astro_technical_seo` monitoring lane with weekly scheduling, scoped it to Fleet-focus properties whose execution surface resolves to `astro_repo_controlled`, and wired the lane to reuse the existing live `indexability` and `redirect_policy` audits under dedicated Fleet technical SEO finding keys
- What was fixed: Domain Monitor can now surface Fleet Astro homepage technical SEO drift through the usual monitoring-finding issue flow without broadening into a crawler or build-lint path, and the focused lane test coverage now guards against WordPress or non-Fleet properties leaking into this weekly slice
- What remains: this first slice does not implement PageSpeed snapshots or repeated-failure streak logic yet, so any later expansion should stay tied to the Fleet standard and add noise-control deliberately rather than broadening the lane ad hoc
