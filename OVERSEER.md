# OVERSEER

## Current Controller

`domain-monitor` is the canonical active controller for the domain-management department.

Supporting evidence confirmed during issue `#131`:

- this repo is a tracked git repository with origin `iamjasonhill/domain-monitor`
- repo documentation already describes `domain-monitor` as the source of truth for operational state
- active planning docs in this repo already treat the older Next.js project as a prior reference implementation

## Related Prototype Status

Related local folder reviewed:
`/Users/jasonhill/Projects/non-laravel-projects/domain-manage-project`

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

## Change Log

### 2026-04-22 07:35:43 AEST

- Trigger: issue `#131`
- What changed: created `OVERSEER.md` and documented `domain-monitor` as the canonical active controller for this department
- What was fixed: removed ambiguity between this repo and the local `domain-manage-project` prototype by classifying the prototype as operationally obsolete
- What remains open: confirm whether any Render setup notes are worth copying first, then archive or remove `/Users/jasonhill/Projects/non-laravel-projects/domain-manage-project`
