# Synergy Wholesale API - Available Domain Information

## Current Implementation

Currently, we're only syncing the **expiry date** from the `domainInfo` method. However, the API provides much more information!

## Complete Domain Information Available (30+ fields)

### Basic Domain Information
- **domainName** - The domain name (e.g., `movingcars.com.au`)
- **domainRoid** - Registry Object ID (unique identifier)
- **createdDate** - Domain registration date (e.g., `2005-07-06 17:24:06`)

### Status & Expiry
- **domain_status** - Current domain status (e.g., `ok`)
- **domain_expiry** - Expiry date and time (e.g., `2026-10-23 16:29:23`)
- **status** - API response status (e.g., `OK`)
- **autoRenew** - Auto-renewal setting (e.g., `on` or `off`)

### DNS & Nameservers
- **nameServers** - Array of nameservers (e.g., `['ns1.nameserver.net.au', 'ns2.nameserver.net.au', 'ns3.nameserver.net.au']`)
- **dnsConfig** - DNS configuration ID (integer)
- **dnsConfigName** - DNS configuration name (e.g., `DNS Hosting`)

### Security & Authentication
- **domainPassword** - Domain password/EPP code
- **idProtect** - ID protection status (e.g., `NA` or enabled)
- **auAssociationAuthInfo** - Australian domain association auth info

### Australian Domain Specific (.au domains)
- **auRegistrantName** - Registrant company/name (e.g., `MOVEROO PTY LTD`)
- **auRegistrantIDType** - ID type (e.g., `ACN`, `ABN`)
- **auRegistrantID** - Registrant ID number (e.g., `682723100`)
- **auEligibilityType** - Eligibility type (e.g., `Company`)
- **auPolicyID** - Policy ID (e.g., `2`)
- **auPolicyIDDesc** - Policy description
- **auAssociationID** - Association ID
- **au_valid_eligibility** - Boolean: Is eligibility valid?
- **au_eligibility_last_check** - Last eligibility check date (e.g., `2025-12-07`)
- **auValidEligibility** - Boolean: Valid eligibility status
- **auEligibilityLastCheck** - Last eligibility check date
- **auComplianceReason** - Compliance reason (if non-compliant)

### Registry Information
- **registryID** - Registry identifier (e.g., `85`)
- **icannVerificationDateEnd** - ICANN verification end date (often `N/A` for .au)
- **icannStatus** - ICANN status (often `N/A` for .au)

### Other
- **categories** - Domain categories array (can be empty)
- **bulkInProgress** - Bulk operation status (0 = none)

## Additional API Methods Available (34+ methods)

### Query & Information Methods
- `domainInfo` - Get complete domain information (currently implemented)
- `bulkDomainInfo` - Get info for multiple domains
- `listDomains` - List all domains in account
- `checkDomain` - Check if domain is available
- `bulkCheckDomain` - Check multiple domains
- `getDomainPricing` - Get pricing information
- `getDomainEligibilityFields` - Get eligibility fields for registration
- `listAvailableDomainExtensions` - List available TLDs
- `getDomainExtensionOptions` - Get options for specific TLD

### Registration & Transfer Methods
- `domainRegister` - Register a new domain
- `domainRegisterAU` - Register .au domain
- `domainRegisterUK` - Register .uk domain
- `domainRegisterUS` - Register .us domain
- `transferDomain` - Transfer domain to Synergy Wholesale
- `domainTransferUK` - Transfer .uk domain
- `isDomainTransferrable` - Check if domain can be transferred
- `checkDomainEPPCode` - Validate EPP code

### Management Methods
- `renewDomain` - Renew domain registration
- `canRenewDomain` - Check if domain can be renewed
- `domainRenewRequired` - Check if renewal is required
- `lockDomain` - Lock domain (prevent transfers)
- `unlockDomain` - Unlock domain
- `updateDomainPassword` - Update domain password/EPP code
- `restoreDomain` - Restore expired domain
- `deleteDomain` - Delete domain

### Contact Information
- `rawDomainContacts` - Get domain contact information (registrant, admin, tech, billing)

### Categories Management
- `listDomainCategories` - List available categories
- `createDomainCategory` - Create new category
- `updateDomainCategory` - Update category
- `removeDomainCategory` - Remove category
- `assignDomainCategory` - Assign domain to category
- `unassignDomainCategory` - Remove domain from category

### Australian Domain Compliance
- `listAuNonCompliantDomains` - List non-compliant .au domains

### SSL Certificate Methods
- `SSL_getDomainBeacon` - Get SSL certificate beacon
- `SSL_checkDomainBeacon` - Check SSL certificate beacon

### Other
- `getTransferredAwayDomains` - Get domains transferred away
- `domainReleaseUK` - Release .uk domain

## Potential Enhancements for Domain Monitor

### High Value Information to Store
1. **Nameservers** - Track DNS changes
2. **Auto-renewal status** - Monitor auto-renew settings
3. **Domain status** - Track status changes
4. **Created date** - Domain age tracking
5. **Australian eligibility** - Compliance monitoring for .au domains
6. **Registrant information** - For compliance/audit purposes

### Useful Methods to Implement
1. **`listDomains`** - Bulk import all domains from Synergy Wholesale
2. **`checkDomain`** - Check domain availability before adding
3. **`domainRenewRequired`** - Check which domains need renewal
4. **`listAuNonCompliantDomains`** - Monitor .au compliance
5. **`rawDomainContacts`** - Store contact information for compliance

## Example: Enhanced Domain Info Response

```php
[
    'domain' => 'movingcars.com.au',
    'expiry_date' => '2026-10-23 16:29:23',
    'created_date' => '2005-07-06 17:24:06',
    'status' => 'ok',
    'auto_renew' => 'on',
    'nameservers' => [
        'ns1.nameserver.net.au',
        'ns2.nameserver.net.au',
        'ns3.nameserver.net.au',
    ],
    'dns_config' => 'DNS Hosting',
    'registrant_name' => 'MOVEROO PTY LTD',
    'registrant_id' => '682723100',
    'registrant_id_type' => 'ACN',
    'eligibility_type' => 'Company',
    'eligibility_valid' => true,
    'eligibility_last_check' => '2025-12-07',
    'registry_id' => '85',
]
```

## Recommendations

1. **Extend the `Domain` model** to store additional fields:
   - `created_at` (from `createdDate`)
   - `auto_renew` (boolean)
   - `domain_status` (string)
   - `nameservers` (JSON array)
   - `registrant_name` (string, for .au domains)
   - `registrant_id` (string, for .au domains)
   - `eligibility_valid` (boolean, for .au domains)

2. **Create a migration** to add these fields to the `domains` table

3. **Update `SynergyWholesaleClient`** to return more fields in `getDomainInfo()`

4. **Add scheduled jobs** to:
   - Sync all domain information (not just expiry)
   - Check for domains needing renewal
   - Monitor .au compliance status
   - Track nameserver changes

5. **Implement bulk operations**:
   - `listDomains()` to import all domains from Synergy Wholesale
   - `bulkDomainInfo()` to sync multiple domains efficiently

