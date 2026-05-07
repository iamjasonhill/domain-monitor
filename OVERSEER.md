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

### 2026-05-07 13:43:50 AEST

- Issue or trigger: reopened issue `#161` to stop failed internal Search Console API enrichment from overriding fresh MM-Google coverage
- What changed: Search Console API enrichment failure persistence now preserves existing fresh `search_console_ready` coverage rows and logs the failed optional enrichment attempt instead of replacing canonical coverage state
- What was fixed: forced Domain Monitor enrichment failures such as `Unable to refresh the Google Search Console access token (400)` still surface as blockers when coverage is stale or missing, but no longer downgrade a property that already has fresh MM-Google Search Console coverage
- What remains: deploy to production, rerun/verify Moving Again coverage refresh, then close issue `#161` if production continues to report fresh MM-Google coverage instead of the legacy token blocker

### 2026-05-07 13:40:11 AEST

- Issue or trigger: production verification for issue `#173` after deploying commit `793226b`
- What changed: deployed production to `793226b`, cleared optimized caches, and checked the four known email-only properties against the production Control Plane issue export
- What was fixed: `jasonhill.com.au`, `jhmh.com.au`, `nfgseo.com.au`, and `removals.com.au` now report `shouldSuppressLiveWebsiteQualityFindings=true`; their `16` raw historical live-site QA findings remain preserved, but exported website-QA Attention leakage for the affected finding classes is `0`
- What remains: no in-repo work remains for issue `#173`; Control Plane should refresh/import Domain Monitor so the now-suppressed rows disappear from the visible Attention queue

### 2026-05-07 13:34:23 AEST

- Issue or trigger: issue `#173` to suppress website QA findings for email-only and not-applicable properties
- What changed: live-website QA eligibility is now centralized on `WebProperty::shouldSuppressLiveWebsiteQualityFindings()` and reused by monitoring lane selection, Control Plane issue exports, web-property monitoring summaries, and GA4 detection summaries
- What was fixed: domain assets, DNS-parked properties, email-only primary domains, and `not_applicable`/coverage-excluded properties no longer emit or export live-site QA findings for missing GA4, indexability, structured data, or agent-readiness checks, while eligible live website properties still use the normal monitoring paths
- What remains: deploy to production and refresh/import Control Plane attention data to confirm the four current email-only properties drop from website-QA Attention without weakening live website QA

### 2026-05-07 13:11:23 AEST

- Issue or trigger: Control Plane Attention review for `vehicle.net.au`, an operational app-shell domain whose HTTP apex upgraded correctly but whose alternate `www` host could not be verified
- What changed: redirect-policy scanning now treats `preferred_host_unverified` as optional for `property_type = app` when it is the only redirect-policy problem; the evidence records `tolerated_problems` and `app_shell_alternate_host_optional` so the exception is visible rather than silent
- What was fixed: app-shell domains no longer surface a critical live redirect incident solely because an unconfigured alternate host cannot be probed, while real HTTP upgrade failures and alternate-host mismatches remain reportable
- What remains: deploy production, rerun the `critical_live` lane or next scheduled monitoring pass for `vehicle.net.au`, and refresh Control Plane so the Attention critical drops if no other live issue remains

### 2026-05-07 12:55:25 AEST

- Issue or trigger: production verification for issues `#171` and `#172` after deploying commits `09dd29e` and `df830bb`
- What changed: deployed production to `df830bb`, cleared optimized caches, checked representative Search Console summaries, checked `web-properties-summary` for stale Matomo freshness matches, and checked the production detected-issues export for parked/domain-asset live-site QA leakage
- What was fixed: representative archived-Matomo properties now report current MM-Google Search Console setup gaps instead of stale Matomo freshness; `web-properties-summary?fleet_focus=1` reports `0` stale Matomo freshness matches; raw old parked/domain-asset QA findings remain preserved in the database, but Control Plane export reports `0` live-site QA attention issues for parked/domain-asset properties
- What remains: Control Plane should rerun/import the refreshed Domain Monitor feed so the Attention page drops the now-suppressed rows; no further Domain Monitor code change is required for issues `#171` or `#172`

### 2026-05-07 12:53:29 AEST

