# SEO Baseline Workflow

Use this workflow before any meaningful website overhaul, rebuild, re-platform, or major SEO cleanup.

The goal is simple:

- measure first
- store a domain-level baseline
- only then plan and build

This avoids redesigning or migrating blind when Google already knows something useful about a site.

## System Roles

### Matomo Owns Raw SEO/Search Data

Matomo is still the legacy raw source for older Search Console-derived data:

- Search Analytics totals
- page/query/country/device breakdowns
- Search Console property mapping to Matomo `idSite`
- scheduled imports and backfills

Matomo should answer:

- what search data do we have right now?
- which pages and queries are performing?
- what imported Search Console history is available?

### domain-monitor Owns Domain-Level Baselines

`domain-monitor` stores the normalized baseline snapshot for each domain or property milestone.

It should answer:

- what was the pre-change baseline for this domain?
- when was it captured?
- what did indexation and search visibility look like at that point?
- do we have enough baseline data to begin a rebuild?

### MM-Google Owns The Replacement Export

MM-Google is the preferred producer of the Search Console coverage/baseline
replacement export.

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

1. the domain has a Matomo site binding
2. the domain has a Search Console property mapping in Matomo
3. at least one Search Console backfill has completed
4. a domain-level SEO baseline snapshot exists in `domain-monitor`
5. any non-API Search Console exports needed for context are stored as operator artifacts and linked

Exception:

- very low-value or disposable sites can skip this process if the overhead is not justified

For money sites, operational sites, and serious rebuild candidates, do not skip it.

## Standard Workflow

### 1. Bind The Domain In Matomo

Confirm:

- Matomo `idSite` exists
- site tracking code is live on the website
- Search Console property is mapped

### 2. Import What The API Can Provide

Use Matomo Search Console integration to import:

- daily summary
- page
- query
- country
- device

Minimum expected import:

- latest 90 days

### 3. Capture Manual Search Console Exports If Needed

If Google exposes useful UI-only context that is not yet in MM-Google or Domain Monitor:

- export the CSV from Search Console
- store it as an operator artifact
- do not treat the CSV as the long-term canonical source

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
- assume Search Console UI exports are a permanent substitute for Matomo imports

## Near-Term Implementation Shape

The practical model should be:

1. Matomo imports raw Search Console data
2. `domain-monitor` stores milestone baseline snapshots per domain/property
3. `MM BRAIN` reads both and reasons about readiness, risk, and outcomes

That keeps ownership clear:

- Matomo = raw SEO/search engine
- `domain-monitor` = operational baseline record
- `MM BRAIN` = interpretation and planning
