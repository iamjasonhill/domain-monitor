# SEO Baseline Workflow

Use this workflow before any meaningful website overhaul, rebuild, re-platform, or major SEO cleanup.

The goal is simple:

- measure first
- store a domain-level baseline
- only then plan and build

This avoids redesigning or migrating blind when Google already knows something useful about a site.

## System Roles

### MM-Google Owns Active Search Data

MM-Google is the active source for current GA4 and Search Console coverage
truth. See `docs/GA4-FIRST-COVERAGE-RETIREMENT.md` for the retirement
decision that makes Matomo/manual CSV legacy archive/backfill only.

MM-Google should answer:

- Search Analytics totals
- page/query/country/device breakdowns
- Search Console property mapping and readiness
- current coverage, blocker, and freshness state

Matomo may still answer historical/backfill questions:

- what legacy Search Console history was imported before retirement?
- which legacy Matomo `idSite` a preserved baseline came from?
- what old backfill evidence explains a prior operator decision?

### domain-monitor Owns Domain-Level Baselines

`domain-monitor` stores the normalized baseline snapshot for each domain or property milestone.

It should answer:

- what was the pre-change baseline for this domain?
- when was it captured?
- what did indexation and search visibility look like at that point?
- do we have enough baseline data to begin a rebuild?

### MM-Google Owns The Search Console Export

MM-Google is the preferred producer of the Search Console coverage/baseline
export.

It should answer:

- is the Search Console property ready?
- what domain-level coverage state should Domain Monitor store?
- what baseline snapshot should Domain Monitor normalize from the export?

### MM BRAIN Owns Operator Context

`MM BRAIN` should hold:

- rebuild readiness workflow
- reasons and recommendations
- links to supporting artifacts
- historical change events

Raw search metrics should not live in Brain as the canonical source.

## Default Rebuild Rule

Before a site enters active rebuild work, all of the following should be true:

1. the property has GA4/MM-Google coverage or an explicit provisioning state
2. the property has current Search Console coverage or an explicit blocker
3. a domain-level SEO baseline snapshot exists in `domain-monitor`
4. any optional historical Matomo/manual CSV context is preserved as evidence, not active required work

Exception:

- very low-value or disposable sites can skip this process if the overhead is not justified

For money sites, operational sites, and serious rebuild candidates, do not skip it.

## Standard Workflow

### 1. Confirm The Active Analytics Binding

Confirm:

- GA4 binding exists or has an explicit MM-Google provisioning state
- expected GA4 measurement ID is available when provisioned
- Search Console property is mapped or has an explicit blocker

### 2. Import What The Active API Can Provide

Use the MM-Google/Search Console path to import:

- daily summary
- page
- query
- country
- device

Minimum expected import:

- latest 90 days

### 3. Preserve Manual Search Console Exports If Needed

If Google exposes useful UI-only context that is not yet in MM-Google or Domain Monitor:

- export the CSV from Search Console
- store it as an operator artifact
- do not treat the CSV as the long-term canonical source
- do not require the CSV for active domain health

Recommended artifact location:

- `MM BRAIN/memory/search-console-artifacts/<slug>/<YYYY-MM-DD>/`

This keeps the evidence trail without turning Brain into the raw metrics source.

### 4. Save The Baseline Snapshot In domain-monitor

Create a normalized baseline snapshot using the imported MM-Google replacement export and any manual issue summaries.

### 5. Review Baseline Before Build

Only after the snapshot exists should we decide:

- preserve as-is
- tidy in place
- rebuild on the same platform
- re-platform

## Baseline Snapshot Fields

These are the fields that matter at the domain level.

### Identity

- `domain`
- `web_property_slug`
- `captured_at`
- `captured_by`
- `baseline_type`
  - `pre_rebuild`
  - `pre_cutover`
  - `post_launch_30d`
  - `post_launch_90d`
  - `manual_checkpoint`

### Source Metadata

- `matomo_site_id`
- `search_console_property_uri`
- `search_type`
  - default: `web`
- `date_range_start`
- `date_range_end`
- `import_method`
  - `mm_google_export`
  - `matomo_api`
  - `matomo_plus_manual_csv`
- `artifact_path`
  - nullable

### Search Visibility Summary

- `clicks`
- `impressions`
- `ctr`
- `average_position`

These should normally reflect the chosen baseline window, for example the previous 90 days.

### Indexation Summary

- `indexed_pages`
- `not_indexed_pages`

If available, also capture the latest known counts for:

- `pages_with_redirect`
- `not_found_404`
- `blocked_by_robots`
- `alternate_with_canonical`
- `crawled_currently_not_indexed`
- `discovered_currently_not_indexed`
- `duplicate_without_user_selected_canonical`

### Optional Business Summary

These are useful but should remain secondary to the raw imported data:

- `top_pages_count`
- `top_queries_count`
- `notes`

Do not store the full top-page and top-query tables in `domain-monitor` if Matomo already owns them.
Store summaries, not the full raw dataset.

## Snapshot Timing

At minimum, capture these checkpoints:

1. before rebuild work starts
2. immediately before launch or cutover
3. 30 days after launch
4. 90 days after launch

Also capture a new baseline whenever:

- a domain moves platform
- a domain changes host
- major URL consolidation happens
- large content pruning happens

## What Gets Compared Later

When judging whether a rebuild helped, compare:

- indexed pages
- not indexed pages
- clicks
- impressions
- CTR
- average position
- major issue counts

This creates a simple before/after answer without requiring operators to manually reconstruct history from multiple tools.

## What Not To Do

Do not:

- start a major rebuild without a baseline
- use Brain notes as the only record of SEO/search state
- store full raw Search Console page/query datasets in `domain-monitor`
- require Matomo/manual CSV evidence for active coverage health
- assume Search Console UI exports are a permanent substitute for MM-Google/Search Console imports

## Near-Term Implementation Shape

The practical model should be:

1. MM-Google imports or exports current Search Console coverage and baseline data
2. `domain-monitor` stores milestone baseline snapshots per domain/property
3. `MM BRAIN` reads both and reasons about readiness, risk, and outcomes

That keeps ownership clear:

- MM-Google = active GA4 and Search Console source of truth
- Matomo/manual CSV = preserved legacy archive/backfill evidence
- `domain-monitor` = operational baseline record
- `MM BRAIN` = interpretation and planning
