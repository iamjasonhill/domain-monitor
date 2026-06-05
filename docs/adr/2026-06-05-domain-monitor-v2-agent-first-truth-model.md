# ADR: Domain Monitor V2 Agent-First Truth Model

Date: 2026-06-05

## Status

Accepted

## Context

The current Laravel Domain Monitor grew beyond its original domain-monitoring
purpose. It now contains domain and property truth, but also SEO evidence,
analytics coverage, Fleet technical SEO audits, conversion-link policy,
priority-like queues, and Control/Bossman-facing action concepts.

That overlap makes the system harder for humans and agents to reason about:

- SEO Champion now owns SEO, page, GSC, GA4, Bing, SERP, and monitoring
  evidence.
- Fleet owns website standards, repo guardrails, and remediation playbooks.
- Bossman owns cross-system attention, routing, approval, duplicate detection,
  and execution history.
- Domain Monitor still needs to own domain, DNS, registrar, mail-plane,
  hosting/platform, lifecycle, availability, and compliance truth.

If v2 simply refactors the Laravel app, it is likely to inherit the same blurred
responsibilities. If it starts clean without preserving useful legacy evidence,
it risks losing operational truth that already exists.

## Decision

Domain Monitor v2 will be a clean agent-first system, not a Laravel v1 feature
branch or broad refactor.

The current Laravel application is a legacy source, implementation reference,
comparison point, and temporary fallback during migration. It is not the default
target architecture for v2.

Domain Monitor v2 will live in a separate repo from the legacy Laravel
application.

Domain Monitor v2 owns operational truth for:

- domains,
- web property identity and operational mapping,
- registrar/Synergy state,
- DNS and nameservers,
- mail-plane readiness,
- platform and hosting mapping,
- controller/repo/deployment references as operational metadata,
- basic DNS/HTTP/SSL/uptime availability,
- `.au` compliance,
- domain-owned operational findings.

Domain Monitor v2 does not own:

- SEO opportunities or page checks,
- analytics evidence,
- website standards,
- repo remediation rules,
- priority queues,
- routed actions,
- approvals,
- execution state.

Every important v2 entity should expose both human-facing state and an
agent-facing action contract. Warnings and findings must include stable codes,
evidence, confidence, freshness, owning system, allowed next actions, and
whether an agent may observe, suggest, draft, or execute.

Bossman decides whether Domain Monitor findings become routed work. Domain
Monitor publishes operational facts and warnings; it does not create routed
actions directly.

Domain Monitor v2 separates:

- accepted operational truth,
- source observations,
- suggested fact updates.

Agents may submit evidence-backed suggested fact updates, but they should not
silently mutate accepted operational truth. Truth changes through review or an
approved Bossman workflow.

## Options Considered

- Continue expanding the Laravel app: plausible because it already contains
  Synergy integration, domain data, and monitoring history. Rejected because it
  keeps the existing responsibility blur and makes it too easy to preserve SEO,
  analytics, and priority bloat.
- Build a clean v2 and ignore Laravel history: plausible because it gives the
  cleanest architecture. Rejected because useful domain, property, registrar,
  DNS, and monitoring evidence already exists and should not be lost.
- Build a clean v2 that mines Laravel as legacy source material: accepted
  because it preserves operational truth while preventing old bloat from setting
  the v2 model.
- Build v2 inside the Laravel repo: rejected because shared folder structure,
  dependencies, models, queues, and UI routes would make the old architecture
  too easy to carry forward by accident.

## Consequences

Positive consequences:

- Domain Monitor has a crisp responsibility boundary.
- Agents can query domain truth without scraping UI or inferring unsafe actions.
- Bossman remains the single cross-system action and approval layer.
- SEO Champion, Fleet, and Domain Monitor can link to each other through stable
  IDs without copying each other's decision loops.
- Conflicting source data can be exposed safely without silently overwriting
  accepted truth.

Trade-offs:

- Migration needs a deliberate keep/move/archive pass instead of a direct schema
  copy.
- v2 needs source-observation and suggested-update concepts earlier than a
  simple CRUD app would.
- Some legacy Laravel payloads must be split apart before they can be trusted in
  v2.

Follow-up:

- Build the first slice as schema plus narrow legacy mirror/import before fresh
  probes.
- Verify read-only `GET /api/v2/domains` and `GET /api/v2/web-properties`
  against legacy Laravel data.
- Create the v2 app in a separate repo and document the legacy Laravel repo as
  the migration source/reference/fallback.
- Keep SEO, analytics, Fleet standards, and Bossman action state out of the v2
  import unless a later ADR changes the boundary.
