# Web Property Grouping Plan

Date: 2026-03-24

## Goal

The first bootstrap pass created one `web_property` per active domain. That is the right low-risk baseline, but it is too granular for day-to-day operational use.

This document defines the second pass:

- merge obvious aliases and parked variants into shared `web_property` records
- keep true standalone sites as their own properties
- avoid merging SEO/acquisition domains into a shared property unless there is strong evidence they are redirects or alternate brand domains

## Current Baseline

Production currently has:

- `68` `web_properties`
- `68` `web_property_domains`
- `4` repository links
- `1` analytics link

Known website repos in [`/Users/jasonhill/Projects/websites`](/Users/jasonhill/Projects/websites):

- `again-com-au-astro`
- `cartransport-au-astro`
- `cartransportwithpersonalitems-com-au-astro`
- `ma-car-transport-astro`
- `moveroo-website-astro`
- `moving-again-astro`
- `moving-insurance-astro`

Known Matomo site IDs from [`/Users/jasonhill/Projects/2026 Projects/Matamo /README.md`](/Users/jasonhill/Projects/2026%20Projects/Matamo%20/README.md):

- `5` Moveroo
- `6` Moveroo website
- `7` Car transport by Moving Again

## High-Confidence Grouping Candidates

These are safe enough to group in the next pass because they are clear brand aliases, parked variants, or TLD siblings.

### 1. `moveroo-website`

Keep as canonical property:

- primary domain: `moveroo.com.au`

Attach these additional domains:

- `moveroo.au`
- `moveroo.click`

Reasoning:

- `moveroo.com.au` already has the repo override and Matomo site `6`
- `moveroo.au` is a brand-TLD sibling
- `moveroo.click` is parked and reads like a campaign/alias domain rather than a standalone property

Do not merge yet:

- `mover.com.au`

Reasoning:

- similar name, but not strong enough evidence that it is intended as the same property

### 2. `moving-again`

Keep as canonical property:

- primary domain: `movingagain.com.au`

Attach these additional domains:

- `movingagain.com`
- `movingagain.net`
- `movingagain.net.au`

Reasoning:

- same brand string
- `.com`, `.net`, and `.net.au` variants are parked
- the main Astro repo already maps to `movingagain.com.au`

### 3. `cartransport-au`

Keep as canonical property:

- primary domain: `cartransport.au`

Attach these additional domains:

- `cartransport.net.au`

Reasoning:

- same brand phrase
- `.net.au` variant is parked
- `cartransport.au` already has a matching Astro repo

Do not merge yet:

- `cartransportaus.com.au`
- `cartransportwithpersonalitems.com.au`

Reasoning:

- they are closely related, but they look like separate SEO/product surfaces rather than simple aliases

### 4. `movingcars-com-au`

Keep as canonical property:

- primary domain: `movingcars.com.au`

Attach these additional domains:

- `movingcars.net.au`

Reasoning:

- same brand string
- `.net.au` variant is parked

Note:

- there is local website work for Moving Cars outside the currently bootstrapped repo list, so this property likely deserves a repo binding in a later pass

### 5. `supercheapcartransport-com-au`

Keep as canonical property:

- primary domain: `supercheapcartransport.com.au`

Attach these additional domains:

- `supercheapcartransport.net.au`

Reasoning:

- same brand string
- `.net.au` variant is parked

### 6. `backloadingremovals-com-au`

Keep as canonical property:

- primary domain: `backloadingremovals.com.au`

Attach these additional domains:

- `backloadingremovals.com`

Reasoning:

- exact brand match across TLDs
- `.com` is parked and does not look like a separate operating property

## Moderate-Confidence Candidates

These look related, but they should stay separate until we confirm redirects, brand intent, or repo ownership.

### Car Transport Portfolio

Keep separate for now:

- `cartransportaus.com.au`
- `cartransportwithpersonalitems.com.au`
- `interstatecarcarriers.com.au`
- `transportnondrivablecars.com.au`
- `movemycar.com.au`
- `vehicle.net.au`

Reasoning:

- these likely belong to the same transport portfolio
- however, they read like distinct acquisition sites or product surfaces, not just aliases
- some may later roll under a portfolio-level parent concept, but not a shared `web_property`

### Moving / Removals Portfolio

Keep separate for now:

- `moving.com.au`
- `movinghome.com.au`
- `movinginterstate.com.au`
- `removalsinterstate.com.au`
- `interstate-removals.com.au`
- `interstate-removalists.net.au`
- `interstateremovalists.au`
- `interstateremovalists.net.au`
- `perthinterstateremovalists.com.au`
- `wemove.com.au`

Reasoning:

- similar market/theme, but not enough evidence they are aliases
- several look like SEO-focused microsites or legacy acquisition domains
- some are active DNS-hosted domains, which makes them less likely to be simple parked aliases

### Backloading Portfolio

Keep separate for now:

- `backload.net.au`
- `backloading-au.com.au`
- `backloading-services.com.au`
- `backloading.net.au`
- `backloadingremovalist.com.au`
- `backloads.net.au`
- `discountbackloading.com`
- `discountbackloading.com.au`

Reasoning:

- there is clear thematic overlap, but not enough proof they are all just aliases of one site
- many are parked, but the naming suggests multiple acquisition domains rather than one canonical brand

## Keep Standalone

These should remain single-domain properties unless new evidence appears:

- `again-com-au`
- `moving-insurance`
- `deftly-com-au`
- `jasonhill-com-au`
- `konradhill-com`
- `mandyhill-com-au`
- `olliehill-com-au`
- `pngchambers-com`
- `nfgseo-com-au`
- `jhmh-com-au`
- `redirection-com-au`
- `tinyurl-com-au`
- `synonymous-com-au`
- `rollover-com-au`
- `beauy-com-au`
- `acraustralia-com`

Reasoning:

- they appear to be standalone business/personal/utility properties
- there is no alias evidence from the current registry pass

## Recommended Execution Order

### Phase 1: Safe Alias Merges

Apply these first:

- `moveroo-website` <- `moveroo.au`, `moveroo.click`
- `moving-again` <- `movingagain.com`, `movingagain.net`, `movingagain.net.au`
- `cartransport-au` <- `cartransport.net.au`
- `movingcars-com-au` <- `movingcars.net.au`
- `supercheapcartransport-com-au` <- `supercheapcartransport.net.au`
- `backloadingremovals-com-au` <- `backloadingremovals.com`

### Phase 2: Repo And Analytics Enrichment

After the alias merges:

- attach repo metadata for `movingcars.com.au`
- review whether `ma-car-transport-astro` should map to a separate property tied to Matomo site `7`
- add more curated Matomo bindings where we have confidence

### Phase 3: Portfolio Review

Do a deliberate review of:

- transport acquisition sites
- moving/removals acquisition sites
- backloading acquisition sites

Only merge these when we confirm:

- they redirect to the same live site
- they share the same product surface
- they are intentionally just alternate brand domains

## Important Rule

For the second pass, only merge domains when the relationship is operationally obvious.

If a domain could reasonably be:

- its own marketing site
- a lead-generation microsite
- a retired but meaningful brand
- a future standalone property

then leave it as its own `web_property` until there is explicit confirmation.
