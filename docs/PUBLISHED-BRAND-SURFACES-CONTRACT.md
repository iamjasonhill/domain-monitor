# Published Brand Surfaces Contract

Status: v1 pilot publisher
Issue: #208, updated by #213, #215, #217, and #219
Consumer: MoverooCombined issues #2083 and #2084

Domain Monitor publishes the read-only `domain-monitor-published-brand-surfaces` feed for MoverooCombined runtime consumption. The feed is pilot scoped and must not be treated as permission to import or render the full estate.

## Endpoint

`GET /api/published-brand-surfaces`

Authentication uses the existing Domain Monitor API Bearer token middleware. MoverooCombined can use `MOVEROO_REMOVALS_API_KEY` without write access to Domain Monitor internals.

Optional query parameters:

- `hostname`: returns a single hostname only when it is also present in the pilot allowlist.

Draft/review endpoint:

`GET /api/published-brand-surface-drafts`

This read-only feed exposes app-host to source-marketing-domain proposals, candidate brand/style facts, evidence, confidence, and approval status. Draft or `needs_review` proposals are review evidence only and must not be treated as published MoverooCombined runtime truth.

## Envelope

Required fields:

- `source_system`: `domain-monitor-published-brand-surfaces`
- `contract_version`: `1`
- `snapshot_id`: immutable export id for idempotency
- `published_at`: ISO-8601 publication timestamp
- `generated_by`: publisher identity
- `pilot.host_allowlist`: explicit pilot hostname allowlist
- `surfaces`: published surface objects

## Pilot Scope

The publisher only emits hostnames listed in `domain_monitor.published_brand_surfaces.pilot_host_allowlist`.

Current pilot hostnames:

- Household quote: `quotes.moveroo.com.au`
- Discount Backloading quote portal: `mymoveportal.discountbackloading.com.au`
- Interstate Removals quote host: `quotes.interstate-removals.com.au`
- Moving Cars vehicle quote host: `quoting.movingcars.com.au`
- Super Cheap Car Transport portal quote host: `portal.supercheapcartransport.com.au`
- Backloading Services quote host: `mymovehub.backloading-services.com.au`
- Backloading Removals quote host: `mymovehub.backloadingremovals.com.au`
- Move My Car quote host: `portal.movemycar.com.au`
- WeMove quote host: `quotes.wemove.com.au`
- Backloading Australia quote host: `quoting.backloading-au.com.au`
- Car Transport quote host: `quoting.cartransport.au`
- Car Transport Aus quote host: `quoting.cartransportaus.com.au`
- Car Transport With Personal Items quote host: `quoting.cartransportwithpersonalitems.com.au`
- Interstate Car Transport quote host: `quoting.interstate-car-transport.com.au`
- Interstate Car Carriers quote host: `quoting.interstatecarcarriers.com.au`
- Perth Interstate Removalists quote host: `quoting.perthinterstateremovalists.com.au`
- Removals Interstate quote host: `quoting.removalsinterstate.com.au`
- Transport Non Drivable Cars quote host: `quoting.transportnondrivablecars.com.au`
- Moving Again removalist quote host: `removalistquotes.movingagain.com.au`
- Moveroo removalists quote host: `removalists.moveroo.com.au`
- Interstate Removals removal portal: `removalportal.interstate-removals.com.au`
- Backloading Services removal quotes host: `removalquotes.backloading-services.com.au`
- Alliance Removals moving quote host: `moving.allianceremovals.com.au`
- Mover quote and booking host: `quoteandbook.mover.com.au`

Second-pilot host classifications:

- `moveroo.com.au`: marketing apex, not a MoverooCombined app-served brand surface in this pilot.
- `discountbackloading.com.au`: marketing apex, not a MoverooCombined app-served brand surface in this pilot.
- `quotes.interstate-removals.com.au`: app-served mixed quote surface and published.
- `quoting.movingcars.com.au`: app-served vehicle quote surface and published.
- `portal.supercheapcartransport.com.au`: app-served vehicle quote surface and published.

Third-pilot host classifications:

