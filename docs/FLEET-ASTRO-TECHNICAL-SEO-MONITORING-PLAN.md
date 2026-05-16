# Fleet Technical SEO Runtime Verification

Last updated: 2026-05-16

## Why This Exists

Fleet already owns the canonical Astro technical standard and migration
compliance gate.

This program is called `Fleet Technical SEO Runtime Verification`.

`domain-monitor` should not create a second standard.

It should provide the recurring live verification layer that proves whether
Fleet-managed public sites are still meeting that standard after launch or
during rollout.

## Upstream Standard Sources

This plan should stay aligned to the existing Fleet-owned documents:

- [`/Users/jasonhill/Projects/Business/websites/MM-fleet-program/docs/FLEET-TECHNICAL-QUALITY-POLICY.md`](/Users/jasonhill/Projects/Business/websites/MM-fleet-program/docs/FLEET-TECHNICAL-QUALITY-POLICY.md)
- [`/Users/jasonhill/Projects/Business/websites/MM-fleet-program/docs/FLEET-MIGRATION-COMPLIANCE-FRAMEWORK.md`](/Users/jasonhill/Projects/Business/websites/MM-fleet-program/docs/FLEET-MIGRATION-COMPLIANCE-FRAMEWORK.md)

Those documents already define the standard for:

- sitemap and robots behavior
- canonical URL control
- shared SEO graph baseline
- analytics readiness
- redirect hygiene
- technical SEO sign-off expectations

## Ownership Boundary

### Fleet owns

- the Astro standard itself
- the migration compliance gate
- starter conventions
- site-repo readiness expectations

### `MM-Google` owns

- GA4, Search Console, and BigQuery identity or readiness
- Google-side search intelligence
- retained-history and sync-health evidence
- plain-English summaries over those Google/search signals

### `domain-monitor` owns

- recurring live technical SEO verification
- weekly or daily drift detection
- alert state and issue flow when a previously healthy site regresses
- cross-domain verification that should keep running after launch

## Program Boundary

`Fleet Technical SEO Runtime Verification` means:

- `domain-monitor` checks live Fleet-managed website properties against the
  Fleet-owned technical SEO standard
- Fleet defines the standard, sign-off language, and remediation routing rules
- site repos fix site-specific failures surfaced by runtime verification
- `MM-Google` contributes Google/Search Console/GA4 evidence only where that
  source owns the truth

It does not mean building a generic competitor-style SEO crawler or duplicating
Fleet standards inside `domain-monitor`.

The target scope is full Fleet-standard runtime auditing for operational
website properties only. This is broader than the first narrow runtime lane,
but still bounded by Fleet applicability. Parked domains, email-only domains,
domain assets, and deliberately non-operational properties are excluded by
default unless Fleet records an explicit per-property expectation.

A `WebProperty` is eligible for full Fleet technical SEO runtime auditing by
default only when all of these are true:

- status is `active`
- property type is `website`
- it has a usable production URL or canonical primary domain
- it is not classified as parked, email-only, domain asset, or
  non-operational
- it is not an app shell unless Fleet explicitly marks it as website-policy
  eligible
- it has enough controller and canonical-origin context to choose applicable
  checks

All other properties should return `not_applicable` for the full runtime audit
unless Fleet records an explicit override.

The canonical runtime audit unit is a `WebProperty`, not a raw domain. Domains,
subdomains, and URLs are linked surfaces used as selectors and evidence
locations. If an operator starts from `--domain=example.com`,
`domain-monitor` should resolve that domain to the owning `WebProperty` before
applying Fleet rules. The property context determines site type, status,
canonical origin, controller repo, linked domains, and applicable checks.

The runtime should support one-`WebProperty` execution, with domain-based
selection as a convenience, so expensive checks can be run deliberately without
sweeping the whole estate every time. For each applicable Fleet catalog check,
`domain-monitor` should record one status:

- `pass`
- `fail`
- `not_applicable`
- `manual_review`
- `unknown`

Each check result should also record `evidence_confidence`:

- `high`
- `medium`
- `low`

Control Attention should receive a runtime finding only when
`result_status = fail` and `evidence_confidence = high`, or when Fleet has
explicitly approved that check to surface medium-confidence failures. Low
confidence, weak crawler signals, flaky external dependencies, and ambiguous
evidence should be stored as audit evidence or `unknown`, not promoted to
Attention automatically.

The full runtime audit is a bounded crawl, not a homepage-only check. The
default URL set for an eligible `WebProperty` should include:

- the production URL or canonical homepage
- URLs listed in sitemap, capped by a configurable limit
- key internal links discovered from the homepage and sampled sitemap pages
- declared conversion, contact, quote, or booking URLs from `domain-monitor`
  property context
- manually configured critical URLs when Fleet or the site repo provides them

The initial default cap should be conservative, such as 25 URLs per
`WebProperty`, with per-property overrides. URLs skipped because of the cap
must be recorded as `not_checked_due_to_limit`; they must not be treated as
passing evidence.

