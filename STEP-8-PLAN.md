# Step 8: API Authentication - Detailed Plan

## Goal
Add API key authentication for external integrations (e.g., Jarvis, Brain, other services).

## Overview
Implement a secure API key system that allows external services to authenticate and access the Domain Monitor API endpoints.

## Implementation Plan

### 1. Database Schema

#### `api_keys` Table
```php
Schema::create('api_keys', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name')->comment('Human-readable name for the API key');
    $table->string('key', 64)->unique()->comment('Hashed API key');
    $table->string('key_prefix', 8)->index()->comment('First 8 chars for identification');
    $table->text('description')->nullable()->comment('Optional description');
    $table->json('permissions')->nullable()->comment('Allowed permissions/endpoints');
    $table->timestamp('last_used_at')->nullable()->comment('Last usage timestamp');
    $table->timestamp('expires_at')->nullable()->comment('Optional expiration');
    $table->boolean('is_active')->default(true)->index();
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['is_active', 'expires_at']);
});
```

**Key Design:**
- Store hashed keys (like passwords) - never store plain text
- Store key prefix (first 8 chars) for identification when listing keys
- Support permissions/scope system for fine-grained access
- Track last usage for monitoring
- Support expiration dates
- Soft deletes for audit trail

### 2. API Key Model

**File:** `app/Models/ApiKey.php`

**Features:**
- UUID primary key
- Automatic key hashing on creation
- Key generation helper method
- Permission checking methods
- Usage tracking
- Expiration validation

**Methods:**
- `generateKey()` - Generate secure random API key
- `hashKey(string $key)` - Hash the key for storage
- `verifyKey(string $key)` - Verify provided key against stored hash
- `hasPermission(string $permission)` - Check if key has specific permission
- `isExpired()` - Check if key has expired
- `recordUsage()` - Update last_used_at timestamp

### 3. API Key Middleware

**File:** `app/Http/Middleware/AuthenticateApiKey.php`

**Functionality:**
- Extract API key from request header (`X-API-Key` or `Authorization: Bearer {key}`)
- Look up API key in database
- Verify key hash
- Check if key is active and not expired
- Attach API key model to request for use in controllers
- Return 401 if authentication fails

**Header Formats Supported:**
- `X-API-Key: {key}`
- `Authorization: Bearer {key}`

### 4. API Key Management Endpoints

**Base Route:** `/api/admin/api-keys` (protected by web auth or separate admin auth)

**Endpoints:**
- `POST /api/admin/api-keys` - Create new API key
  - Returns: `{ id, name, key, key_prefix, description, permissions, expires_at, created_at }`
  - **Important:** Only return the full key once on creation!
  
- `GET /api/admin/api-keys` - List all API keys
  - Returns: `{ id, name, key_prefix, description, last_used_at, expires_at, is_active, created_at }`
  - **Never return full keys in list!**
  
- `GET /api/admin/api-keys/{id}` - Get API key details
  - Returns same as list (no full key)
  
- `PUT /api/admin/api-keys/{id}` - Update API key (name, description, permissions, expires_at)
  - Cannot update the key itself (must regenerate)
  
- `DELETE /api/admin/api-keys/{id}` - Delete (soft delete) API key
  
- `POST /api/admin/api-keys/{id}/regenerate` - Regenerate API key
  - Invalidates old key, generates new one
  - Returns new key (only time full key is returned)

**Alternative:** Artisan command for key management (more secure)
- `php artisan api-keys:create {name} {--description=} {--expires=}`
- `php artisan api-keys:list`
- `php artisan api-keys:revoke {id}`
- `php artisan api-keys:regenerate {id}`

### 5. API Routes Setup

**File:** `routes/api.php`

