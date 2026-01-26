# Rookie Errors Analysis Report

## Critical Issues Found

### 1. ⚠️ SQL Injection Risk in `orderByRaw` (Medium Risk)
**Location**: `app/Livewire/DomainsList.php` lines 180, 185, 194, 197, 207, 210

**Issue**: While `$dir` is validated to be 'asc' or 'desc', it's still concatenated directly into SQL strings. This is a security anti-pattern.

**Current Code**:
```php
$query->orderByRaw('LOWER(domains.domain) '.$dir);
$query->orderByRaw("domains.expires_at {$dir} NULLS LAST");
```

**Risk**: If validation is bypassed or changed, this could allow SQL injection.

**Recommendation**: Use parameter binding or whitelist approach:
```php
$dir = $dir === 'desc' ? 'desc' : 'asc';
$query->orderByRaw('LOWER(domains.domain) '.$dir); // Still concatenated but safer
// OR better: Use separate orderBy methods
```

### 2. ⚠️ Race Condition in Subdomain Creation (Medium Risk)
**Location**: `app/Livewire/DomainDetail.php` lines 641-657

**Issue**: Check-then-act pattern without proper locking. Two concurrent requests could create duplicate subdomains.

**Current Code**:
```php
$existing = Subdomain::where('domain_id', $this->domain->id)
    ->where('subdomain', $this->subdomainName)
    ->first();

if ($existing) {
    session()->flash('error', 'A subdomain with this name already exists.');
    return;
}

Subdomain::create([...]); // Race condition here!
```

**Risk**: Two users could create the same subdomain simultaneously.

**Recommendation**: Use database unique constraint + handle exception, or use `firstOrCreate()` with proper locking.

### 3. ⚠️ Missing Database Transactions (Low-Medium Risk)
**Location**: Multiple locations

**Issues**:
- `app/Livewire/DomainDetail.php::saveDnsRecord()` - Creates DNS record via API then local DB (lines 479-499)
- `app/Livewire/DomainDetail.php::deleteDnsRecord()` - Deletes from API then local DB (lines 549-560)
- `app/Livewire/DomainDetail.php::saveSubdomain()` - Check then create (lines 641-657)

**Risk**: If API call succeeds but DB operation fails (or vice versa), data becomes inconsistent.

**Recommendation**: Wrap multi-step operations in `DB::transaction()`.

### 4. ⚠️ Missing Authorization Checks (Low Risk - May be Intentional)
**Location**: `app/Livewire/DomainDetail.php`, `app/Livewire/DomainForm.php`

**Issue**: No checks to ensure users can only access/modify domains they should have access to.

**Current**: Routes are protected by `auth` middleware, but any authenticated user can access any domain.

**Risk**: If this is meant to be multi-tenant, users could access other users' domains.

**Recommendation**: 
- If single-tenant: Document this is intentional
- If multi-tenant: Add `user_id` to domains table and add authorization checks

### 5. ⚠️ Potential Null Reference Issues (Low Risk)
**Location**: `app/Livewire/DomainDetail.php` line 105

**Issue**: `findOrFail()` will throw exception, but `$this->domainId` could be invalid UUID format.

**Current Code**:
```php
$this->domain = Domain::with([...])->findOrFail($this->domainId);
```

**Risk**: Invalid UUID format could cause database error instead of user-friendly 404.

**Recommendation**: Validate UUID format before querying.

### 6. ⚠️ Missing Error Handling for External API Calls (Low Risk)
**Location**: `app/Livewire/DomainDetail.php` - Multiple locations

**Issue**: External API calls (Synergy Wholesale) have try-catch but don't handle all failure scenarios.

**Risk**: Network timeouts, partial failures, or API changes could cause unexpected behavior.

**Recommendation**: Add retry logic, better error messages, and fallback behavior.

### 7. ✅ Inconsistent Error Handling (FIXED)
**Location**: Throughout `app/Livewire/DomainDetail.php`

**Issue**: Some methods used both `session()->flash('error')` AND `$this->addError()` for the same error, causing duplicate error messages.

**Fix Applied**: 
- Removed redundant `session()->flash()` calls when `addError()` is used for field-specific errors
- Standardized pattern:
  - `$this->addError('fieldName', 'message')` → For form field validation errors (shows inline)
  - `session()->flash('error', 'message')` → For general errors not tied to a field (shows at top)
  - Never use both for the same error

**Result**: Cleaner UX with no duplicate error messages.

## Good Practices Found ✅

1. ✅ Proper use of `whereRaw()` with parameter binding in search (lines 268-273 in `app/Models/Domain.php`)
2. ✅ Validation rules are properly defined
3. ✅ Eager loading used to prevent N+1 queries
4. ✅ Proper use of `findOrFail()` in most places
5. ✅ Domain ownership checks in subdomain operations (`$subdomain->domain_id !== $this->domain->id`)

## Recommendations Priority

1. **High Priority**: Fix race condition in subdomain creation (#2)
2. **High Priority**: Add database transactions for multi-step operations (#3)
3. **Medium Priority**: Fix SQL injection risk in orderByRaw (#1)
4. **Low Priority**: Add authorization checks if multi-tenant (#4)
5. **Low Priority**: Standardize error handling (#7)