Each catalog check should declare an execution mode so cheap deterministic
checks can run more often and expensive or subjective checks can run
deliberately. Use these execution modes:

- `http_fetch`: status, redirects, headers, robots, and sitemap fetchability
- `html_parse`: title, meta, canonical, headings, links, images, and schema
  parsing from fetched HTML
- `bounded_crawl`: duplicate metadata, broken internal links, sitemap URL
  consistency, and sampled page relationships
- `browser_render`: rendered DOM, JavaScript redirects, console errors, and
  mobile layout basics
- `lighthouse_lab`: Core Web Vitals-style lab metrics, accessibility, and
  best-practice checks
- `imported_evidence`: Search Console, GA4, `MM-Google`, or retained benchmark
  artifacts
- `manual_review`: soft-404 judgement, content relevance, semantic judgement,
  and policy exceptions

The full audit may run in phases by execution mode instead of one giant sweep.

Only `fail` should open or update a runtime finding for Control Attention by
default. `unknown` should create an owning-repo GitHub issue when the evidence
or ownership cannot be determined from current docs, code, live checks, or
imported source truth.

An `unknown` result should create a GitHub issue only when it is durable and
actionable. Create an issue when all of these are true:

- the check applies to the `WebProperty`
- `domain-monitor` cannot determine pass or fail after one retry or a
  reasonable secondary evidence source
- the unknown affects a rule that could become a failure if true
- the owner repo or system can be identified, or the issue is routed to
  Bossman for ownership decision
- the issue can be deduped by `web_property + check_id + owner_repo`

Do not create GitHub issues for:

- URLs skipped because of the crawl cap
- a transient timeout on first attempt
- checks marked `manual_review`
- checks where Fleet says unknown is an acceptable evidence state
- one issue per affected URL unless the owning repo explicitly needs URL-level
  tickets

`domain-monitor` should store full audit evidence separately from
Attention-facing findings. Conceptually:

- `fleet_technical_seo_audit_runs`
  - `web_property_id`
  - trigger type, such as manual, scheduled, or operator-requested
  - URL cap
  - execution modes included
  - started and finished timestamps
  - summary counts
- `fleet_technical_seo_audit_results`
  - `run_id`
  - `check_id`
  - URL or property-level evidence target
  - `result_status`
  - `evidence_confidence`
  - evidence payload
  - owner system
  - linked `domain-monitor` finding, when a result becomes a failure
  - linked GitHub issue URL, when a durable `unknown` needs owner resolution

`MonitoringFinding` remains the Attention-facing failure surface, not the full
audit record.

The first implementation slice should build the audit runner, storage, bounded
URL selection, and deterministic execution modes:

- `http_fetch`
- `html_parse`
- `bounded_crawl`
- `imported_evidence`, where the evidence already exists

The first slice should not be considered the complete end state. These later
execution modes must remain tracked as explicit follow-up work rather than
being forgotten:

- `browser_render`
- `lighthouse_lab`
- broad accessibility automation
- manual-review workflow UI

If those later modes are not implemented in the first slice, create or keep
repo-owned GitHub issues for them so the missing coverage is visible.

Control should import and display the full-audit summary separately from
Attention. The summary view should show, per `WebProperty`:

- latest full audit status
- counts by `pass`, `fail`, `manual_review`, `unknown`, and `not_applicable`
- execution modes included
- URL cap and skipped URL count
- timestamp and source link back to `domain-monitor`
- Attention link only when high-confidence failures exist

Control Attention remains failures-only. Clean passes, skipped URL counts,
manual-review counts, and general audit coverage should be visible in a calm
audit summary surface rather than becoming Attention signals.

Fleet remains the canonical owner of the machine-readable technical SEO check
catalog. Fleet owns check IDs, applicability, signal class, wording, and policy
decisions. `domain-monitor` may import or mirror a versioned snapshot of the
Fleet catalog for execution, but that snapshot must not become a competing
standard.

Each `domain-monitor` audit run should record the Fleet catalog version or
checksum it executed. Runtime-specific implementation fields may live in
`domain-monitor` as mapping metadata, but they must point back to Fleet-owned
check IDs. Control may display the catalog version and check IDs from
`domain-monitor` evidence, but Control does not own the catalog.

Any actionable runtime verification issue should be visible in Control
Attention at `https://control.again.com.au/admin/attention` so an operator can
decide whether to route, suppress, accept, or investigate it. `domain-monitor`
remains the source truth for live findings; Control is the operator decision
surface.

Control Attention should receive failures only. Clean passes, healthy coverage
rows, and informational catalog entries should stay in Fleet or `domain-monitor`
evidence and should not become Attention noise.

## Signal Classes

Each catalog check should declare one signal class:

- `failure`: opens or updates a `domain-monitor` finding and appears in Control
  Attention
- `review`: is recorded in catalog or evidence, but only appears in Control if
  Fleet or an operator promotes it
