# Domain Monitor as Brain Registry

## Purpose

Use `domain-monitor` as the web estate registry and monitoring backbone for the larger brain.

It should answer:

- what domains do we own?
- what live web properties exist?
- which repo powers each property?
- which analytics property tracks it?
- what is the current health and risk state?

It should not become the app that stores everything about content, design, software delivery, or business planning.

## Position in the Larger System

`domain-monitor` should be the source of truth for web asset inventory and web-facing operational metadata.

Suggested system boundaries:

- `domain-monitor`
  - domains
  - subdomains
  - DNS and SSL state
  - hosting and platform detection
  - web property inventory
  - repo links
  - analytics links
  - deployment references
  - alerts and checks
- `Matamo`
  - analytics collection
  - Matomo API access
  - reporting and trend summaries
- website repos
  - implementation
  - content
  - design code
  - build and deployment config
- brain layer
  - cross-system memory
  - priorities
  - summaries
  - routing work to Codex or humans

## What This Repo Should Own

### 1. Domain Asset Registry

This already exists and should stay here.

Examples:

- registrable domain
- registrar
- expiry
- nameservers
- DNS records
- compliance
- contacts
- renewal state

Current fit is strong in:

- `app/Models/Domain.php`
- `app/Models/DnsRecord.php`
- `app/Models/Subdomain.php`
- `app/Models/DomainContact.php`
- `app/Models/DomainComplianceCheck.php`

### 2. Web Property Registry

This is the missing layer.

A domain is not the same thing as a website. One property can have multiple domains, and one domain can redirect to another.

Add a first-class `web_properties` concept for things like:

- `moveroo-website`
- `moving-again`
- `cartransport-au`
- quote apps
- booking apps
- internal dashboards

Recommended fields:

- `id`
- `slug`
- `name`
- `property_type`
  - `marketing_site`
  - `programmatic_site`
  - `app`
  - `landing_page`
  - `redirect_only`
  - `internal_tool`
- `business_unit`
- `status`
  - `active`
  - `planned`
  - `paused`
  - `archived`
- `primary_domain_id`
- `production_url`
- `staging_url`
- `platform`
  - human-curated canonical platform value
- `target_platform`
- `owner`
- `priority`
- `notes`

### 3. Domain-to-Property Mapping

Add a join table so you can represent:

- one primary domain
- aliases
- redirects
- parked domains
- subdomain app endpoints

Suggested table: `web_property_domains`

Recommended fields:

- `id`
- `web_property_id`
- `domain_id`
- `usage_type`
  - `primary`
  - `redirect`
  - `alias`
  - `staging`
  - `cdn`
  - `tracking`
- `is_canonical`
- `notes`

### 4. Repository Links

The brain needs to know which codebase owns each property, but the repo itself should remain the source of implementation truth.

Suggested table: `property_repositories`

Recommended fields:

- `id`
- `web_property_id`
- `repo_name`
- `repo_provider`
  - `github`
  - `local_only`
- `repo_url`
- `local_path`
- `default_branch`
- `deployment_branch`
- `framework`
- `is_primary`
- `notes`

Examples for `local_path`:

- `/Users/jasonhill/Projects/websites/moveroo-website-astro`
- `/Users/jasonhill/Projects/websites/moving-again-astro`

### 5. Analytics Links

Do not move raw analytics into this repo. Store only bindings and identifiers.

Suggested table: `property_analytics_sources`

Recommended fields:

- `id`
- `web_property_id`
- `provider`
  - `matomo`
  - `ga4`
  - `search_console`
- `external_id`
- `external_name`
- `workspace_path`
- `is_primary`
- `status`
- `notes`

Examples:

- Matomo site ID
- GA4 property ID
- Search Console property URL

This lets `domain-monitor` answer:

- "which analytics source belongs to this site?"
- "which sites are missing analytics?"

Without becoming the analytics engine itself.

### 6. Deployments and Runtime References

The existing `deployments` concept is useful and should stay lightweight.

Keep storing:

- deploy timestamps
- git commit
- notes

Add only what helps linkage:

- deployment target
- deploy provider
- environment

Do not turn this into a full CI/CD system.

### 7. Monitoring and Alerts

This already fits the repo well.

Keep ownership here for:

- HTTP checks
- SSL checks
- DNS checks
- reputation checks
- security header checks
- broken link checks
- uptime incidents
- alert state

These are web estate health signals and belong in the registry layer.

## What Should Stay Outside This Repo

To avoid another muddled brain, keep these out:

- page copy drafts
- content calendars
- design files
- design system source of truth
- implementation details of Astro or app repos
- product backlog
- customer or CRM data
- raw analytics events
- full business reporting
- multi-step agent memory across unrelated domains

This repo should know where those things live, not store them.

## Recommended Source-of-Truth Rules

- domain ownership and domain health: `domain-monitor`
- website code and content implementation: website repo
- analytics data and reporting logic: `Matamo`
- cross-project priorities and decisions: brain layer

When two systems disagree, prefer the owner above.

## Minimal API Role

The current API already exposes domains and deployments.

Recommended expansion:

- `GET /api/web-properties`
- `GET /api/web-properties/{id}`
- `GET /api/web-properties/{id}/domains`
- `GET /api/web-properties/{id}/repositories`
- `GET /api/web-properties/{id}/analytics-sources`
- `GET /api/web-properties/{id}/health-summary`

Keep the API read-heavy at first.
Avoid building a huge mutation surface until the model settles.

## Brain Integration Contract

The larger brain should consume summaries from this repo, not raw internal tables.

Recommended summary shape per property:

- identity
  - name
  - type
  - status
- domains
  - primary
  - redirects
  - expiring soon
- repo
  - local path
  - framework
  - default branch
- analytics
  - Matomo site ID
  - coverage status
- health
  - latest HTTP
  - latest SSL
  - latest DNS
  - current alerts
- action hints
  - missing analytics
  - missing repo link
  - failing SSL
  - redirect ambiguity
  - parked but still active

This gives the brain enough to prioritize work without bloating this app.

## Suggested v1 Schema Additions

Add these tables first:

- `web_properties`
- `web_property_domains`
- `property_repositories`
- `property_analytics_sources`

Avoid adding dozens of new columns to `domains` for everything else.

## Suggested v1 Backfill Work

1. Create one `web_property` record per real website or app.
2. Link each property to its primary domain and redirect domains.
3. Link each property to its local repo path under `/Users/jasonhill/Projects/websites` when applicable.
4. Link each property to Matomo site IDs from the Matomo workspace.
5. Mark properties missing repo links, analytics, or health coverage.

That gives you a usable estate map very quickly.

## Proposed Naming Guidance

Keep the repo name `domain-monitor` if changing it is inconvenient, but think of its product role as:

- estate registry
- web property monitor
- domain and site inventory

That mental shift matters. It stops the repo from trying to become the whole brain.

## Recommended Next Implementation Slice

If this direction feels right, implement in this order:

1. add `web_properties`
2. add `web_property_domains`
3. add `property_repositories`
4. add `property_analytics_sources`
5. add simple read views and API endpoints
6. backfill current websites and Matomo links

That is enough to make `domain-monitor` the registry backbone for the web side of the brain without turning it into a monster.
