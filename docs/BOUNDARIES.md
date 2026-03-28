# Domain Monitor Boundaries

`domain-monitor` is the source of truth for current operational state.

It should answer questions like:

- What platform is this domain or property on right now?
- What hosting provider is it on right now?
- Is it healthy right now?
- What DNS records and DNS config does it have right now?
- Is it parked, email-only, or set to auto-renew right now?
- Is analytics or a repository currently linked?

## domain-monitor Owns

- current domain state
- current web property state
- current platform
- current hosting provider
- current DNS config
- current DNS records
- current health checks
- current alerts
- current analytics bindings
- current repository bindings
- operational flags such as parked, email-only, and auto-renew

## domain-monitor Does Not Own

- business priority ranking
- long-term migration sequencing
- historical change logs
- operator memory and decision context
- recommendation strategy across systems

## Write Rules

- Update `domain-monitor` directly for operational facts.
- Keep `domain-monitor` focused on what is true now.
- Do not treat `MM BRAIN` as authoritative for current operational fields.

Examples:

- if a site moves from WordPress to Astro, update the current `platform` in `domain-monitor`
- if a domain moves from Synergy to Vercel, update the current `hosting_provider` in `domain-monitor`
- if a domain is parked, email-only, or no longer monitored, update that operational flag in `domain-monitor`

## Sync Direction

For now, sync should flow one way:

- `domain-monitor` -> `MM BRAIN`

`MM BRAIN` may interpret and annotate this data, but it should not silently overwrite operational truth back into `domain-monitor`.

If write-back is introduced later, it should be narrow and explicit, for example:

- mark parked
- attach a note
- set an approved priority field

Not broad bidirectional state sync.

## Migration Events

When a platform or host changes:

- update the current state in `domain-monitor`
- record the historical event in `MM BRAIN`

That keeps `domain-monitor` reliable for live operations while leaving history and reasoning to the Brain.
