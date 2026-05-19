# Published Brand Surfaces Contract

Status: v1 pilot publisher
Issue: #208, updated by #213
Consumer: MoverooCombined issues #2083 and #2084

Domain Monitor publishes the read-only `domain-monitor-published-brand-surfaces` feed for MoverooCombined runtime consumption. The feed is pilot scoped and must not be treated as permission to import or render the full estate.

## Endpoint

`GET /api/published-brand-surfaces`

Authentication uses the existing Domain Monitor API Bearer token middleware. MoverooCombined can use `MOVEROO_REMOVALS_API_KEY` without write access to Domain Monitor internals.

Optional query parameters:

- `hostname`: returns a single hostname only when it is also present in the pilot allowlist.

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
- non-sensitive provenance: `provenance.approved_by`, `provenance.approved_at`, `provenance.source`, `provenance.change_ref`

## Guardrails

- Read-only export only.
- No DNS, cron, secret, billing, live website, or MoverooCombined changes.
- No redirect policy decisions from #210.
- Hostnames outside the pilot allowlist are intentionally omitted, even when present in Domain Monitor data.
- Consumers must validate again before rendering and keep local fallback behavior during the pilot.

## Fixtures

Example payload fixtures live in:

- `docs/fixtures/published-brand-surfaces/household-quote.json`
- `docs/fixtures/published-brand-surfaces/discountbackloading-quote.json`
