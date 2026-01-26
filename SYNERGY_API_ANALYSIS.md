# Synergy Wholesale API - Deep Analysis

## Currently Extracted Fields ✅

From `getDomainInfo()`, we're already extracting:
- ✅ `domain` (domainName)
- ✅ `expiry_date` (domain_expiry)
- ✅ `created_date` (createdDate)
- ✅ `domain_status`
- ✅ `auto_renew` (autoRenew)
- ✅ `nameservers` (nameServers array)
- ✅ `nameserver_details` (detailed NS info with IPs)
- ✅ `dns_config_name` (dnsConfigName)
- ✅ `registrant_name` (auRegistrantName)
- ✅ `registrant_id_type` (auRegistrantIDType)
- ✅ `registrant_id` (auRegistrantID)
- ✅ `eligibility_type` (auEligibilityType)
- ✅ `eligibility_valid` (au_valid_eligibility / auValidEligibility)
- ✅ `eligibility_last_check` (auEligibilityLastCheck)
- ✅ `registrar`
- ✅ `status` (API response status)

## Available But NOT Currently Extracted ❌

### High Value Fields (Recommended to Add)

1. **domainRoid** - Registry Object ID
   - Unique identifier from registry
   - Useful for tracking and API operations
   - **Recommendation**: Add to Domain model

2. **auPolicyID** & **auPolicyIDDesc** - Policy Information
   - Policy ID and description for .au domains
   - Shows which eligibility policy applies
   - **Recommendation**: Add `au_policy_id` and `au_policy_desc` fields

3. **auComplianceReason** - Compliance Status
   - Reason why domain is non-compliant (if applicable)
   - Critical for .au domain compliance monitoring
   - **Recommendation**: Add `au_compliance_reason` field

4. **auAssociationID** - Association ID
   - Association identifier for .au domains
   - **Recommendation**: Add `au_association_id` field

5. **registryID** - Registry Identifier
   - Registry ID (e.g., `85` for .au registry)
   - Useful for identifying which registry manages the domain
   - **Recommendation**: Add `registry_id` field

### Medium Value Fields

6. **idProtect** - ID Protection Status
   - Shows if domain has ID protection enabled
   - **Recommendation**: Add `id_protect` field (nullable string)

7. **categories** - Domain Categories
   - Array of category IDs/names
   - Could be useful for organization
   - **Recommendation**: Add `categories` JSON field

8. **dnsConfig** - DNS Configuration ID
   - Numeric ID for DNS config (we have name, but not ID)
   - **Recommendation**: Add `dns_config_id` field

### Low Priority / Security Fields

9. **domainPassword** / **EPP Code**
   - Domain password/EPP code for transfers
   - **Security Note**: Should be encrypted if stored
   - **Recommendation**: Only store if needed, use encrypted field

10. **auAssociationAuthInfo** - Association Auth Info
    - Authentication info for .au domains
    - **Security Note**: Sensitive data
    - **Recommendation**: Only if needed for transfers

11. **icannVerificationDateEnd** & **icannStatus**
    - ICANN verification info (often N/A for .au)
    - **Recommendation**: Low priority, rarely used for .au domains

12. **bulkInProgress** - Bulk Operation Status
    - Shows if bulk operation is in progress
    - **Recommendation**: Not needed for monitoring

## Unused API Methods (High Value) 🚀

### 1. **rawDomainContacts** - Contact Information ⭐⭐⭐
**Value**: Very High
**Use Case**: Compliance, audit trails, contact management

Returns:
- Registrant contact (name, email, phone, address)
- Admin contact
- Technical contact
- Billing contact

**Recommendation**: 
- Create `domain_contacts` table or JSON field
- Store encrypted if containing sensitive data
- Useful for compliance audits

### 2. **domainRenewRequired** / **canRenewDomain** ⭐⭐⭐
**Value**: Very High
**Use Case**: Automated renewal management

**Recommendation**:
- Add `renewal_required` boolean field
- Add `can_renew` boolean field
- Create scheduled job to check renewal status
- Alert when domains need renewal

