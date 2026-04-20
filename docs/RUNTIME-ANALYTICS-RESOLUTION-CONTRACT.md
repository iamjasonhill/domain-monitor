# Runtime Analytics Resolution Contract

## Purpose

Define the contract between:

- hosted `domain-monitor`
- the maintained Laravel quote runtime at
  `/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026`

This exists to answer one implementation question clearly:

- given an incoming hostname on the shared Laravel runtime, how should the app
  resolve the owning site, `GA4` identity, event contract, and rollout state?

The goal is to stop this logic from living in scattered env vars, duplicated
config files, or tribal knowledge.

## Source Of Truth

`domain-monitor` is the source of truth for:

- `web_property` identity
- `web_property.site_key`
- conversion surface to property mapping
- `GA4` bindings
- event contract assignment
- rollout state

The Laravel runtime is a consumer of that truth. It should not become a second
registry for site ownership or analytics identity.

## Core Rule

The shared runtime must resolve analytics by hostname and owning journey, not by
codebase-wide defaults.

That means:

1. request hostname identifies the conversion surface
2. conversion surface identifies the parent `web_property`
3. the parent `web_property` or surface binding identifies the `GA4` property
4. the event contract assignment identifies the expected event model

Shared runtime does not imply shared analytics identity.

## Preferred Read Model

The authoritative runtime read model is:

```text
GET /api/web-properties-summary
```

from:

```text
https://monitor.again.com.au
```

The properties summary already includes:

- `slug`
- `primary_domain`
- `analytics_sources`
- `event_architecture`
- `conversion_surfaces`

The runtime should consume the summary as the canonical source for hostname
resolution.

## Runtime Resolution Model

For an incoming request:

1. normalize the request hostname to lowercase with no trailing `.`
2. find a matching entry in `conversion_surfaces.hostname`
3. read the parent `web_property.slug`
4. resolve analytics binding:
   - if `conversion_surfaces.analytics.binding_mode = inherits_property`, use
     the property's primary `ga4` source
   - if a future direct binding is used, prefer the surface-level source
5. resolve event contract binding:
   - if `conversion_surfaces.event_contract.binding_mode = inherits_property`,
     use the property's primary event contract assignment
   - if a future direct binding is used, prefer the surface-level assignment
6. derive the runtime analytics context used by client and backend code

## Runtime Analytics Context

The runtime should treat this as the minimum resolved contract per hostname:

```json
{
  "hostname": "quotes.moveroo.com.au",
  "property_slug": "moveroo-com-au",
  "site_key": "moveroo",
  "journey_type": "mixed_quote",
  "ga4": {
    "provider": "ga4",
    "property_id": "457902172",
    "stream_id": "9677257871",
    "measurement_id": "G-9F3Y80LEQL",
    "bigquery_project": null
  },
  "event_contract": {
    "key": "moveroo-full-funnel-v1",
    "version": "v1",
    "rollout_status": "instrumented"
  },
  "conversion_surface": {
    "rollout_status": "instrumented"
  }
}
```

### Notes

- `site_key` is now a first-class `web_property` field in `domain-monitor`.
- The runtime should prefer the explicit property `site_key` over inferring from
  slug or analytics metadata.
- Transitional fallback from `ga4.provider_config.site_key` is acceptable only
  while older rows are being backfilled.

## Binding Rules

### `GA4`

Use one `GA4` property per parent site or brand journey.

For quote surfaces on the shared Laravel runtime, this means:

- apex site and quote subdomains for the same journey share one `GA4` property
- different parent sites on the same runtime do not collapse into one global
  `GA4` property

### Event Contracts

The runtime should treat the event contract as the expected event vocabulary for
that journey.

At minimum the runtime consumer should read:

- contract key
- version
- rollout status

This allows the app and the registry to agree on what "defined",
"instrumented", and "verified" mean.

## Consumer Guidance

### Preferred Integration Mode

Do not make a blocking external API request to `domain-monitor` on every web
request.

Preferred pattern:

1. fetch the authoritative summary on a scheduled sync or explicit refresh
2. cache or materialize the subset the runtime needs
3. resolve hostnames locally at request time against the cached snapshot

Good runtime outputs:

- in-memory cache
- database-backed local snapshot
- config artifact refreshed by command or deploy hook

Avoid:

- hand-maintained hostname to `measurement_id` maps in multiple files
- environment variables per hostname
- per-request live dependency on the monitor API

### Cache Freshness

Recommended sync behavior:

- refresh on deploy
- refresh on explicit operator command
- optionally refresh on a short background interval for long-lived servers

The runtime should prefer slightly stale but coherent cached data over a hard
dependency on a live network call.

## Failure Behavior

If hostname resolution fails:

- do not guess another site's analytics identity
- do not fall back to a random global `measurement_id`
- log the hostname and resolution failure clearly
- keep the page functional
- suppress site-specific tracking until the mapping is resolved

If the property resolves but no `ga4.measurement_id` exists:

- treat the journey as not yet configured for `GA4`
- keep the page functional
- do not initialize `GA4` with an unrelated fallback ID

If the event contract is missing:

- keep the page functional
- treat event-contract-dependent verification as incomplete

## Current Moveroo Scope

The immediate implementation for Moveroo may assume:

- parent property: `moveroo-com-au`
- `site_key`: `moveroo`
- `GA4 measurement_id`: `G-9F3Y80LEQL`

But it should still be structured behind the hostname-resolution contract above,
so the runtime can later support:

- `movingagain.com.au`
- `wemove.com.au`
- other quote surfaces on the same shared Laravel runtime

without being rewritten from scratch.

## Contract Gaps Still To Close

The current contract is usable, but there are still a few explicit gaps:

- `domain-monitor` should expose a lighter runtime-oriented feed if the full
  properties summary becomes too heavy
- the Laravel runtime still needs the consuming sync and resolver layer
- verification should remain paused until the runtime genuinely resolves the
  right `GA4` identity by hostname

## Related Documents

- [DOMAIN-MONITOR-API-INTEGRATION.md](/Users/jasonhill/Projects/2026%20Projects/domain-monitor/docs/DOMAIN-MONITOR-API-INTEGRATION.md)
- [BRAIN-REGISTRY-SPEC.md](/Users/jasonhill/Projects/2026%20Projects/domain-monitor/docs/BRAIN-REGISTRY-SPEC.md)
- [ANALYTICS-BOUNDARY-AND-ROLLUP-MODEL.md](/Users/jasonhill/Projects/2026%20Projects/MM%20BRAIN/docs/ANALYTICS-BOUNDARY-AND-ROLLUP-MODEL.md)
