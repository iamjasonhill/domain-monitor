# Deployment Guide - Domain Monitor

This guide covers deploying the Domain Monitor application to Laravel Forge.

## Prerequisites

- Laravel Forge account
- PostgreSQL database (can be provisioned via Forge)
- Domain name configured in Forge
- GitHub repository access

## Pre-Deployment Checklist

### 1. Environment Variables

Ensure all required environment variables are set in Laravel Forge:

#### Application Settings
- `APP_NAME` - Application name (e.g., "Domain Monitor")
- `APP_ENV` - Set to `production`
- `APP_DEBUG` - Set to `false`
- `APP_URL` - Your production domain URL (e.g., `https://domain-monitor.example.com`)
- `APP_KEY` - Generated automatically by Forge

#### Database Configuration
- `DB_CONNECTION=pgsql`
- `DB_HOST` - PostgreSQL host (usually `127.0.0.1` for Forge)
- `DB_PORT=5432`
- `DB_DATABASE` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password

#### Session & Cache
- `SESSION_DRIVER=database`
- `CACHE_STORE=database`
- `QUEUE_CONNECTION=database`

#### Brain API (Required)
- `BRAIN_BASE_URL` - Your Brain instance URL
- `BRAIN_API_KEY` - Your Brain API key

#### Domain Monitor Settings (Optional)
- `DOMAIN_MONITOR_RECENT_FAILURES_HOURS` - Default “Recent failures” window (hours). Can be overridden in-app at `/settings/monitoring`.
- `DOMAIN_MONITOR_PRUNE_DOMAIN_CHECKS_DAYS` - Retain `domain_checks` history for N days (default 90).
- `DOMAIN_MONITOR_PRUNE_ELIGIBILITY_CHECKS_DAYS` - Retain `domain_eligibility_checks` history for N days (default 180).

#### Synergy Wholesale API (Optional - for .com.au domains)
- `SYNERGY_WHOLESALE_API_URL` - Default: `https://api.synergywholesale.com/soap`
- `SYNERGY_WHOLESALE_RESELLER_ID` - Your reseller ID
- `SYNERGY_WHOLESALE_API_KEY` - Your API key

### 2. Database Setup

1. **Create Database in Forge:**
   - Go to your server → Databases
   - Create a new PostgreSQL database
   - Note the database name, username, and password

2. **Run Migrations:**
   - Forge will automatically run migrations on deployment
   - Or run manually: `php artisan migrate --force`

3. **Create Superadmin User:**
   ```bash
   php artisan db:seed --class=SuperadminSeeder --force
   ```
   
   **Default Login Credentials:**
   - **Email:** `jason@jasonhill.com.au`
   - **Password:** `password`
   - ⚠️ **Important:** Change the password immediately after first login!

### 3. Storage Link

The deployment script should include:
```bash
php artisan storage:link
```

This creates a symbolic link from `public/storage` to `storage/app/public`.

### 4. Scheduled Tasks (Cron Jobs)

Laravel Forge automatically sets up the Laravel scheduler. Ensure this cron job is configured:

```bash
* * * * * cd /home/forge/your-site-path && php artisan schedule:run >> /dev/null 2>&1
```

**Scheduled Tasks Configured:**
- **Platform Detection**: Weekly on Sundays at 02:00 UTC
- **Hosting Detection**: Weekly on Sundays at 02:30 UTC
- **HTTP Health Checks**: Every hour
- **SSL Certificate Checks**: Daily at 03:00 UTC
- **DNS Checks**: Every 6 hours
- **Synergy Wholesale Sync**: Daily at 04:00 UTC (for .com.au domains)
- **Prune Monitoring Data**: Daily at 09:00 UTC

### 4a. Monitoring Settings UI (Optional)

You can tune “recent” windows from the UI at:

- `/settings/monitoring`

The DB setting takes precedence; environment variables are used as defaults/fallbacks.

### 5. Queue Workers with Laravel Horizon (REQUIRED for Brain Events)

**IMPORTANT**: Queue workers are REQUIRED for Brain event notifications. Health checks and expiry alerts use async dispatch, so a queue worker must be running.

This application uses **Laravel Horizon** for queue management, which provides:
- Web dashboard for monitoring queue jobs
- Better job retry handling
- Metrics and monitoring
- Automatic process management

