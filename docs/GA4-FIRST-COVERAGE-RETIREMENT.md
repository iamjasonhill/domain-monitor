# GA4-First Coverage Retirement Decision

Decision date: 2026-05-07

## Decision

Domain Monitor treats MM-Google, GA4, and current Search Console coverage as the active analytics truth.

Matomo and manual Search Console CSV imports are legacy archive/backfill inputs only. They must not be treated as required active coverage, active domain health, or first-look operator work.

## Active Coverage Complete Means

A managed website property is actively covered when Domain Monitor can show:

- repo/control coverage is present for the owning property
- GA4 coverage is synced from MM-Google or is explicitly in a provisioning state
- Search Console coverage is mapped and current through the MM-Google/Search Console coverage path
- baseline sync exists when the property needs a rebuild, cutover, or milestone comparison

Manual CSV evidence is optional supporting context. It is not required to mark active automation coverage complete.

## Historical Data Policy

Preserve existing Matomo and manual CSV records unless a later issue explicitly approves deletion or migration.

Historical Matomo/manual CSV data may still be used for:

- old Search Console recovery or backfill
- pre-retirement baseline evidence
- audit trails explaining prior operator decisions
- one-off comparison work where newer MM-Google evidence is missing

Historical data should not appear as active required work in first-look dashboards, active coverage queues, or Control Plane routing.

## Operator Routing Rules

Use these rules when adding or reviewing active coverage behavior:

- If a property lacks GA4, route it as a GA4/MM-Google readiness issue.
- If Search Console evidence is stale, missing, blocked, or failing, route it through the current Search Console coverage summary and blocker fields.
- If a baseline is missing for a rebuild/cutover decision, route it as a baseline-sync need, not a manual CSV need.
- If Matomo exists but GA4 is ready, do not show Matomo as a blocker.
- If manual CSV exists, preserve it as evidence; do not require it for active health.

## Legacy Refresh Scheduling

Normal production scheduling must not run Matomo install audits or Matomo-mapped weekly Search Console baseline refreshes.

The legacy commands remain available for deliberate archive/backfill work, but scheduled Matomo refreshes require the explicit `MATOMO_LEGACY_REFRESH_SCHEDULE_ENABLED=true` opt-in plus valid Matomo credentials.

## Retirement Stack

The retirement is intentionally staged:

1. Lock this decision in docs.
2. Stop scheduled Matomo refresh jobs.
3. Remove manual CSV from active automation coverage.
4. Retire Matomo/manual CSV UI from first-look surfaces.
5. Archive Matomo sources and promote GA4 where available without deleting history.
6. Update quality tests around the GA4-first model.
7. Neutralize Matomo-era naming in active contracts.
8. Verify production cleanup after the stack lands.