- Issue or trigger: issue `#172` to suppress live-site QA findings for parked and domain-asset properties
- What changed: live monitoring lanes now skip `domain_asset` properties, parked detection for monitoring uses DNS parking as well as parked platform/override state, and Control Plane-facing issue, monitoring-summary, and GA4 live-detection exports suppress existing live-site QA findings for domain assets and parked domains
- What was fixed: missing GA4, JSON-LD, robots/llms, sitemap/indexability, and related live-site QA findings no longer surface for parked/domain-asset properties while normal live website properties still receive QA findings and domain ownership/expiry paths remain separate
- What remains: deploy and rerun production Control Plane import/attention refresh to confirm parked/domain-asset live-site QA rows drop from Attention without hiding real live-site defects

### 2026-05-07 12:46:03 AEST

- Issue or trigger: issue `#171` to stop archived Matomo coverage from driving active Search Console freshness
- What changed: Search Console URI and coverage summaries now select only active coverage rows; archived/inactive Matomo coverage is skipped before freshness is calculated, while GA4/MM-Google rows remain eligible
- What was fixed: an archived Matomo source with stale Search Console evidence now falls through to the current GA4/MM-Google Search Console setup gap instead of reporting `stale_import` from old Matomo evidence
- What remains: deploy and rerun production Control Plane import/attention refresh to confirm stale Matomo-driven attention rows disappear; then continue with parked/domain-asset live-site QA suppression in issue `#172`

### 2026-05-07 12:13:05 AEST

- Issue or trigger: issue `#170` to run production cleanup verification after Matomo/manual CSV retirement
- What changed: verified production is deployed at commit `3b0b170`, reran `coverage:sync-tags`, checked the normal scheduler, queried live tag/source/baseline counts, checked the archive dry-run, and verified the authenticated production priority queue and issues API
- What was fixed: production detached the remaining `automation.manual_csv_pending` tag and now reports `manual_csv_pending=0`; normal scheduling contains no Matomo refresh jobs; Matomo sources remain preserved as `26` archived rows with `0` active and `0` primary; the Matomo archive dry-run reports `0` remaining archive/promote actions; `composer quality` passes locally with `437` tests
- What remains: no in-repo cleanup remains for this issue; retained Matomo/manual CSV baselines remain queryable as historical evidence, and the only `Matomo` string found in `/api/issues` is raw captured page HTML from `vehicle.net.au`, not a Domain Monitor live action item

### 2026-05-07 12:09:22 AEST

- Issue or trigger: issue `#169` to neutralize Matomo-era naming in active contracts
- What changed: active Search Console summaries now expose neutral source aliases (`source_provider`, `source_site_id`, `source_display_name`, `source_url`, and `search_console_property_uri`) while preserving `legacy_matomo_*` compatibility fields; Search Console URI resolution now prefers current domain/GA4 evidence before legacy Matomo evidence, and API/baseline docs teach the neutral fields first
- What was fixed: new consumers can integrate against GA4/MM-Google/Search Console contracts without treating Matomo-specific names as active requirements, and older consumers still have compatibility aliases for historical rows
- What remains: run the final production cleanup verification after the retirement stack is deployed

### 2026-05-07 12:04:03 AEST

- Issue or trigger: issue `#168` to update quality tests for the GA4-first coverage model after Matomo/manual CSV retirement
- What changed: renamed the manual Search Console CSV importer coverage as legacy archive/backfill, updated its active automation assertion to require GA4/MM-Google readiness, and raised the PHPUnit memory limit used by `composer quality`
- What was fixed: `composer quality` now passes with Pint, PHPStan, and `436` tests; the suite no longer fails because a Matomo/manual CSV fixture is treated as complete active coverage
- What remains: neutralize Matomo-era naming in active contracts, then run final production cleanup verification after the retirement stack is complete

### 2026-05-07 11:58:20 AEST

- Issue or trigger: issue `#167` to archive Matomo analytics sources and promote GA4 where available
- What changed: added `analytics:archive-legacy-matomo-sources`, deployed commit `ef8af81` to production, ran the production dry-run, then ran `php artisan analytics:archive-legacy-matomo-sources --write`
- What was fixed: production archived `26` Matomo sources, set Matomo primary count to `0`, promoted valid GA4 sources so GA4 primary count is now `28`, and preserved all Matomo rows as archived legacy/backfill records; the post-run dry-run reports `0` Matomo sources left to archive, `0` GA4 sources left to promote, and `0` properties without valid GA4
- What remains: update broader GA4-first quality tests, neutralize Matomo-era naming in active contracts, and run final production cleanup verification

### 2026-05-07 11:51:29 AEST

