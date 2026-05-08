# Mail-Plane DNS Tracking

Domain Monitor treats mail-plane domains as DNS/provider-readiness assets, not website properties.

Mail planes cover agent-first or application mail subdomains such as `notify.again.com.au`, `ops.again.com.au`, `tx.again.com.au`, or future agent-native mail experiments. They can be parked or email-only for website checks while still carrying active DNS requirements for SPF, DKIM, DMARC, MX, return-path, bounce, and provider verification.

## Domain Fields

Mail-plane tracking lives on `domains`:

- `mail_plane_type`: one of `agent_notifications`, `work_email_intake`, `transactional_app_mail`, or `agent_native_experiment`.
- `mail_provider`: provider label such as `resend`, `google_workspace`, `postmark`, `agentmail`, or `openmail`.
- `mail_dns_requirements`: ordered required/optional DNS records to compare against stored `dns_records`.
- `mail_provider_verification`: provider-facing verification state without secrets or API keys.

Example Resend pilot shape:

```json
{
  "mail_plane_type": "agent_notifications",
  "mail_provider": "resend",
  "mail_dns_requirements": [
    {
      "purpose": "spf",
      "host": "@",
      "type": "TXT",
      "value": "v=spf1 include:amazonses.com ~all",
      "required": true,
      "description": "Allow Resend to send agent notification mail."
    },
    {
      "purpose": "dkim",
      "host": "resend._domainkey",
      "type": "CNAME",
      "value": "resend._domainkey.resend.com",
      "required": true
    },
    {
      "purpose": "dmarc",
      "host": "_dmarc",
      "type": "TXT",
      "value": "v=DMARC1; p=none;",
      "required": false
    }
  ],
  "mail_provider_verification": {
    "status": "pending",
    "checked_at": "2026-05-08T02:55:00+00:00",
    "external_id": "resend-domain-123",
    "notes": "Waiting on DKIM."
  }
}
```

## API Contract

`/api/domains`, `/api/domains/{domain}`, `/api/web-properties-summary`, and `/api/web-properties/{slug}` expose a `mail_plane` block for each domain.

The block includes:

- `enabled`, `plane_type`, and `provider`.
- `status`: `ok`, `warn`, `fail`, `unknown`, or `not_applicable`.
- `records[]`: required record, current match status, matched record id, and copy/paste DNS instruction.
- `counts`: total, required, verified, missing, drifted, and optional missing counts.
- `next_actions`: copy/paste-friendly actions for missing or drifted records.
- `provider_verification`: non-secret provider status metadata.

Mail-plane DNS drift remains visible even when a domain is `Email Only`, parked, or otherwise excluded from website monitoring. Website health checks should keep using the existing `monitoringSkipReason()` policy and must not treat mail-only domains as failed websites.

## Boundaries

Domain Monitor stores DNS readiness evidence and provider verification status only. It must not store provider API keys, send email, own notification policy, or configure app transactional mail.
