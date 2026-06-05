# Domain Monitor

## Purpose

Domain Monitor owns the operational truth for controlled domains and web
properties: registrar state, lifecycle, DNS, mail-plane readiness, platform and
hosting mapping, basic availability, and compliance facts.

## Canonical Terms

### Domain Monitor V2

- Definition: A clean agent-first system for domain and web-property operational
  truth.
- Distinguish from: The current Laravel Domain Monitor, which is a legacy source
  and reference during migration.
- Example: V2 can answer which registrar, nameservers, mail plane, host, and
  canonical property belong to `example.com.au`.

### Domain

- Definition: A registrable domain or subdomain we control or intentionally
  track.
- Distinguish from: Web Property, which is the site or app experience served by
  one or more domains.
- Example: `movingcars.com.au` is a domain.

### Web Property

- Definition: A canonical website, app, parked surface, quoting surface, or
  mail-only property that one or more domains point at.
- Distinguish from: Domain, which is the DNS/registrar identity.
- Lifecycle or states: active, parked, email-only, no-mail, app-only, quoting
  surface, retired.
- Example: A main marketing site and a quoting subdomain are separate web
  properties even when they share a brand.

### Legacy Web Property

- Definition: The current Laravel `WebProperty` model and API payload.
- Distinguish from: Domain Monitor V2 Web Property, which should keep only the
  operational identity and mapping facts Domain Monitor owns.
- Example: The legacy payload includes analytics, Search Console, Fleet
  technical SEO, conversion surfaces, and priority-like fields that v2 should
  not automatically inherit.

### Operational Truth

- Definition: Current factual state about ownership, registrar, DNS, mail,
  hosting, platform, compliance, and availability.
- Distinguish from: SEO opportunity, business priority, implementation policy,
  or cross-system routing.
- Example: "The domain uses Synergy nameservers and expires on this date" is
  operational truth.

### Controller Reference

- Definition: A reference to the repo, deployment project, or operational system
  that currently controls a Web Property.
- Distinguish from: Fleet standards or remediation rules, which define whether
  that controller is good enough and what should change.
- Example: "This property is deployed by Vercel project X from GitHub repo Y" is
  a Controller Reference.

### Agent Action Contract

- Definition: Machine-readable guidance describing what an agent may do with a
  fact, warning, or entity.
- Distinguish from: Raw data, which may be observable but not safely actionable.
- Lifecycle or states: observe, suggest, draft, execute.
- Example: A DNS warning may allow an agent to suggest or draft a Bossman action,
  but not execute a DNS change.

### Suggested Fact Update

- Definition: An evidence-backed proposed change to Domain Monitor operational
  truth.
- Distinguish from: A committed operational fact, which has already been accepted
  into Domain Monitor truth.
- Lifecycle or states: proposed, accepted, rejected, superseded.
- Example: An agent proposes that a domain appears parked, with DNS and HTTP
  evidence, but Domain Monitor does not treat that as truth until accepted.

### Source Observation

- Definition: A timestamped statement from a source system or probe about a
  domain or web property.
- Distinguish from: Operational Truth, which is the accepted current fact.
- Example: Synergy reports one nameserver set, live DNS reports another, and the
  legacy Laravel app reports an older value.

### Operational Finding

- Definition: A current or historical domain-monitoring finding about DNS,
  mail-plane, SSL, HTTP availability, registrar, lifecycle, or compliance state.
- Distinguish from: A Bossman action, which owns routing, approval, and
  execution.
- Lifecycle or states: detected, still_present, resolved, stale, suppressed,
  not_applicable.
- Example: "DMARC policy is missing for a mail-enabled domain" is an Operational
  Finding. "Ask Jason to approve a Synergy DNS change" is a Bossman action.

## Rules and Invariants

- Domain Monitor V2 is a clean system; the Laravel app is legacy/source material,
  not the default v2 architecture.
- Domain Monitor V2 should live in a separate repo from the legacy Laravel app.
- Domain Monitor owns domain and web-property operational truth.
- Domain Monitor publishes operational facts and warnings; Bossman decides
  whether they become routed work.
- Domain Monitor V2 starts with reviewed fact updates. Agents may propose
  changes to operational truth, but should not silently mutate truth.
- Domain Monitor V2 separates accepted Operational Truth, Source Observations,
  and Suggested Fact Updates.
- Web Property remains first-class in v2, but the legacy Laravel WebProperty
  shape must be split apart during migration.
- Domain Monitor V2 Web Property is limited to identity and operational mapping:
  property type, status, canonical URL/host, linked domains, platform, hosting,
  role, and stable external IDs.
- Domain Monitor may store repo/deployment/controller references as operational
  mapping, but Fleet owns standards and remediation rules for those controllers.
- Domain Monitor V2 keeps operational findings for domain-owned checks only;
  Bossman stores routed action state.
- SEO Champion owns SEO/page/search evidence and improvement opportunities.
- Fleet owns website standards, guardrails, and remediation playbooks.
- Bossman owns cross-system attention, routing, approval, and action history.
- APIs must be useful to agents from day one.
- Agents must not infer unsafe actions from raw fields; warnings need stable
  codes, evidence, confidence, freshness, owning system, and allowed actions.
- Domain Monitor may expose operational facts to other systems, but should not
  duplicate their decision loops.

## Boundaries

- In scope: domain lifecycle, registrar/Synergy truth, DNS, mail plane, hosting,
  platform mapping, basic availability, `.au` compliance, and agent-readable
  operational contracts.
- Out of scope: SEO opportunities, page checks, content quality, analytics
  dashboards, website standards, repo remediation, and priority routing.

## Key Scenarios

- SEO Champion asks Domain Monitor whether a URL belongs to a real website,
  quoting surface, parked domain, or retired property before treating it as SEO
  inventory.
- Domain Monitor v2 imports the identity and mapping parts of a legacy
  WebProperty, but leaves its Search Console, analytics, technical SEO, and
  priority-style data with the systems that now own those areas.
- The first v2 migration mirrors narrow legacy facts before adding fresh probes,
  so the schema can be verified against Laravel v1 without copying old bloat.
- The separate v2 repo should document the legacy Laravel repo as its migration
  source, reference implementation, and fallback until v2 is proven.
- If Laravel, Synergy, and live DNS disagree, Domain Monitor records the
  disagreement as Source Observations and uses a reviewed Suggested Fact Update
  to change accepted Operational Truth.
- Domain Monitor records that a property is served by a Vercel project and
  controlled by a GitHub repo; Fleet decides whether that repo satisfies Astro,
  deployment, or website-standard policy.
- Domain Monitor stores that an SSL certificate expires soon and whether the
  finding is still present; Bossman may reference that finding if it creates a
  routed action.
- Bossman asks Domain Monitor for current operational warnings, but Bossman
  decides whether the warning becomes routed work.
- Domain Monitor reports that a domain's MX state does not match intended mail
  usage; Bossman decides whether to create an action and who owns it.
- An agent detects that a domain appears to have moved to Vercel; it submits a
  Suggested Fact Update with evidence and confidence rather than directly
  changing the platform field.
- A domain moves platform or hosting provider; Domain Monitor records the
  current operational truth while historical reasoning lives elsewhere.