- Issue or trigger: issue `#166` to retire Matomo/manual CSV UI from first-look dashboard and active navigation
- What changed: removed Matomo and manual CSV links from desktop/mobile navigation, removed the manual CSV dashboard card and preview section, removed the CSV-pending tab/stat from active automation coverage, and relabeled retained Matomo/manual CSV pages as legacy archive/backfill surfaces
- What was fixed: first-look operators no longer see manual CSV or Matomo as active readiness work, while historical archive routes remain available for intentional inspection
- What remains: archive Matomo sources while promoting GA4 where available, update broader GA4-first quality tests, neutralize active contract naming, and run production cleanup verification

### 2026-05-07 11:47:05 AEST

- Issue or trigger: issue `#165` to remove manual Search Console CSV from active automation coverage
- What changed: active automation coverage now completes from repository, GA4/MM-Google, Search Console, and baseline sync without requiring manual CSV; manual CSV remains a legacy/archive evidence summary for the old importer/backlog path only
- What was fixed: `manual_csv_pending` is no longer produced as an active automation status or emitted as an active automation tag, and stale `automation.manual_csv_pending` tags are cleaned up by `coverage:sync-tags`
- What remains: retire Matomo/manual CSV first-look dashboard UI, archive Matomo sources safely while promoting GA4, update broader GA4-first quality tests, neutralize active contract naming, and run production cleanup verification

### 2026-05-07 11:40:19 AEST

- Issue or trigger: issue `#164` to stop scheduled Matomo refresh jobs during the GA4-first retirement
- What changed: moved the Matomo install-audit and Matomo-mapped weekly Search Console baseline schedules behind the explicit `MATOMO_LEGACY_REFRESH_SCHEDULE_ENABLED=true` legacy opt-in, documented the scheduling rule, and added schedule-list coverage for the default production cadence
- What was fixed: normal scheduled tasks no longer run Matomo refresh jobs even when the repo still preserves Matomo command code and historical records for archive/backfill work
- What remains: remove manual CSV from active automation coverage, retire Matomo/manual CSV first-look UI, archive Matomo sources safely, update broader GA4-first quality tests, neutralize active contract naming, and run production cleanup verification

### 2026-05-07 11:36:36 AEST

- Issue or trigger: issue `#163` to plan the GA4-first retirement of Matomo/manual CSV active coverage before functional cleanup starts
- What changed: added `docs/GA4-FIRST-COVERAGE-RETIREMENT.md` as the repo-owned decision record and updated the SEO baseline workflow so MM-Google/Search Console is the active path while Matomo/manual CSV is legacy archive/backfill only
- What was fixed: future operators and agents now have a single documented rule that Matomo/manual CSV must not be treated as active required coverage, active domain health, or first-look work
- What remains: the later retirement issues still need to stop scheduled Matomo refreshes, remove manual CSV from active automation coverage, retire active UI surfaces, archive Matomo sources safely, update quality tests, neutralize active contract naming, and verify production cleanup

### 2026-05-07 11:11:49 AEST

- Issue or trigger: issue `#162` final production disk cleanup after the root Forge recipe successfully vacuumed journals but left `/` at `78%`
- What changed: reran production evidence after the root recipe, then pruned the two oldest Forge release directories via the `forge` user while keeping the current release plus one rollback release
- What was fixed: root disk is now below the issue threshold at `74%` used with `2.4G` free; journald retention was capped by the root recipe, journal usage dropped to about `170.7M`, and release storage dropped from `712M` to `356M`
- What remains: watch whether the 10GB droplet regrows toward the threshold during normal deploy/log churn; if it does, schedule a DigitalOcean volume resize rather than repeatedly shaving normal operating data

### 2026-05-07 11:00:40 AEST

- Issue or trigger: issue `#162` follow-up to finish Domain Monitor production root disk cleanup or resize after the first Forge release-prune pass
- What changed: rechecked production via `domain-monitor-server`, confirmed root disk is now `86%` used with `1.3G` free, identified the largest remaining safe root-owned reclaim targets as `/var/log/journal` at roughly `909M` and apt cache/list data at roughly `517M`, and confirmed the droplet is `TheBrainDeftly` on a 10GB DigitalOcean disk
- What was fixed: no further root-owned cleanup was performed from this checkout because `forge` sudo requires a password/TTY and direct `root@170.64.195.27` SSH is not authorized; Forge release retention is already within the current-plus-one-or-two rollback target
- What remains: run the root/Forge console maintenance commands to vacuum journald to about `200M`, clean apt caches/lists, verify `/` drops below `75%`, then record the final `df -h /` evidence and close issue `#162`; if cleanup cannot hold the target, schedule a DigitalOcean resize with a maintenance window

