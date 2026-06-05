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
- Whether an operational warning should become routed work.

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
  - property type and role,
  - current platform,
  - current host/provider,
  - controller/repository/deployment references as operational metadata,
  - stable external ids for related systems.
- Basic availability:
  - DNS,
  - HTTP status,
  - SSL expiry/validity,
  - uptime/current reachability.
- Operational findings:
  - domain, DNS, mail-plane, SSL, HTTP availability, registrar, lifecycle, and
    compliance findings,
  - finding state such as detected, still present, resolved, stale, suppressed,
    or not applicable,
  - stable finding ids that Bossman can reference.
- Source observations:
  - timestamped statements from Laravel legacy, Synergy, DNS, HTTP, SSL, mail,
    and compliance probes,
  - separated from accepted operational truth.
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

Domain Monitor may store which repo, deployment project, or controller currently
serves a property. Fleet owns whether that controller meets standards and what
remediation should happen.

Move to Bossman:

- cross-system priority queues,
  must-fix/should-fix language,
  material signals,
  draft actions,
  route-to-repo decisions,
  approvals,
  duplicate detection,
  execution history.

Domain Monitor may publish operational warnings and allowed action shapes, but
Bossman decides whether those warnings become routed actions.
Domain Monitor may keep its own operational findings ledger for domain-owned
checks, but not priority, approval, route-to-repo, or execution state.

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

The existing Laravel `WebProperty` concept is useful, but its current model and
API payload have accumulated responsibilities that v2 must split apart. Preserve
the identity and operational mapping facts; do not automatically copy analytics,
Search Console, Fleet technical SEO, conversion-surface policy, or priority-like
payloads into v2 just because they sit on the legacy WebProperty today.

Domain Monitor V2 `Web Property` should be limited to property identity and
operational mapping: type, status, role, canonical URL/host, linked domains,
platform, hosting, and stable external references to owner systems. It should
link to analytics, SEO, Fleet, and Bossman records rather than owning their
evidence or decisions.

Accepted operational truth must be separated from source observations and
suggested fact updates. If Laravel legacy, Synergy, live DNS, or another probe
disagree, v2 should expose the conflict instead of silently letting the newest
source overwrite truth.

## Suggested V2 Shape

The cleanest direction is a new lean system rather than continuing to expand the
current Laravel app surface.

Preferred approach:

- build Domain Monitor v2 as a clean agent-first system,
- create v2 in a separate repo from the legacy Laravel app,
- treat the current Laravel app as a legacy source/reference during transition,
- mine, compare, and migrate only the domain-monitoring facts v2 still owns,
- expose stable v2 contracts before building broad UI surfaces,
- retire old Laravel surfaces deliberately once their useful data and consumers
  have been accounted for.

Do not perform a large destructive refactor of the Laravel app first. Build the
clean v2 spine beside it, prove the contracts, then retire old surfaces
deliberately.

## Greenfield Stack Direction

Domain Monitor v2 should be designed as if starting fresh.

Preferred stack:

- Next.js App Router for the operator UI and API routes,
- TypeScript for shared contracts and domain rules,
- Supabase Postgres for the durable domain/property/source-of-truth schema,
- Supabase Auth or controlled API tokens for users and agents,
- Vercel for hosting the UI/API,
- scheduled workers for registrar, DNS, SSL, HTTP, mail-plane, and compliance
  probes.

The current Laravel application remains useful as:

- a legacy data source,
- an implementation reference for Synergy and monitoring behavior,
- a comparison point during migration,
- a fallback until v2 has proven coverage.

It is not the default target architecture for v2.

V2 should live in a separate repo. This keeps the clean architecture boundary
visible and avoids inheriting Laravel models, queues, UI routes, and historical
SEO/analytics assumptions by folder proximity.

## First Slice

Build the clean schema and import/mirror a narrow set of legacy Laravel facts
before adding fresh v2 probes.

First import/mirror:

- domains,
- web property identity and operational mapping,
- domain-to-property links,
- registrar/Synergy expiry and status,
- nameserver/DNS summary,
- mail-plane intended use,
- platform/hosting/controller references,
- current operational findings that Domain Monitor still owns.

Do not import/mirror:

- GSC/GA4/Bing/Matomo coverage,
- SEO opportunities,
- Lighthouse/browser/render/page checks,
- internal/broken/external-link checks,
- Fleet website standards,
- Bossman priority/routing/action state.

Then verify two read-only v2 endpoints:

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
- Write access starts as reviewed proposed changes, not silent direct mutation
  of operational truth.
- Every important entity should expose both human-facing state and an
  agent-facing action contract.
- Include freshness and source timestamps.
- Include confidence/reason fields where current truth is inferred.
- Include explicit `not_applicable` states for parked/email-only/no-mail domains.
- Avoid words like `must_fix`, `should_fix`, or `priority`; those belong in
  Bossman or specialist systems.
- Use stable source ids so Bossman, Fleet, SEO Champion, and MM-Google can link
  back without copying raw Domain Monitor data.
- For warnings or findings, include a stable issue code, evidence, confidence,
  freshness, owning system, allowed next actions, and whether the caller can
  observe, suggest, draft, or execute.
- Do not require agents to infer safe actions from raw fields alone.
- Do not create routed actions directly from Domain Monitor; publish facts and
  findings for Bossman to decide on.
- Let agents submit evidence-backed suggested fact updates, such as "appears
  parked" or "appears hosted on Vercel"; accepted updates become operational
  truth only after review or an approved Bossman workflow.

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

- What should the separate v2 repo be named and where should it live in
  `/Users/jasonhill/Projects`?
- Which Supabase project should host v2, or should a new one be created?
- Do published brand/conversion surfaces remain property truth here, or move to
  Fleet/Moveroo/Fleet Control?
- Which historical tables should be exported to SEO Champion before retirement?
- Which current external consumers still depend on old v1 endpoints?

## Acceptance Criteria For The First Slice

- v2 schema separates Domain, Web Property, Controller Reference, Operational
  Finding, Source Observation, and Suggested Fact Update concepts.
- legacy Laravel data is mirrored only for the approved domain-monitoring facts.
- v2 endpoints return useful estate truth for all current monitored domains.
- v2 endpoints omit SEO/analytics/priority bloat.
- current v1 endpoints are untouched during the proof.
- at least one consumer can read the v2 contract without needing legacy fields.
- docs list moved responsibilities and their new owners.
- no old data is deleted.