- `mymovehub.backloading-services.com.au`: app-served mixed quote surface and published.
- `mymovehub.backloadingremovals.com.au`: app-served mixed quote surface and published.
- `portal.movemycar.com.au`: app-served vehicle quote surface and published.
- `quotes.wemove.com.au`: app-served mixed quote surface and published.
- `quoting.backloading-au.com.au`: app-served mixed quote surface and published.
- `quoting.cartransport.au`: app-served vehicle quote surface and published.
- `quoting.cartransportaus.com.au`: app-served vehicle quote surface and published.
- `quoting.cartransportwithpersonalitems.com.au`: app-served vehicle quote surface and published.
- `quoting.interstate-car-transport.com.au`: app-served vehicle quote surface and published.
- `quoting.interstatecarcarriers.com.au`: app-served vehicle quote surface and published.
- `quoting.perthinterstateremovalists.com.au`: app-served mixed quote surface and published.
- `quoting.removalsinterstate.com.au`: app-served mixed quote surface and published.
- `quoting.transportnondrivablecars.com.au`: app-served vehicle quote surface and published.
- `removalistquotes.movingagain.com.au`: app-served mixed quote surface and published.
- `removalists.moveroo.com.au`: app-served mixed quote surface and published.
- `removalportal.interstate-removals.com.au`: app-served mixed quote surface and published.
- `removalquotes.backloading-services.com.au`: app-served mixed quote surface and published.
- `moving.allianceremovals.com.au`: app-served mixed quote surface and published.
- `cartransport.movingagain.com.au`: marketing website candidate, not confirmed as a MoverooCombined app-served runtime surface for this pilot.
- `perth.moveroo.com.au`: retired and superseded by `quoting.perthinterstateremovalists.com.au`.
- `quotes.interstateremovalists.net.au`: retired/decommissioned hostname recorded as an expected miss.
- `quoting.mover.com.au`: retired and superseded by `quoteandbook.mover.com.au`.
- `quoting.vehicle.net.au`: legacy vehicle quoting, not the active vehicle pilot.
- `removalist.backloadingremovals.com.au`: legacy portal host recorded as an expected miss.
- `interstate-removals.moveroo.com.au`: legacy Moveroo subdomain, not confirmed as a current app-served brand surface for this controlled batch.

Final runtime closeout classification:

- `quoteandbook.mover.com.au`: app-served mixed quote surface and published.
- The remaining 78 runtime-only hostnames from issue #219 are intentionally classified out in `domain_monitor.published_brand_surfaces.classified_runtime_hostnames` and mirrored in `docs/fixtures/published-brand-surfaces/final-runtime-closeout.json`.
- Classifications cover marketing apexes, aliases, retired Moveroo subdomains, legacy vehicle/runtime hosts, personal/non-business hosts, redirect utilities, and non-MoverooCombined hosts.
- This closes the Domain Monitor publisher side of the runtime-only list without changing MoverooCombined code, live websites, DNS, redirects, cron, secrets, or deployment settings.

`quoting.vehicle.net.au` is legacy quoting and is not the active #2084 pilot test host. Discount Backloading is the selected pilot because Domain Monitor production records the current property-specific quote portal and target links:

- production URL: `https://discountbackloading.com.au`
- target quote subdomain: `https://mymoveportal.discountbackloading.com.au/`
- household quote target: `https://mymoveportal.discountbackloading.com.au/quote/household`
- vehicle quote target: `https://mymoveportal.discountbackloading.com.au/quote/vehicle`
- booking target: `https://mymoveportal.discountbackloading.com.au/booking/create`
- contact target: `https://mymoveportal.discountbackloading.com.au/contact`

## Surface Shape

Each surface includes:

- canonical hostname identity: `hostname`, `canonical_hostname`, `linked_hostnames`, `property_slug`, `surface_slug`, `status`, `surface_type`, `canonical_role`, `updated_at`
- brand identity: `brand.display_name`, `brand.brand_key`, legal/tagline/mark/logo fields when available
- runtime copy and shell metadata: `copy`, `navigation`, `behavior`, `links`
- theme tokens: `theme.theme_key`, `theme.mode`, `theme.fonts`, `theme.colors`, `theme.exact_tokens`
- public contact links: `contact`
- parent/canonical relationship: `owning_marketing_domain`, `controller_owner`, `controller_repo`
- ownership metadata: `ownership.published_truth_owner`, `ownership.runtime_renderer_owner`, `ownership.site_repo_owner`, `ownership.portfolio_routing_owner`
- telemetry linkage: `analytics.status`, `analytics.runtime_context_key`, `analytics.property_slug`, `analytics.site_key`, `analytics.journey_type`, optional GA4 and event-contract mirrors
- approved brand-style source metadata: optional `brand_style_source` is present only when a draft proposal has been explicitly approved
- non-sensitive provenance: `provenance.approved_by`, `provenance.approved_at`, `provenance.source`, `provenance.change_ref`

## Guardrails

- Read-only export only.
- No DNS, cron, secret, billing, live website, or MoverooCombined changes.
- No redirect policy decisions from #210.
- Hostnames outside the pilot allowlist are intentionally omitted, even when present in Domain Monitor data.
- Brand-style drafts are never published merely because extraction or reviewed metadata exists; only `approval_status=approved` proposals can annotate the published feed.
- Consumers must validate again before rendering and keep local fallback behavior during the pilot.

## Fixtures

Example payload fixtures live in:

- `docs/fixtures/published-brand-surfaces/household-quote.json`
- `docs/fixtures/published-brand-surfaces/discountbackloading-quote.json`
- `docs/fixtures/published-brand-surfaces/second-pilot-batch.json`
- `docs/fixtures/published-brand-surfaces/third-pilot-batch.json`
- `docs/fixtures/published-brand-surfaces/final-runtime-closeout.json`
