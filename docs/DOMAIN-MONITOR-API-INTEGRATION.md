# Domain Monitor API Integration

This document defines the stable read-only integration contract for external
services such as MM BRAIN, fleet-control, or future operator tools.

## Base URL

Production:

```text
https://monitor.again.com.au
```

## Authentication

All integration endpoints use bearer authentication:

```http
Authorization: Bearer <token>
```

Accepted environment variables on the Domain Monitor side:

- `BRAIN_API_KEY`
- `OPS_API_KEY`
- `FLEET_CONTROL_API_KEY`
- `MOVEROO_REMOVALS_API_KEY`

Recommended usage:

- MM BRAIN: `BRAIN_API_KEY`
- fleet-control: `FLEET_CONTROL_API_KEY`
- Moveroo Removals 2026: `MOVEROO_REMOVALS_API_KEY`
- other operator tooling: dedicated token where practical

## Discovery

Authenticated clients can inspect:

```text
GET /api/meta/integrations
```

This returns the supported feeds, contract versions, and auth metadata.

## Authoritative Feeds

### Properties

```text
GET /api/web-properties-summary
```

- `source_system`: `domain-monitor`
- `contract_version`: `1`

Purpose:
- authoritative property identity
- linked domains, repositories, analytics sources
- current health summary
- controller authority and deployment-readiness metadata for managed surfaces

Selected property fields now include:
- `site_key`
- `control_state`
- `execution_surface`
- `fleet_managed`
- `controller_repo`
- `controller_repo_url`
- `controller_local_path`
- `deployment_provider`
- `deployment_project_name`
- `deployment_project_id`
- `conversion_surfaces`
- `hostname_link_policy`
- `event_architecture`
- `analytics_sources`
- `analytics.ga4`

`analytics.ga4` is the Fleet-facing lookup block for website-domain GA4
rollouts. It is the active analytics source Fleet should use for current
launch and cutover decisions.

Selected `analytics.ga4` fields include:

- `property_slug`
- `domain`
- `site_key`
- `measurement_id`
- `property_id`
- `stream_id`
- `source_system`
- `status`
- `label`
- `provisioning_state`
- `switch_ready`
- `last_synced_at`
- `last_live_check_at`
- `detection.verdict`
- `detection.issue_id`

`hostname_link_policy` is the canonical hostname-level export for quote,
booking, contact, and customer-portal link expectations. It is derived from the
stored property targets plus known conversion surfaces, and uses per-slot
statuses:

- `required`
- `optional`
- `suppressed`
- `unknown`

Each hostname row includes:

- `hostname`
- `role`
- `property_kind`
- `controller_owner`
- `expected_links.household_quote`
- `expected_links.vehicle_quote`
- `expected_links.booking`
- `expected_links.contact`
- `expected_links.customer_portal`

### Runtime Analytics Contexts

```text
GET /api/runtime/analytics-contexts
```

- `source_system`: `domain-monitor-runtime-analytics`
- `contract_version`: `1`

Purpose:
- lightweight hostname-to-analytics resolution for shared runtimes
- avoids consuming the full property summary when the app only needs runtime
  analytics context

Selected context fields include:
- `hostname`
- `property_slug`
- `site_key`
- `journey_type`
- `runtime`
- `ga4`
- `event_contract`
- `conversion_surface`

### Normalized Issues

```text
GET /api/issues
GET /api/issues/{issue_id}
```

- `source_system`: `domain-monitor-issues`
- `contract_version`: `1`

Purpose:
- normalized open issue feed for cross-system remediation and rollout logic

Core fields:
- `issue_id`
- `property_slug`
- `property_name`
- `domain`
- `issue_class`
- `severity`
- `detector`
- `status`
- `detected_at`
- `rollout_scope`
- `control_id`
- `control_state`
- `execution_surface`
- `fleet_managed`
- `controller_repo`
- `controller_repo_url`
- `platform_profile`
- `host_profile`
- `control_profile`
- `evidence`

