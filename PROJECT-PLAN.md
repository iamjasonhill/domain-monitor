# Domain Monitor - Project Plan

A comprehensive domain monitoring and management platform built with Laravel 12, PostgreSQL, and Laravel Boost MCP.

## Project Overview

Track and monitor domains with health checks, uptime monitoring, SSL certificate tracking, platform detection, and hosting provider identification.

## Completed Steps

### ✅ Step 1: Bootstrap Laravel 12 + Postgres + GitHub + Laravel Boost MCP
- [x] Create fresh Laravel 12 project
- [x] Configure PostgreSQL database
- [x] Install Laravel Boost MCP server
- [x] Run initial migrations
- [x] Initialize Git repository and push to GitHub
- [x] Configure Laravel Pint (code formatting)
- [x] Install PHPStan + Larastan (static analysis, level 7)
- [x] Set up pre-commit hooks

## Current Step

### 🔄 Step 2: Core Database Schema + Models (Domains, Checks, Alerts)
**Status:** In Progress

**Goal:** Add the 3 core tables and Eloquent models with UUID primary keys, Postgres-friendly JSONB, and proper relationships.

**Tasks:**
- [ ] Create migrations for: `domains`, `domain_checks`, `domain_alerts`
- [ ] Create Eloquent models with UUID support
- [ ] Set up relationships (hasMany/belongsTo)
- [ ] Configure JSONB payload casting
- [ ] Add proper indexes (including composite)
- [ ] Add soft deletes to Domain model
- [ ] Create model factories for testing
- [ ] Run migrations and verify
- [ ] Test with Tinker

**Schema Details:**
- **domains**: UUID primary, domain (unique), project_key, registrar, hosting_provider, platform, expires_at, last_checked_at, check_frequency_minutes, notes, is_active, soft deletes
- **domain_checks**: UUID primary, domain_id (FK), check_type, status, response_code, started_at, finished_at, duration_ms, error_message, payload (jsonb), metadata (jsonb), retry_count
- **domain_alerts**: UUID primary, domain_id (FK), alert_type, severity, triggered_at, resolved_at, notification_sent_at, acknowledged_at, auto_resolve, payload (jsonb)

## Completed Steps

### ✅ Step 2: Core Database Schema + Models (Domains, Checks, Alerts)
- [x] Create migrations for: `domains`, `domain_checks`, `domain_alerts`
- [x] Create Eloquent models with UUID support
- [x] Set up relationships (hasMany/belongsTo)
- [x] Configure JSONB payload casting
- [x] Add proper indexes (including composite)
- [x] Add soft deletes to Domain model
- [x] Create model factories for testing
- [x] Run migrations and verify
- [x] Test with Tinker

### ✅ Step 3: BrainClient Service + Event Emission
- [x] Create BrainClient service class
- [x] Set up event emission for domain checks
- [x] Configure event envelope format
- [x] Test event emission

### ✅ Step 4: Platform Detection Service
- [x] Create platform detection service
- [x] Add `website_platform` table
- [x] Implement detection logic for major platforms (WordPress, Laravel, Next.js, Shopify, Static)
- [x] Store detection results with confidence levels
- [x] Create scheduled job for platform detection (weekly)
- [x] Create artisan command for manual detection

## Next Step

### Step 5: Hosting Detection Service

## Future Steps

### Step 4: Platform Detection Service
**Goal:** Automatically detect website platforms (WordPress, Laravel, Next.js, Shopify, etc.)

**Tasks:**
- [ ] Create platform detection service
- [ ] Add `website_platform` table (or JSONB field in domains)
- [ ] Implement detection logic for major platforms
- [ ] Store detection results with confidence levels
- [ ] Create scheduled job for platform detection

### Step 5: Hosting Detection Service
**Goal:** Identify hosting providers (Vercel, Render, Cloudflare, AWS, etc.) and store admin links.

**Tasks:**
- [ ] Create hosting detection service
- [ ] Enhance `hosting_provider` field or create separate tables
- [ ] Implement detection logic for major providers
- [ ] Store admin links for hosting dashboards
- [ ] Create scheduled job for hosting detection

### Step 6: Health Check Services
**Goal:** Implement comprehensive health checks (HTTP, SSL, DNS, Uptime)

**Tasks:**
- [ ] Create HTTP check service
- [ ] Create SSL certificate check service
- [ ] Create DNS record check service
- [ ] Create uptime monitoring service
- [ ] Create scheduled jobs for each check type
- [ ] Store results in `domain_checks` table