### 2026-05-07 10:29:39 AEST

- Issue or trigger: Control Plane still surfaced Moving Again `.com`, `.net`,
  and `.net.au` parked-domain findings as SEO/GA/indexability should-fix work.
- What changed: parked primary domains are now excluded from live monitoring
  lanes that expect a real website, parked-domain monitoring findings are
  suppressed from `/api/issues` and `/api/web-properties-summary`, and parked
  domains can only enter the priority queue through a domain-expiry warning
  inside the 14-day window.
- What was fixed: parked domains no longer generate or export live-site noise
  for GA4 install, structured data, agent-readiness, indexability, controller
  drift, or Fleet Astro technical SEO checks.
- What remains: deploy to production, rerun the relevant Domain Monitor
  summaries, and refresh Control Plane so the Moving Again parked TLD rows drop
  out unless one is within two weeks of expiry.

### 2026-05-07 07:50:06 AEST

- Issue or trigger: issue `#161` to harden Search Console coverage refresh blockers after the Moving Again production refresh exposed GSC token and Matomo SSL coupling failures
- What changed: forced Search Console API enrichment failures now persist a `search_console_refresh_failed` coverage record with the exact exception message, coverage summaries treat that refresh failure as the active `blocked_unavailable` operator state before stale-import handling, and domain-scoped automation coverage refreshes continue with existing domain state when the Matomo coverage sync throws or fails
- What was fixed: Moving Again-style token failures can surface `Unable to refresh the Google Search Console access token (400).` as the current blocker, while `analytics:refresh-automation-coverage --domain=...` no longer aborts the requested domain just because a Matomo coverage sync path has an unrelated SSL/API failure
- What remains: the underlying Search Console credential repair still belongs with the upstream Google/MM-Google source; once credentials are repaired, a successful Domain Monitor refresh should replace the persisted blocker state with fresh evidence

### 2026-05-07 07:15:04 AEST

- Issue or trigger: issue `#160` to add an external reference policy for outbound link inventory
- What changed: added a policy classifier for outbound hosts, persisted classification/action/reason metadata in external link inventory payloads, exposed policy counts for Control Plane consumers, and moved deep-audit review behavior onto policy actions instead of blunt external-host rules
- What was fixed: known Domain Monitor/Fleet estate hosts, source-attached operational surfaces, official `.gov.au`/authority references, approved partners, review-required hosts, disallowed hosts, and broken/unverified inventory states now have explicit outcomes; the deep audit only opens `cleanup.external_links_inventory` findings for `review_required` or `disallowed` links
- What remains: production inventory rows need to be refreshed by the normal monitoring lanes before Control Plane summaries show the new policy fields for previously scanned properties

### 2026-05-07 06:58:30 AEST

- Issue or trigger: issue `#159` to clarify Search Console coverage freshness states for Control Plane consumers
- What changed: expanded Search Console coverage summaries with explicit `operational_state`, freshness, last successful evidence/import timestamps, blocker, checked-at, and next-action fields while preserving existing status labels for current consumers
- What was fixed: stale, blocked/unavailable, failing, excluded, and fresh Search Console states now have operator-readable outcomes so Control Plane does not have to guess whether a stale coverage item should become website work; `movingagain.com.au` can now be represented as stale with the last evidence date and refresh action instead of an ambiguous import-stale flag
- What remains: Domain Monitor now owns the classification contract; the actual Search Console import still needs to be refreshed by the normal MM-Google/import path when a property is stale or blocked

### 2026-05-07 06:14:53 AEST

- Issue or trigger: issue `#158` to detect live Astro/Vercel cutovers that still have stale WordPress/_wp-house controller metadata
- What changed: added a `controller_metadata` monitoring lane backed by a live homepage Astro-evidence probe, surfaced drift as the advisory `controller_metadata_drift` finding, and widened Astro detection to recognise `/_astro/` assets
- What was fixed: Domain Monitor can now open a normal monitoring finding when live platform evidence indicates Astro but stored controller metadata still points at WordPress/_wp-house, with evidence and a safe promotion-command hint for downstream Control Plane review
- What remains: the lane needs to be scheduled in production alongside the existing monitoring lanes, then any detected rows should be promoted with `web-properties:promote-controller` rather than treated as broken websites