### 3. **listAuNonCompliantDomains** ⭐⭐⭐
**Value**: Very High (for .au domains)
**Use Case**: Compliance monitoring

**Recommendation**:
- Create scheduled job to check compliance
- Alert on non-compliant domains
- Track compliance history

### 4. **bulkDomainInfo** ⭐⭐
**Value**: High
**Use Case**: Efficient bulk syncing

**Recommendation**:
- Use for bulk import/sync operations
- More efficient than individual `domainInfo` calls
- Reduces API rate limiting issues

### 5. **lockDomain** / **unlockDomain** / **isDomainLocked** ⭐⭐
**Value**: Medium-High
**Use Case**: Transfer protection monitoring

**Recommendation**:
- Add `transfer_lock` boolean field
- Track lock status changes
- Alert if domain is unlocked (security risk)

### 6. **getDomainPricing** ⭐
**Value**: Medium
**Use Case**: Cost tracking

**Recommendation**:
- Store renewal pricing if needed
- Useful for budgeting

### 7. **checkDomainEPPCode** / **updateDomainPassword** ⭐
**Value**: Low-Medium
**Use Case**: EPP code management

**Recommendation**: Only if managing transfers

## Implementation Recommendations

### Phase 1: High Priority Additions

1. **Add missing .au compliance fields**:
   ```php
   // Migration
   $table->string('au_policy_id')->nullable();
   $table->text('au_policy_desc')->nullable();
   $table->text('au_compliance_reason')->nullable();
   $table->string('au_association_id')->nullable();
   $table->string('registry_id')->nullable();
   $table->string('domain_roid')->nullable()->unique();
   ```

2. **Implement `rawDomainContacts` method**:
   ```php
   public function getDomainContacts(string $domain): ?array
   {
       // Returns: registrant, admin, tech, billing contacts
   }
   ```

3. **Implement `domainRenewRequired` check**:
   ```php
   public function checkRenewalRequired(string $domain): ?array
   {
       // Returns: can_renew, renewal_required, days_until_expiry
   }
   ```

4. **Add transfer lock status**:
   ```php
   public function getDomainLockStatus(string $domain): ?bool
   {
       // Check if domain is locked (prevents transfers)
   }
   ```

### Phase 2: Compliance Monitoring

1. **Scheduled job for compliance checking**:
   - Use `listAuNonCompliantDomains` weekly
   - Alert on non-compliant domains
   - Track compliance history

2. **Contact information storage**:
   - Store domain contacts (encrypted if sensitive)
   - Track contact changes over time
   - Useful for compliance audits

### Phase 3: Enhanced Features

1. **Bulk operations**:
   - Use `bulkDomainInfo` for efficient syncing
   - Batch operations for better performance

2. **Renewal management**:
   - Track which domains can be renewed
   - Automated renewal reminders
   - Integration with `renewDomain` method

## Current API Method Usage

### ✅ Implemented Methods:
- `domainInfo` - Get domain information
- `listDomains` - List all domains
- `listDNSZone` - Get DNS records
- `addDNSRecord` - Add DNS record
- `updateDNSRecord` - Update DNS record
- `deleteDNSRecord` - Delete DNS record
- `balanceQuery` - Get account balance
- `renewDomain` - Renew domain

### ❌ Not Implemented (High Value):
- `rawDomainContacts` - Contact information
- `domainRenewRequired` - Renewal status
- `canRenewDomain` - Can domain be renewed
- `listAuNonCompliantDomains` - Compliance check
- `bulkDomainInfo` - Bulk domain info
- `lockDomain` / `unlockDomain` - Transfer lock
- `isDomainTransferrable` - Transfer status

## Data We're Missing in Logs

The debug log at line 115-120 logs `response_keys` which shows all available fields. We should:
1. Check actual API responses in logs to see what fields exist
2. Compare with what we're extracting
3. Add any missing high-value fields

## Next Steps

1. **Review production logs** to see actual API response structure
2. **Add high-priority fields** (compliance, renewal status)
3. **Implement contact information** storage
4. **Add compliance monitoring** job
5. **Enhance renewal management** with renewal status checks
