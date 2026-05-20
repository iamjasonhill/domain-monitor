# .au Compliance Failure Report

Domain Monitor can generate a read-only work queue for current Synergy Wholesale `.au` eligibility/compliance failures.

Run a dry summary without writing files:

```bash
php artisan domains:report-au-compliance-failures --dry-run
```

Write Markdown and CSV artifacts to the default business-vault reports folder:

```bash
php artisan domains:report-au-compliance-failures
```

Default output directory:

```text
/Users/jasonhill/Projects/Business/vault/domains/compliance/reports
```

Write to a caller-provided directory:

```bash
php artisan domains:report-au-compliance-failures --output-dir=/path/to/reports
```

The command uses Synergy Wholesale `listAuNonCompliantDomains` through `SynergyWholesaleClient::listNonCompliantAuDomains()` as current Synergy truth, then enriches matching local `Domain` records with registrant, eligibility, expiry, renewal, registrar, latest compliance-check, and open compliance-alert state.

Rows where Synergy reports a failing domain that does not exist locally are still included with `local_record_status` set to `not in local domain table`.

Manual workflow status values are:

```text
needs review
needs old entity lookup
needs current eligible entity selected
cor draft needed
cor ready for synergy
submitted in synergy
resolved
parked for later
```

Use the generated Markdown/CSV as the business-vault COR remediation queue. The operator workflow is:

1. Review each failing domain and Synergy failure reason.
2. Look up the old registrant ABN, ACN, or business-name basis where needed.
3. Select the current eligible entity outside Domain Monitor.
4. Prepare the COR/evidence pack in the business vault.
5. Submit the change manually in Synergy Wholesale.
6. Re-run the report after Synergy and Domain Monitor compliance checks refresh.

The command does not expose Synergy API credentials and does not perform COR, DNS, renewal, registrant-update, Domain Monitor mutation, or Synergy mutation actions.