### 2026-05-06 19:23:44 AEST

- Issue or trigger: Bossman/control-plane review found `interstatecarcarriers.com.au` and `supercheapcartransport.com.au` still surfacing as WordPress after their Astro/Vercel cutovers
- What changed: updated the seeded Domain Monitor controller metadata for both properties from `_wp-house`/WordPress to their Astro controller repos, including Vercel deployment project names
- What was fixed: future bootstraps and summaries now align the repo source of truth with the live Astro/Vercel evidence instead of reintroducing stale WordPress controller metadata
- What remains: production still needs the existing `web-properties:promote-controller` command run for both rows, followed by a Control Plane import refresh so the hosted overview reflects the corrected controller state

### 2026-05-05 13:49:14 AEST

- Issue or trigger: issue `#157` to surface GA4 marketing interaction v2 readiness, plus local cleanup of bootstrap target override refresh behavior
- What changed: added the MM-Google `marketing-interaction-v2` event contract to the repo sync config, exposed a new `analytics.ga4.marketing_interaction_v2` summary block, and taught `web-properties:bootstrap --refresh-links` to refresh target URL overrides on already-linked properties
- What was fixed: Domain Monitor can now distinguish base GA4 readiness from optional marketing interaction v2 adoption without reviving Matomo readiness or duplicating MM-Google event and parameter names; the Perth Interstate Removalists contact target override can now be corrected through the bootstrap config
- What remains: downstream MM-Google or Fleet evidence still needs to promote individual property assignments from `defined` to `instrumented` or `verified`; Domain Monitor now surfaces that state instead of inventing independent event taxonomy

### 2026-05-02 17:29:00 AEST

- Issue or trigger: MovingAgain SEO intelligence pilot live URL verification before Fleet controlled-test routing
- What changed: captured `docs/evidence/live-seo-verification/movingagain-cairns-toowoomba-2026-05-02.json` using the live SEO verification packet contract against `https://movingagain.com.au/cairns-toowoomba/`
- What was fixed: the pilot now has a repo-owned live-truth artifact showing HTTP `200`, matching canonical, no noindex signal, fetchable robots state, current title/meta, and zero broken links in the sampled page-local check
- What remains: Domain Monitor has no site-writing role here; Fleet and MM Content Studio own the controlled-test brief/draft, and MM-Google owns follow-up measurement writeback

### 2026-05-02 09:05:00 AEST

- Issue or trigger: issue `#154` follow-up contract tightening for Search Intelligence live SEO verification packets
- What changed: expanded the existing `/api/web-properties/{slug}/live-seo-verification` packet input to carry measurement, evidence, site, expected canonical, owning repo, reason, and requested-check metadata; aligned packet verdicts to `passes_live_verification`, `needs_attention`, and `inconclusive`; added basic title/meta evidence and a concise packet doc with a Moving Again pilot shape
- What was fixed: Search Intelligence callers can now reference a repo-owned live-truth packet without translating older `packet_ready` or `page_unavailable` labels and without mixing live URL evidence with MM-Google measurement truth
- What remains: no Search Console ownership, site repo writes, Fleet gate work, or automatic downstream issue creation belongs in this slice

### 2026-05-02 08:11:00 AEST

- Issue or trigger: issue `#154` to define a live SEO verification packet for Search Intelligence URLs
- What changed: added a narrow authenticated `/api/web-properties/{slug}/live-seo-verification` packet, documented it in integration metadata, reused the site-signal scanner for live page fetch, canonical, robots, redirect, and limited page-local link evidence, and added focused API coverage for exact URLs and pattern-plus-sample verification
- What was fixed: `domain-monitor` now exposes a repo-owned live verification packet that MM-Google or adjacent operators can call for one selected URL without widening into Search Console analysis, broad crawling, or site-repo issue generation
- What remains: no additional in-repo implementation work is required for `#154`; follow-up is limited to GitHub issue closeout and any later decision to widen the packet beyond the current evidence limits

### 2026-05-01 07:27:00 AEST