**Check Types:**
- `http` - HTTP/HTTPS availability and response codes
- `ssl` - SSL certificate validity and expiry
- `dns` - DNS record validation
- `uptime` - Uptime monitoring with response times
- `downtime` - Downtime event detection

### Step 7: Alert System
**Goal:** Create alert system for downtime, SSL expiry, DNS changes, etc.

**Tasks:**
- [ ] Create alert triggers for various conditions
- [ ] Implement alert severity levels (info, warn, critical)
- [ ] Add alert resolution logic
- [ ] Create notification system (email, webhook, etc.)
- [ ] Add alert acknowledgment system

### Step 8: API Authentication
**Goal:** Add API key authentication for external integrations

**Tasks:**
- [ ] Create `api_keys` table
- [ ] Implement API key middleware
- [ ] Add API key generation endpoint
- [ ] Secure API endpoints
- [ ] Add API key usage tracking

### Step 9: Synergy Wholesale Integration
**Goal:** Automated expiry date syncing for .com.au domains

**Tasks:**
- [ ] Create `synergy_credentials` table
- [ ] Implement Synergy Wholesale API client
- [ ] Create scheduled job for expiry sync
- [ ] Handle API authentication
- [ ] Store encrypted credentials

### Step 10: Check Cleanup Command
**Goal:** Automatically delete domain checks older than 7 days

**Tasks:**
- [ ] Create `CleanupOldDomainChecks` command
- [ ] Add to Laravel scheduler (daily at 2 AM)
- [ ] Test cleanup command
- [ ] Document retention policy

**Note:** Keep separate until ready to deploy

### Step 11: Admin Dashboard/UI
**Goal:** Create web interface for managing domains and viewing status

**Tasks:**
- [ ] Set up frontend (Livewire, Inertia, or Blade)
- [ ] Create domain list view
- [ ] Create domain detail view
- [ ] Add domain creation/edit forms
- [ ] Display health check results
- [ ] Show alerts and notifications
- [ ] Add charts/graphs for uptime statistics

### Step 12: REST API Endpoints
**Goal:** Full REST API for integration with other systems (e.g., Jarvis)

**Endpoints:**
- `GET /api/domains` - List all domains
- `POST /api/domains` - Create domain (requires API key)
- `GET /api/domains/{id}` - Get domain details
- `PUT /api/domains/{id}` - Update domain (requires API key)
- `DELETE /api/domains/{id}` - Delete domain (requires API key)
- `GET /api/domains/{id}/health` - Get domain health status
- `GET /api/domains/{id}/uptime` - Get uptime statistics
- `GET /api/domains/{id}/platform` - Get platform detection info
- `GET /api/domains/{id}/hosting` - Get hosting provider info
- `POST /api/domains/{id}/recheck` - Run full health check (requires API key)
- `GET /api/projects/{name}/domains` - Get domains for a project

### Step 13: Cron Jobs/Scheduled Tasks
**Goal:** Automated background jobs for monitoring

**Scheduled Jobs:**
- Daily expiry sync: Sync expiry dates from Synergy Wholesale
- Daily expiry reminder: Check and create expiry reminders
- Hourly uptime check: Run uptime checks for all active domains
- Daily SSL check: Check SSL certificates
- Weekly platform detection: Detect platform and hosting

### Step 14: Deployment Setup
**Goal:** Prepare for production deployment

**Tasks:**
- [ ] Set up deployment configuration
- [ ] Configure environment variables
- [ ] Set up database migrations for production
- [ ] Configure scheduled tasks (cron)
- [ ] Set up monitoring and logging
- [ ] Performance optimization
- [ ] Security hardening

## Technical Decisions

### Database
- **PostgreSQL** with JSONB for flexible payload storage
- **UUID primary keys** for all tables
- **Soft deletes** on domains table
- **Composite indexes** for common query patterns

### Code Quality
- **Laravel Pint** for code formatting (PSR-12)
- **PHPStan level 7** for static analysis
- **Pre-commit hooks** for automated checks

### Architecture
- **Consolidated check approach**: Single `domain_checks` table with `check_type` field
- **Flexible payload storage**: JSONB for unstructured data
- **Event-driven**: BrainClient integration for event emission

## Notes

- Check history retention: 7 days (cleanup command in Step 10)
- Personal project: No multi-tenancy needed
- No audit trail required for MVP
- Platform detection can be added incrementally
- Hosting detection can start simple (string field) and expand later

## Reference

Based on previous Next.js/TypeScript implementation at:
`/Users/jasonhill/Projects/non-laravel-projects/domain-manage-project/`

