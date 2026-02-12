# Domain Monitor

A comprehensive domain monitoring and management platform built with Laravel 12, PostgreSQL, and Laravel Boost MCP.

## Features

- **Domain Management**: Track and manage domains with comprehensive metadata
- **Health Checks**: Automated HTTP, SSL, and DNS health checks
- **Platform Detection**: Automatic detection of website platforms (WordPress, Laravel, Next.js, etc.)
- **Hosting Detection**: Identify hosting providers and store admin links
- **Synergy Wholesale Integration**: Automated expiry date syncing for .com.au domains
- **DNS Management**: View and manage DNS records for .com.au domains
- **Brain Integration**: Event emission to Brain Nucleus for monitoring
- **Livewire UI**: Modern, responsive admin interface

## Requirements

- PHP 8.2+
- PostgreSQL 12+
- Composer
- Node.js & NPM
- Laravel 12

## Installation

1. Clone the repository:
```bash
git clone https://github.com/your-username/domain-monitor.git
cd domain-monitor
```

2. Install dependencies:
```bash
composer install
npm ci
```

3. Configure environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Set up database:
```bash
# Update .env with your database credentials
php artisan migrate
php artisan db:seed --class=SuperadminSeeder
```

5. Build assets:
```bash
npm run build
```

6. Start the development server:
```bash
php artisan serve
```

## Environment Variables

See `DEPLOYMENT.md` for a complete list of required environment variables.

### Required
- `APP_NAME` - Application name
- `APP_ENV` - Environment (local/production)
- `APP_DEBUG` - Debug mode (false in production)
- `APP_URL` - Application URL
- `DB_*` - Database configuration
- `BRAIN_BASE_URL` - Brain API base URL
- `BRAIN_API_KEY` - Brain API key

### Optional
- `SYNERGY_WHOLESALE_*` - Synergy Wholesale API credentials (for .com.au domains)
- `HORIZON_ALLOWED_EMAILS` - Comma-separated allowlist for Horizon dashboard access in non-local environments (recommended in production).
- `DOMAIN_MONITOR_RECENT_FAILURES_HOURS` - Default “Recent failures” window (hours). Can be overridden in-app at `/settings/monitoring`.
- `DOMAIN_MONITOR_PRUNE_DOMAIN_CHECKS_DAYS` - Retain `domain_checks` history for N days (default 90).
- `DOMAIN_MONITOR_PRUNE_ELIGIBILITY_CHECKS_DAYS` - Retain `domain_eligibility_checks` history for N days (default 180).

Production note: set `HORIZON_ALLOWED_EMAILS` before exposing `/horizon` so only approved operator accounts can access queue internals.

## Scheduled Tasks

The application includes several scheduled tasks configured in `routes/console.php`:

- **Platform Detection**: Weekly on Sundays at 02:00 UTC
- **Hosting Detection**: Weekly on Sundays at 02:30 UTC
- **HTTP Health Checks**: Every hour
- **SSL Certificate Checks**: Daily at 03:00 UTC
- **DNS Checks**: Every 6 hours
- **Synergy Wholesale Sync**: Daily at 04:00 UTC
- **Prune Monitoring Data**: Daily at 09:00 UTC

Ensure Laravel's scheduler is running:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Monitoring Settings UI

You can tune “recent” windows from the UI at:

- `/settings/monitoring`

The DB setting takes precedence; environment variables are used as defaults/fallbacks.

## Deployment

See `DEPLOYMENT.md` for detailed deployment instructions for Laravel Forge.

## Documentation

The detailed project documentation has been moved to the `docs/` folder:

### General
- [Project Overview (Completed Steps)](docs/PROJECT-PLAN.md)
- [Project Setup Guide](docs/PROJECT-SETUP-GUIDE.md)

### Deployment & Ops
- [Deployment Guide (Laravel Forge)](docs/DEPLOYMENT.md)
- [Deployment Checklist](docs/DEPLOYMENT-CHECKLIST.md)
- [Pre-Deployment Checks](docs/CHECK-BEFORE-DEPLOY.md)
- [Server Access & Logging](SERVER_ACCESS.md) *(Local only, ignored by git)*

### Features & Implementation
- [Auth & UI Plan](docs/AUTH-AND-UI-PLAN.md)
- [Step 8 Plan](docs/STEP-8-PLAN.md)

### Styling
- [Styling Guide](docs/STYLING-GUIDE.md)
- [Styling Quick Reference](docs/STYLING-QUICK-REFERENCE.md)

### Integrations
- [Synergy Wholesale Setup](docs/SYNERGY-SETUP.md)
- [Synergy API Fields](docs/SYNERGY-API-FIELDS.md)

## Contributing

See `CONTRIBUTING.md`.

## Security

See `SECURITY.md`.