- Issue or trigger: daily Bossman overseer review for issue `#153`
- What changed: verified the repo already exposes the canonical Fleet must-fix contract through `/api/issues` with `fleet_focus=1`, labels `/api/dashboard/priority-queue` as priority-queue-only, and shows the same distinction on the dashboard; also reran focused API and dashboard tests covering the aggregate and UI wording
- What was fixed: removed issue drift by confirming the must-fix source-of-truth mismatch has already been addressed in this repo, so downstream operators can use the documented detected-issue feed instead of reading priority-queue stats as total must-fix truth
- What remains: no in-repo implementation work remains for `#153`; the unrelated local repo state is still the pre-existing modified [`/Users/jasonhill/Projects/Business/operations/domain-monitor/OVERSEER.md`](/Users/jasonhill/Projects/Business/operations/domain-monitor/OVERSEER.md) history and untracked [`/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md`](/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md), neither of which changed outside this closeout entry

### 2026-04-30 04:02:25 AEST

- Issue or trigger: daily Bossman overseer verification for issue `#152`
- What changed: rechecked the only open repo issue against local `main`, confirmed commit `406d06e` already implements the full Matomo-retirement slice, and reran the focused queue and UI coverage tied to that change
- What was fixed: removed issue drift by verifying the work is already landed in this repo and ready for GitHub closeout instead of leaving `#152` open after the implementation shipped
- What remains: no in-repo implementation work remains for `#152`; the only non-issue repo state is the pre-existing untracked [`/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md`](/Users/jasonhill/Projects/Business/operations/domain-monitor/docs/FLEET-ASTRO-TECHNICAL-SEO-MONITORING-PLAN.md), which was not touched in this run

### 2026-04-29 15:16:30 AEST

- Issue or trigger: issue `#152` to retire Matomo-facing Fleet analytics readiness surfaces
- What changed: switched the active Fleet automation and Search Console readiness model from Matomo to MM-Google GA4, rewired the automation queue, property checklist, dashboard/manual CSV metadata, and search-console queue to show GA4-first readiness labels, and relabeled the old Matomo queue/navigation as a legacy archive rather than an active requirement
- What was fixed: operators no longer see active `Needs Matomo` readiness states in Fleet-facing queue or checklist surfaces; active readiness now flows from `analytics.ga4`, Search Console fallback copy points to MM-Google GA4 sync instead of Matomo, and the remaining Matomo inventory is clearly presented as archive/backfill-only
- What remains: the repo-local implementation is complete and focused tests are passing, but the change still needs the usual commit/push/issue-close step and any later cleanup of historical Matomo storage should stay out of scope unless it comes with a safe migration/archive plan

### 2026-04-29 15:00 AEST

- Issue or trigger: Fleet-wide decision that GA4 is now the only active analytics source for rollout decisions
- What changed: updated the API integration docs and coverage tag descriptions so active Fleet analytics readiness points at GA4 through Domain Monitor instead of Matomo-era coverage wording
- What was fixed: the current API contract now states that `analytics.ga4` is the Fleet-facing lookup block for active analytics decisions, while legacy collector paths are recovery/backfill-only
- What remains: deeper UI, queue, model, and legacy import cleanup is tracked in issue `#152`; do not remove historical database/storage paths without a safe migration or archive plan

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

### 2026-05-07 10:47:33 AEST

- Issue or trigger: Control Plane still saw `movingagain.com.au` as stale after Domain Monitor confirmed current MM-Google Search Console coverage was fresh
- What changed: exposed the current `search_console` coverage summary in the existing `/api/web-properties-summary` contract alongside the older `gsc_evidence_summary` evidence block
- What was fixed: downstream consumers no longer have to infer first-look freshness from legacy Search Console issue evidence when the newer MM-Google coverage state already reports a fresh checked/imported timestamp
- What remains: deploy this API contract update, then rerun the Control Plane Domain Monitor import so the stale Moving Again freshness signal resolves from the authoritative current coverage field

### 2026-05-07 10:56:44 AEST

- Issue or trigger: follow-up from production deploy showing Domain Monitor root disk near 90% and a missing `composer quality` script
- What changed: pruned old Forge release directories on production while keeping the current release plus one rollback release, and added `composer quality` as the repo-standard alias for the existing `composer check` script
- What was fixed: production root disk dropped from about 90% used to 84% used with roughly 1.5G free, and future operator runs can use the same `composer quality` command shape as adjacent repos
- What remains: root-owned system journals, apt cache, and the tiny 8.65G root volume still need a Forge/root maintenance pass or resize; the full local `composer check` suite still has pre-existing manual CSV / coverage-tag failures plus a PHP memory ceiling, separate from this alias change
