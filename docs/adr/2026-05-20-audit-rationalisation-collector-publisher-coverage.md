# ADR: Audit Rationalisation, Collector/Publisher Boundary, and Weekly Coverage

Date: 2026-05-20

## Status

Accepted

## Context

Domain Monitor has several audit paths that overlap in the operator surface:

- domain health checks
- monitoring lanes
- Fleet technical SEO audit runs
- imported Search Console, GA4, and MM-Google evidence

The richer Fleet technical SEO runtime audit can now detect rendered/mobile,
quote/contact, legacy route, and imported-evidence issues that older checks did
not cover. Without a clear model, those audit paths can create duplicate Control
Attention, unclear ownership, or repeated unknown results that never become
actionable work.

The primary rationalisation problem is duplicate operator noise and unclear
ownership. Runtime load matters, but it is secondary to making the evidence,
owner, and Control Attention path explicit.

## Decision

Domain Monitor will separate audit collectors from finding publishers.

Scheduled audit collectors store evidence and summaries. They do not update
Control Attention directly. Control Attention is updated only through
`MonitoringFinding` records under explicit promotion rules.

The Fleet technical SEO runtime program will use named audit profiles:

- `fleet_technical_seo_smoke`: daily low-cap smoke/regression evidence
  collection. Initial URL cap: 3.
- `fleet_technical_seo_deep`: weekly deeper evidence refresh. Initial URL cap:
  25.

Weekly coverage is required for every eligible site and every applicable audit,
but it does not need to happen as one large sweep. Scheduled batches must use
freshness-aware rotation so the most stale eligible properties are selected
first and the same first N properties are not audited repeatedly.

The existing `monitoring:run-lane fleet_astro_technical_seo` lane remains in the
first implementation slice as the publisher/reconciler. It reads latest
qualifying audit evidence and promotes only eligible failures into
`MonitoringFinding` records.

Durable `unknown` results should not loop forever. Scheduled audits should write
investigation candidates with evidence and dedupe keys. A later unknown-triage
slice will create GitHub issues only after thresholds such as repeated attempts
or 24 hours and confident owner classification.

## Consequences

Positive consequences:

- Control Attention has one deliberate publishing path.
- Fleet technical SEO evidence can become richer without increasing operator
  noise by default.
- Weekly coverage can be spread across batches to protect production load.
- Unknown results become an explicit investigation queue rather than silent
  recurring uncertainty.

Trade-offs:

- More scheduling and coverage state is required.
- Operators must understand the difference between collected evidence and
  promoted findings.
- Manual runs need an explicit promotion option if an operator wants immediate
  Control Attention updates from fresh evidence.

## Implementation Notes

The first implementation slice should add:

- named audit profiles for smoke and deep Fleet technical SEO runtime coverage
- freshness-aware property selection for scheduled estate batches
- evidence-only scheduled collector mode
- schedule entries for collector profiles
- a safe initial catch-up path for the deep profile within 12 hours of enablement

Automatic durable-unknown GitHub issue creation is intentionally out of scope
for the first slice.