**Prerequisites**: 
- **Redis is REQUIRED** - Horizon uses Redis for its metadata storage (dashboard, metrics, job history)
- Ensure Redis is installed on your server: `redis-cli ping` should return `PONG`
- Configure Redis connection in `.env`:
  ```env
  REDIS_HOST=127.0.0.1
  REDIS_PASSWORD=null
  REDIS_PORT=6379
  ```

**Set up Horizon daemon in Forge**:

1. Go to your site → Daemons
2. Add a new daemon:
   - **Command**: `php artisan horizon`
   - **User**: `forge`
   - **Directory**: `/home/forge/domains.again.com.au/current`
3. Click **Start Daemon**

**Access Horizon Dashboard**:
- URL: `https://your-domain.com/horizon`
- Authentication: All authenticated users can access (configured in `HorizonServiceProvider`)

**Verification**:
- Check if Horizon is running: `ps aux | grep 'horizon'`
- Check pending jobs: Visit `/horizon` dashboard
- View queue metrics and job history in the Horizon dashboard

**Note**: The application uses `QUEUE_CONNECTION=database`, so all async jobs (including Brain events) are stored in the database and processed by Horizon workers.

### 6. Asset Compilation

Forge will automatically run `npm install` and `npm run build` during deployment if configured.

Ensure your `package.json` includes:
```json
{
  "scripts": {
    "build": "vite build"
  }
}
```

## Deployment Script

Recommended Forge deployment script:

```bash
cd /home/forge/your-site-path
git pull origin main
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
npm ci
npm run build
```

## Post-Deployment Steps

### 1. Verify Application

1. Visit your domain URL
2. Check that the login page loads
3. Log in with your superadmin credentials
4. Verify the dashboard displays correctly

### 2. Test Scheduled Tasks

Run scheduled commands manually to verify they work:

```bash
# Test platform detection
php artisan domains:detect-platforms --all

# Test hosting detection
php artisan domains:detect-hosting --all

# Test health checks
php artisan domains:health-check --all --type=http

# Test Synergy sync (if applicable)
php artisan domains:sync-synergy-expiry --all
```

### 3. Monitor Logs

Check application logs for any errors:

```bash
tail -f storage/logs/laravel.log
```

### 4. Set Up Synergy Wholesale Credentials (Optional)

If you're managing .com.au domains:

1. Log into the application
2. Create a SynergyCredential via Tinker or create a seeder:
   ```php
   php artisan tinker
   ```
   ```php
   $credential = \App\Models\SynergyCredential::create([
       'reseller_id' => env('SYNERGY_WHOLESALE_RESELLER_ID'),
       'api_key' => env('SYNERGY_WHOLESALE_API_KEY'),
       'api_url' => env('SYNERGY_WHOLESALE_API_URL', 'https://api.synergywholesale.com/soap'),
       'is_active' => true,
   ]);
   ```

## Security Checklist

- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production`
- [ ] Strong database passwords
- [ ] HTTPS enabled (Forge handles this automatically)
- [ ] Environment variables secured (not in version control)
- [ ] File permissions set correctly (Forge handles this)
- [ ] Regular backups configured (Forge handles this)

## Troubleshooting

### Scheduled Tasks Not Running

1. Verify cron job is set up in Forge
2. Check Laravel logs: `storage/logs/laravel.log`
3. Test scheduler manually: `php artisan schedule:run`

### Database Connection Issues

1. Verify database credentials in Forge environment variables
2. Check PostgreSQL is running: `sudo systemctl status postgresql`
3. Verify database exists: `psql -U username -d database_name`

### Storage Link Issues

If images/files aren't loading:
```bash
php artisan storage:link
```

### Queue Jobs Not Processing

1. Verify queue worker daemon is running in Forge
2. Check queue table: `php artisan queue:work`
3. Check failed jobs: `php artisan queue:failed`

## Maintenance

### Regular Tasks

- Monitor application logs weekly
- Review scheduled task execution
- Check database size and optimize if needed
- Update dependencies monthly: `composer update` and `npm update`

### Backup Strategy

Laravel Forge automatically handles:
- Database backups (daily)
- Application backups (configurable)

Ensure backups are tested and restorable.

## Support

For issues or questions:
- Check Laravel logs: `storage/logs/laravel.log`
- Review Forge server logs
- Check scheduled task execution in Forge dashboard

