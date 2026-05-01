# Fleet Astro Technical SEO Monitoring Plan

Last updated: 2026-04-28

## Why This Exists

Fleet already owns the canonical Astro technical standard and migration
compliance gate.

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

## What `domain-monitor` Should Verify

The first monitoring slice should stay narrow and verify the Fleet standard at
runtime rather than rebuilding it.

Recommended first checks:

- homepage and key-route PageSpeed snapshot
- robots presence and correctness
- sitemap presence and basic discovery
- canonical tag presence on homepage and key routes
- obvious indexability blockers on key routes
- preferred-root and key-route redirect sanity

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

The best next step is not a giant new crawler lane.

The best next step is to extend the existing lanes with a small Fleet-Astro
verification slice, most likely by:

- expanding `seo_agent_readiness` and or `marketing_integrity` with a narrow
  technical SEO baseline check set
- keeping PageSpeed snapshots weekly rather than trying to run them too often
- opening alerts only after repeated regression, not one noisy sample

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
