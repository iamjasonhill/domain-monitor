# OVERSEER

## Current Controller

`domain-monitor` is the canonical active controller for the domain-management department.

Supporting evidence confirmed during issue `#131`:

- this repo is a tracked git repository with origin `iamjasonhill/domain-monitor`
- repo documentation already describes `domain-monitor` as the source of truth for operational state
- active planning docs in this repo already treat the older Next.js project as a prior reference implementation

## Related Prototype Status

Related local folder reviewed:
`domain-manage-project` in the local non-Laravel projects area

Classification:
`domain-manage-project` is operationally obsolete as a controller and should not be treated as a second source of truth.

What it still is:

- a local, non-git Next.js and Render prototype
- a reference source for older implementation ideas
- something that may still contain a few notes worth preserving before archive or removal

Keep-worthy items named during review:

- Render deployment and CLI notes in `README.md`, `RENDER_CLI.md`, and `.render-setup.md`
- earlier Synergy and uptime-monitoring implementation ideas captured in the prototype README
- the fact that this prototype used a separate Next.js and Drizzle stack, which is now superseded here

What remains open:

- decide whether any of the Render-specific notes should be copied into this repo before cleanup
- after that review, archive or remove the local prototype folder

## Rename Direction

Issue `#133` confirmed that this system is a keeper and a pivotal operational dependency.

Current recommendation:

- keep `domain-monitor` as the product-facing and live contract name for now
- do not perform an immediate standards rename across the live estate
- if standards alignment is still desired, use an alias-first staged migration path before any canonical rename

Why this is the current call:

- the live hostname and API identity already use `https://monitor.again.com.au`
- this repo exposes stable `source_system` values such as `domain-monitor`, `domain-monitor-issues`, `domain-monitor-runtime-analytics`, and `domain-monitor-priority-queue`
- adjacent systems already consume those names directly, including `fleet-control` and `MM BRAIN`
- package, job, user-agent, artifact-path, and documentation references inside this repo still assume `domain-monitor` is the canonical operational name

## Change Log

### 2026-04-22 07:35:43 AEST

- Trigger: issue `#131`
- What changed: created `OVERSEER.md` and documented `domain-monitor` as the canonical active controller for this department
- What was fixed: removed ambiguity between this repo and the local `domain-manage-project` prototype by classifying the prototype as operationally obsolete
- What remains open: confirm whether any Render setup notes are worth copying first, then archive or remove the local `domain-manage-project` folder

### 2026-04-22 10:52:11 AEST

- Trigger: issue `#133`
- Product-name decision: keep `domain-monitor` as the current product-facing and live contract identity; do not rename it immediately to an `MM-` form
- If a future rename is approved, the blast radius includes at least:
  - local folder/workspace name
  - GitHub repo name and clone remote
  - deployment identifiers and any app-name references
  - live hostname and API clients currently using `https://monitor.again.com.au`
  - API contract values such as `service=domain-monitor` and `source_system=domain-monitor*`
  - user-agent strings, artifact paths, package metadata, and automation labels in this repo
  - downstream docs, scripts, tests, and contract validators in adjacent repos including `fleet-control`, `MM-Google`, and `MM BRAIN`
- Safe staged migration plan:
  1. decide the target standard name, but keep `domain-monitor` as the canonical live identifier during planning
  2. add an internal alias map and compatibility policy so both old and new names are explicitly recognized in docs and handoff planning
  3. update adjacent repos to accept dual identifiers without removing `domain-monitor` contract support
  4. add dual-read or alias coverage for repo references, service labels, and any machine-read contract validators
  5. introduce any hostname or API alias only after downstream consumers are verified against both names
  6. switch the preferred human-facing standard name in docs and workflow prompts
  7. only then evaluate whether the GitHub repo, local folder, and live hostname should actually be renamed
- Bossman verification checklist before considering the rename complete:
  - all current API consumers still work against the existing live contract
  - `fleet-control` sync and contract validation pass against the chosen alias strategy
  - `MM BRAIN` snapshot ingestion, validators, and operator views still read the same payloads successfully
  - `MM-Google` docs and handoff references are updated where they rely on the old canonical naming
  - GitHub clone, local workspace instructions, and deployment runbooks all point to the intended final identity
  - no hard-coded `domain-monitor` value that must remain canonical was changed accidentally
  - live smoke checks confirm the hostname, health routes, and integration metadata behave as expected
- Best immediate action: `alias-first`, which effectively means defer any canonical rename until dual-name compatibility is verified end to end
