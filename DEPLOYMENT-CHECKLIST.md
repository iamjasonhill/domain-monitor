# Pre-Deployment Checklist for Laravel Forge

Use this checklist before deploying to Laravel Forge to ensure everything is ready.

## ✅ Pre-Deployment Tasks

### 1. Code Quality
- [x] All code passes Laravel Pint formatting
- [x] PHPStan analysis passes (level 7)
- [x] All migrations are tested locally
- [x] No console errors in application logs

### 2. Environment Configuration
- [ ] Create `.env.example` file (✅ Created)
- [ ] Document all required environment variables
- [ ] Verify all sensitive data is in `.env` (not committed)
- [ ] Test application with production-like settings locally

### 3. Database
- [ ] All migrations are up to date
- [ ] Database seeders tested (SuperadminSeeder)
- [ ] Foreign key constraints verified
- [ ] Indexes created for performance

### 4. Scheduled Tasks
- [x] All scheduled tasks configured in `routes/console.php`
- [x] Verify Laravel scheduler cron job will be set up in Forge (Forge manages this automatically)
- [x] All scheduled commands are available and working:
  - [x] `php artisan domains:detect-platforms --all` - ✅ Scheduled: Weekly Sundays at 02:00 UTC
  - [x] `php artisan domains:detect-hosting --all` - ✅ Scheduled: Weekly Sundays at 02:30 UTC
  - [x] `php artisan domains:health-check --all --type=http` - ✅ Scheduled: Every hour
  - [x] `php artisan domains:health-check --all --type=ssl` - ✅ Scheduled: Daily at 03:00 UTC
  - [x] `php artisan domains:health-check --all --type=dns` - ✅ Scheduled: Every 6 hours
  - [x] `php artisan domains:sync-synergy-expiry --all` - ✅ Scheduled: Daily at 04:00 UTC
  - [x] `php artisan domains:sync-dns-records --all` - ✅ Scheduled: Daily at 04:30 UTC (added)

### 5. Storage & Assets
- [x] `storage:link` command included in deployment script
- [x] Asset compilation (`npm run build`) in deployment script
- [x] Verify `public/storage` symlink works - ✅ Fixed: Now points to shared storage directory

### 6. Queue Workers (REQUIRED for Brain Events)
- [x] Queue workers are REQUIRED - Brain events use async dispatch
- [ ] Configure daemon in Forge for `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`
- [ ] Verify queue worker is running: `ps aux | grep 'queue:work'`
- [ ] Test that Brain events are being sent (check pending jobs count)

### 7. External Services
- [ ] Brain API credentials configured
- [ ] Synergy Wholesale credentials configured (if using .com.au domains)
- [ ] Test API connections from production-like environment

### 8. Security
- [ ] `APP_DEBUG=false` for production
- [ ] `APP_ENV=production`
- [ ] Strong database passwords
- [ ] HTTPS enabled (Forge handles automatically)
- [ ] Environment variables secured in Forge

### 9. Documentation
- [x] `DEPLOYMENT.md` created with full instructions
- [x] `README.md` updated with project information
- [ ] Deployment script documented

## 📋 Forge-Specific Configuration

### Deployment Script
Use this deployment script in Laravel Forge:

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

### Environment Variables to Set in Forge

**Required:**
```
APP_NAME="Domain Monitor"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_secure_password
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
BRAIN_BASE_URL=https://your-brain-instance.com
BRAIN_API_KEY=your-brain-api-key
```

**Optional (for .com.au domains):**
```
SYNERGY_WHOLESALE_API_URL=https://api.synergywholesale.com/soap
SYNERGY_WHOLESALE_RESELLER_ID=your_reseller_id
SYNERGY_WHOLESALE_API_KEY=your_api_key
```

### Cron Job
Forge should automatically set up:
```bash
* * * * * cd /home/forge/your-site-path && php artisan schedule:run >> /dev/null 2>&1
```

Verify this is configured in Forge → Your Site → Scheduler

### Database Setup
1. Create PostgreSQL database in Forge
2. Note credentials
3. Add to environment variables
4. Run migrations on first deployment

### Post-Deployment Verification

After deployment, verify:

1. **Application Loads**
   - [ ] Homepage loads
   - [ ] Login page accessible
   - [ ] Can log in with superadmin credentials

2. **Database**
   - [ ] All tables created
   - [ ] Can create/view domains
   - [ ] Health checks can be run

3. **Scheduled Tasks**
   - [ ] Check logs after scheduled time
   - [ ] Verify tasks executed successfully

4. **External APIs**
   - [ ] Brain API connection works
   - [ ] Synergy Wholesale API works (if configured)

5. **Storage**
   - [ ] `public/storage` symlink exists
   - [ ] Files can be uploaded (if applicable)

## 🚨 Common Issues

### Scheduled Tasks Not Running
- Verify cron job in Forge
- Check `storage/logs/laravel.log`
- Test manually: `php artisan schedule:run`

### Database Connection Failed
- Verify credentials in Forge environment
- Check PostgreSQL is running
- Test connection: `php artisan tinker` → `DB::connection()->getPdo()`

### Assets Not Loading
- Run `php artisan storage:link`
- Verify `npm run build` completed
- Check `public/build` directory exists

### 500 Errors
- Check `APP_DEBUG=true` temporarily to see errors
- Review `storage/logs/laravel.log`
- Verify all environment variables are set

## 📝 Notes

- Keep `APP_DEBUG=false` in production
- Monitor logs regularly after deployment
- Test scheduled tasks after first deployment
- Set up database backups in Forge
- Consider setting up queue workers if performance is an issue