**Structure:**
```php
// Public endpoints (no auth required)
Route::get('/health', [HealthController::class, 'check']);

// Protected endpoints (require API key)
Route::middleware('auth:api-key')->group(function () {
    // Domain endpoints
    Route::apiResource('domains', DomainController::class);
    Route::get('domains/{domain}/health', [DomainController::class, 'health']);
    Route::get('domains/{domain}/uptime', [DomainController::class, 'uptime']);
    Route::get('domains/{domain}/platform', [DomainController::class, 'platform']);
    Route::get('domains/{domain}/hosting', [DomainController::class, 'hosting']);
    Route::post('domains/{domain}/recheck', [DomainController::class, 'recheck']);
    
    // Project endpoints
    Route::get('projects/{name}/domains', [ProjectController::class, 'domains']);
});
```

### 6. Permission System (Optional but Recommended)

**Permission Types:**
- `domains:read` - Read domain information
- `domains:write` - Create/update domains
- `domains:delete` - Delete domains
- `domains:recheck` - Trigger health checks
- `health:read` - Read health check results
- `platform:read` - Read platform detection info
- `hosting:read` - Read hosting detection info

**Implementation:**
- Store permissions as JSON array in `api_keys.permissions`
- Middleware checks permissions before allowing access
- Default: If no permissions specified, allow all (for backward compatibility)

### 7. Usage Tracking

**Track:**
- Last used timestamp
- Request count (optional - could add `usage_count` field)
- IP address (optional - could add `last_used_from_ip` field)

**Usage:**
- Update `last_used_at` on each authenticated request
- Useful for monitoring and identifying unused keys
- Can add scheduled job to revoke unused keys after X days

### 8. Security Considerations

**Key Generation:**
- Use `Str::random(32)` or `bin2hex(random_bytes(32))` for 64-character keys
- Ensure cryptographically secure random generation

**Key Storage:**
- Hash keys using `Hash::make()` (bcrypt)
- Never log or return full keys except on creation
- Store key prefix for identification only

**Key Transmission:**
- Require HTTPS in production
- Support both header formats for flexibility
- Clear error messages (but don't leak information)

**Rate Limiting:**
- Add rate limiting per API key
- Prevent abuse and DoS attacks
- Use Laravel's built-in rate limiting

### 9. Testing

**Test Cases:**
- ✅ Valid API key authentication
- ✅ Invalid API key rejection
- ✅ Expired key rejection
- ✅ Inactive key rejection
- ✅ Permission checking
- ✅ Usage tracking
- ✅ Key generation and hashing
- ✅ Key regeneration
- ✅ Rate limiting

## Implementation Order

1. **Create migration** for `api_keys` table
2. **Create ApiKey model** with hashing and verification
3. **Create middleware** for API key authentication
4. **Register middleware** in `bootstrap/app.php` or `app/Http/Kernel.php`
5. **Create API routes file** (`routes/api.php`)
6. **Create API key management** (Artisan commands OR admin endpoints)
7. **Add rate limiting** to API routes
8. **Test authentication** with sample API key
9. **Document API usage** in README or separate API docs

## Alternative: Laravel Sanctum API Tokens

**Consideration:** Laravel Sanctum provides API token authentication out of the box.

**Pros:**
- Built-in, well-tested
- Supports both SPA and API authentication
- Token management included

**Cons:**
- Tied to User model (we may not have users)
- More complex if we don't need user-based auth
- May be overkill for simple API key auth

**Decision:** For this project, custom API key system is better because:
- No user model required
- Simpler for service-to-service authentication
- More control over key format and management
- Better for external integrations (Jarvis, Brain, etc.)

## Example Usage

**Creating an API Key:**
```bash
php artisan api-keys:create "Jarvis Integration" --description="API key for Jarvis service" --expires="2026-12-31"
# Returns: dm_live_abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
```

**Using the API Key:**
```bash
curl -H "X-API-Key: dm_live_abc123def456ghi789jkl012mno345pqr678stu901vwx234yz" \
     https://domain-monitor.com/api/domains
```

**Or with Bearer token:**
```bash
curl -H "Authorization: Bearer dm_live_abc123def456ghi789jkl012mno345pqr678stu901vwx234yz" \
     https://domain-monitor.com/api/domains
```

## Next Steps After Step 8

Once API authentication is complete, we can proceed with:
- **Step 12:** REST API Endpoints (will use the authentication from Step 8)
- **Step 11:** Admin Dashboard (can use API keys for admin API access)