- `evidence_only`: is stored for proof, benchmarking, sign-off, or trend context
  and never appears in Control Attention by default

Hard technical failures such as invalid SSL, HTTP 5XX, broken internal links,
bad canonicals, or unexpected noindex signals should normally be `failure`.
Softer thresholds such as title length, social preview dimensions, optional
AI-discovery files, or benchmark scores should usually start as `review` or
`evidence_only` unless Fleet defines them as launch blockers for a site type.

## Applicability By Site Type

The catalog should not apply one universal checklist to every property. Each
check should declare the site types it applies to, using at least:

- `fleet_astro_marketing_site`
- `wordpress_holding_site`
- `plain_html_static_site`
- `app_shell_or_operational_domain`
- `domain_asset_or_parked`
- `email_only`

For example, missing JSON-LD or `llms.txt` may be a real finding for a Fleet
Astro marketing site but should not create Control Attention noise for a parked
domain, email-only domain, or app shell unless Fleet has recorded an explicit
expectation for that property.

## Catalog Artifact

Fleet should own a machine-readable technical SEO check catalog in addition to
the human-readable policy docs.

The catalog should include fields such as:

- `id`
- `category`
- `description`
- `applicability`
- `coverage_state`
- `signal_class`
- `owner_system`
- `current_evidence`
- `runtime_mapping`

`domain-monitor` should consume or mirror the Fleet-owned catalog rather than
hard-coding an undocumented checklist. The runtime implementation can still
keep its own local mapping where needed, but the standard and check taxonomy
belong to Fleet.

## Coverage Inventory Rule

The first pass through the catalog should classify existing coverage before
building new checks.

If a check cannot be clearly classified from current docs, code, live evidence,
or imported source truth, it should not remain as an ambiguous note. Create a
GitHub issue in the relevant owning repo so the ambiguity can be resolved,
implemented, marked not applicable, or deliberately excluded.

## Issue Routing

Use this default owner map when catalog coverage is missing, unclear, or failing:

- Fleet issue: catalog taxonomy, applicability, signal class, launch-gate
  wording, or site-type policy is unclear
- `domain-monitor` issue: live runtime check, finding generation, cadence,
  evidence packet, or Control export mapping is missing or unclear
- Control issue: a valid `domain-monitor` failure is not visible or actionable
  in `/admin/attention`
- `MM-Google` issue: GA4, Search Console, BigQuery, or Search Intelligence
  evidence is missing, stale, or ambiguous
- site-repo issue: the failure is specific to one website implementation, such
  as missing canonical, broken internal links, bad schema, robots or sitemap
  output, image alt, H1, or PageSpeed regression
- Bossman issue: cross-repo decision or routing is unclear and no single repo
  owner can be chosen safely

## Verification Scope

The program should maintain a complete technical SEO check catalog rather than
only a small MVP checklist.

Each check in that catalog should be classified by:

- whether it is already covered
- which repo or system owns it
- its coverage state
- whether it applies to all Fleet websites or only specific site types
- what evidence proves the check passed or failed

The purpose is to stop repeatedly reopening broad technical SEO discovery and
instead give operators one durable coverage map for the whole estate.

## Coverage States

Use these states when classifying each check:

- `automated_runtime`: checked by recurring live monitoring, usually in
  `domain-monitor`
- `automated_repo`: checked inside the owning site repo, build, test, or static
  analysis path
- `evidence_imported`: covered by an imported evidence source such as
  `MM-Google`, Search Console, GA4, or a preserved benchmark artifact
- `manual_gate`: checked during Fleet sign-off or migration review with durable
  evidence recorded
- `planned`: accepted as relevant but not yet covered strongly enough
- `not_applicable`: does not apply to this site type or property
- `out_of_scope`: deliberately excluded from Fleet Technical SEO Runtime
  Verification

## How This Fits Existing Monitoring Lanes

Current lanes already cover part of this space:

- `marketing_integrity`
  - GA4 install
  - conversion-surface GA4
  - indexability
  - quote handoff integrity
- `seo_agent_readiness`
  - structured data
  - agent readiness
- `critical_live`
  - redirect policy

The best next step is not a blind giant crawler lane.

The best next step is to inventory the full check catalog against existing
Fleet, `domain-monitor`, `MM-Google`, and site-repo evidence, then create
targeted issues only for uncovered or weakly covered checks.

Likely implementation work should still extend existing lanes where possible,
keep noisy checks on sensible cadences, and open alerts only when the evidence
is strong enough to avoid churn.

## What Should Stay Out Of Scope

Do not widen this into:

- a second Fleet standards framework
- a full-page crawler product
- full build-lint or site-repo compile checks
- a broad warehouse SEO mart
- generic AI scoring or vague quality grades

## Practical Next Step

1. Keep Fleet docs as the standard source of truth.
2. Add a narrow Domain Monitor issue for weekly Fleet Astro technical SEO
   verification.
3. Start with live checks that are easy to interpret and low-noise.
4. Use those checks to prioritize real site conversion and regression cleanup.