### Optional Operational Queue

```text
GET /api/dashboard/priority-queue
```

- `source_system`: `domain-monitor-priority-queue`
- `contract_version`: `2`

Purpose:
- enriched queue/context layer for operator views
- not required for basic property/issue synchronization

## Integration Guidance

- Treat Domain Monitor as the source of truth for property identity and detected
  issue records.
- Prefer `/api/web-properties-summary` plus `/api/issues` as the core read
  model.
- For shared runtimes that must resolve analytics by hostname, use the
  dedicated `/api/runtime/analytics-contexts` feed where possible.
- Use `/api/web-properties-summary` plus `conversion_surfaces` when broader
  property context is also needed.
- See
  [RUNTIME-ANALYTICS-RESOLUTION-CONTRACT.md](/Users/jasonhill/Projects/2026%20Projects/domain-monitor/docs/RUNTIME-ANALYTICS-RESOLUTION-CONTRACT.md)
  for the runtime-facing hostname to analytics resolution contract.
- Use `/api/dashboard/priority-queue` only as supplemental operational context.
- Fail loudly if `source_system` or `contract_version` do not match expected
  values.
- Do not scrape random local `.env` files from other repos to discover tokens.
  Provision the token explicitly for the consuming service.

## Google Search Console Collector

The official Google Search Console collector still uses its own runtime
credentials on the Domain Monitor side. Do not treat repository-local `.env`
files as the source of truth for these values.

### Preferred Source Of Truth

The preferred durable shared Search Console control-plane now lives in
MM-Google.

```text
Export contract: search-console-coverage-baseline-v1
Source system: mm-google
```

Domain Monitor should consume the MM-Google export and normalize it into its
own coverage and baseline tables.

Legacy collector storage remains useful only for recovery and backfill work:

- table `vsb3_plugin_setting`
  - `plugin_name = SearchConsoleIntegration`
  - settings:
    - `googleClientId`
    - `googleClientSecret`
    - `googleRedirectUri`
- table `vsb3_option`
  - `option_name = SearchConsoleIntegration.googleTokens`
  - contains the connected Google `refresh_token` and current `access_token`

Use the legacy collector side only when you need to recover or rotate an older
collector or inspect historic legacy imports. New Fleet analytics decisions
should use `analytics.ga4` from Domain Monitor.

### Domain Monitor Runtime Variables

Set these on the live Domain Monitor host:

- `GOOGLE_SEARCH_CONSOLE_API_BASE_URL`
- `GOOGLE_SEARCH_CONSOLE_INSPECTION_BASE_URL`
- `GOOGLE_SEARCH_CONSOLE_TOKEN_URL`
- `GOOGLE_SEARCH_CONSOLE_REFRESH_TOKEN`
- `GOOGLE_SEARCH_CONSOLE_CLIENT_ID`
- `GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET`
- `GOOGLE_SEARCH_CONSOLE_ANALYTICS_ROW_LIMIT`
- `GOOGLE_SEARCH_CONSOLE_INSPECTION_URL_LIMIT`

Do not commit the actual values into git.

### Verification Path

The safest live verification sequence is:

1. exchange the refresh token for an access token against
   `https://oauth2.googleapis.com/token`
2. verify the token can list Search Console properties from
   `https://www.googleapis.com/webmasters/v3/sites`
3. run the MM-Google export sync on one property:

```text
php artisan analytics:sync-mm-google-search-console-export <export-json-path> --dry-run
```

4. once the dry-run output looks correct, run the real import without
   `--dry-run`

### Operational Notes

- The collector should reuse the existing connected Google account rather than
  creating a separate ad hoc OAuth app when possible.
- Prefer the refresh-token flow over a pasted short-lived access token.
- If the MM-Google export is unavailable, treat that as an integration outage
  for active analytics decisions. Use legacy collector paths only long enough
  to recover the replacement contract.
  store.
