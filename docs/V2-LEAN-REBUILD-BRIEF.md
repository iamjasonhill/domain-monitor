# Domain Monitor V2 Lean Rebuild Brief

Date: 2026-06-05
Status: planning brief

## Purpose

Domain Monitor v2 should return to being the operational truth system for
domains, DNS, registrar state, mail-plane readiness, hosting, platform mapping,
and basic availability.

It should not remain a broad SEO, analytics, Lighthouse, Fleet audit, or
priority-routing app. Those functions now have better owners.

## Product Boundary

Domain Monitor answers:

- What domains do we control?
- Who is the registrar and what is the current registrar/Synergy state?
- When does the domain expire and is renewal/action required?
- What nameservers and DNS records are currently present?
- Is the domain active, parked, email-only, no-mail, app-only, or retired?
- Is the mail plane configured correctly for its intended use?
- Which canonical web property or subdomain does this domain belong to?
- What platform and hosting provider currently serve the property?
- Is the domain/property basically reachable through DNS, HTTP, SSL, and uptime checks?
- What `.au` eligibility/compliance facts or documents are attached?

Domain Monitor does not answer:

- Which page should be improved for SEO?
- Which GSC opportunity should be worked next?
- Whether content, headings, meta, Lighthouse, agent-readiness, or internal links are good.
- Which cross-system issue should be routed first.
- Which website repo should implement a content/technical SEO fix.
- Long-term business priority or recommendation strategy.

## Keep In Domain Monitor V2

- Domain inventory and lifecycle:
  - domain name, status, active/parked/email-only/no-mail/retired flags,
  - expiry, auto-renew, transfer lock, renewal risk.
- Registrar/Synergy truth:
  - registrar id/status,
  - nameservers,
  - DNS records,
  - registrant/contact/eligibility metadata where safe,
  - `.au` compliance records and COR/archive references.
- DNS and mail-plane readiness:
  - MX/SPF/DKIM/DMARC/provider-verification state,
  - no-mail and email-only policy,
  - provider-specific setup requirements.
- Property and host mapping:
  - web properties,
  - linked domains/subdomains,
  - canonical production URL,
  - current platform,
  - current host/provider,
  - controller/repository references as operational metadata.
- Basic availability:
  - DNS,
  - HTTP status,
  - SSL expiry/validity,
  - uptime/current reachability.
- Narrow APIs:
  - domain list/detail,
  - web property summary/detail,
  - mail-plane summary,
  - compliance summary,
  - basic health summary.

## Move Out Of Domain Monitor V2

Move to SEO Champion:

- Search Console imports, issue snapshots, URL inspection, live rechecks,
  baselines, query/page ledgers, SEO opportunities, page checks, content
  quality, Lighthouse, browser render, internal links, broken links, external
  link inventory, agent readiness, metadata uniqueness, and website standards.

Move to MM-Google or SEO Champion:

- GA4, GSC, Bing, analytics freshness, legacy Matomo coverage, manual CSV
  backlog, and analytics-source coverage.

Move to Fleet:

- website standards, guardrails, technical implementation policy, Astro/WordPress
  standards, approved external-link policy, repo expectations, and remediation
  playbooks.

Move to Bossman:

- cross-system priority queues,
  must-fix/should-fix language,
  material signals,
  draft actions,
  route-to-repo decisions,
  approvals,
  duplicate detection,
  execution history.

Archive or keep read-only until migrated:

- historical Matomo/manual CSV tables,
  old SEO baseline workflow artifacts,
  legacy Fleet technical SEO runs,
  old Control/Attention compatibility shims.

## Data Preservation Rule

Do not delete old tables, commands, or history simply because they are no longer
v2 responsibilities.

Before removing anything:

1. classify it as `keep`, `move`, `archive`, or `delete candidate`,
2. identify the future owner,
3. export or migrate any evidence worth preserving,
4. document the retirement,
5. verify no production consumer still depends on it.

## Suggested V2 Shape

The cleanest direction is a new lean implementation path rather than continuing
to expand the current Laravel app surface.

Preferred approach:

- keep the current repo as the truth source during transition,
- add a `v2` contract and narrow API layer first,
- prove the narrowed endpoints from current data,
- then decide whether the final v2 app stays in this Laravel repo or becomes a
  clean new app backed by a migrated schema.

Do not perform a large destructive refactor first. Build the small v2 contract
over known data, then retire old surfaces deliberately.

## First Slice

Build and verify two read-only v2 endpoints:

- `GET /api/v2/domains`
- `GET /api/v2/web-properties`

They should return only:

- identity,
- lifecycle/status,
- registrar/Synergy state,
- expiry/renewal,
- nameservers/DNS summary,
- mail-plane summary,
- canonical host/property mapping,
- platform/hosting,
- basic HTTP/SSL/DNS/uptime state,
- compliance summary,
- source timestamps and confidence.

They should not return:

- GSC/GA4/Bing/Matomo coverage,
- SEO opportunities,
- Lighthouse/browser/render/page checks,
- internal/broken/external-link checks,
- priority/routing/actionability language,
- Bossman draft/action state.

## API Contract Principles

- Agent-friendly from day one.
- Read-only first.
- Include freshness and source timestamps.
- Include confidence/reason fields where current truth is inferred.
- Include explicit `not_applicable` states for parked/email-only/no-mail domains.
- Avoid words like `must_fix`, `should_fix`, or `priority`; those belong in
  Bossman or specialist systems.
- Use stable source ids so Bossman, Fleet, SEO Champion, and MM-Google can link
  back without copying raw Domain Monitor data.

## UI Direction

The UI should become quiet and operational:

- domain estate overview,
- expiring soon,
- registrar/Synergy status,
- DNS/mail-plane readiness,
- properties and hosting,
- `.au` compliance,
- basic availability.

Avoid rebuilding SEO dashboards, analytics dashboards, technical SEO pages, or
cross-system priority queues inside Domain Monitor.

## Open Decisions

- Should final v2 remain Laravel, or should we create a clean app once the v2
  API contract is proven?
- Does `MonitoringFinding` remain as a local outbox/event table, or move fully
  to Bossman?
- Do published brand/conversion surfaces remain property truth here, or move to
  Fleet/Moveroo/Fleet Control?
- Which historical tables should be exported to SEO Champion before retirement?
- Which current external consumers still depend on old v1 endpoints?

## Acceptance Criteria For The First Slice

- v2 endpoints return useful estate truth for all current monitored domains.
- v2 endpoints omit SEO/analytics/priority bloat.
- current v1 endpoints are untouched during the proof.
- at least one consumer can read the v2 contract without needing legacy fields.
- docs list moved responsibilities and their new owners.
- no old data is deleted.

