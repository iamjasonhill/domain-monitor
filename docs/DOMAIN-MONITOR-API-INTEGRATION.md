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

Recommended usage:

- MM BRAIN: `BRAIN_API_KEY`
- fleet-control: `FLEET_CONTROL_API_KEY`
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
- Use `/api/dashboard/priority-queue` only as supplemental operational context.
- Fail loudly if `source_system` or `contract_version` do not match expected
  values.
- Do not scrape random local `.env` files from other repos to discover tokens.
  Provision the token explicitly for the consuming service.
