<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Health ping - send every 5 minutes to indicate app is alive
Schedule::command('health:ping')
    ->everyFiveMinutes()
    ->timezone('UTC');

// Horizon metrics snapshot - run every minute to collect queue metrics
Schedule::command('horizon:snapshot')
    ->everyMinute()
    ->timezone('UTC');

// Daily platform detection - queue jobs for domains that need checking
// This runs daily and queues jobs for domains not checked in the last 24 hours
Schedule::command('domains:queue-platform-detection --hours=24')
    ->daily()
    ->at('02:00')
    ->timezone('UTC');

// Weekly hosting detection for all active domains
Schedule::command('domains:detect-hosting --all')
    ->weekly()
    ->sundays()
    ->at('02:30')
    ->timezone('UTC');

// Daily IP information update - update domains and subdomains not checked in last 24 hours
Schedule::command('domains:update-ip-info --all --hours=24')
    ->daily()
    ->at('03:00')
    ->timezone('UTC');

// Uptime checks - run every 10 minutes for active domains
Schedule::command('domains:health-check --all --type=uptime')
    ->everyTenMinutes()
    ->timezone('UTC');

// HTTP health checks - run every hour for active domains
Schedule::command('domains:health-check --all --type=http')
    ->hourly()
    ->timezone('UTC');

// SSL certificate checks - run daily for active domains
Schedule::command('domains:health-check --all --type=ssl')
    ->daily()
    ->at('03:00')
    ->timezone('UTC');

// DNS checks - run every 6 hours for active domains
Schedule::command('domains:health-check --all --type=dns')
    ->everySixHours()
    ->timezone('UTC');

// Email security checks - run daily for active domains
Schedule::command('domains:health-check --all --type=email_security')
    ->daily()
    ->at('03:30')
    ->timezone('UTC');

// Reputation & Blacklist Monitoring - run weekly for all domains
Schedule::command('domains:health-check --all --type=reputation')
    ->weekly()
    ->sundays()
    ->at('04:00')
    ->timezone('UTC');

// Security headers checks - run daily for active domains
Schedule::command('domains:health-check --all --type=security_headers')
    ->daily()
    ->at('04:30')
    ->timezone('UTC');

// SEO fundamentals checks - run daily for active domains
Schedule::command('domains:health-check --all --type=seo')
    ->daily()
    ->at('05:00')
    ->timezone('UTC');

// Broken links checks - run weekly (resource intensive)
Schedule::command('domains:health-check --all --type=broken_links')
    ->weekly()
    ->sundays()
    ->at('05:30')
    ->timezone('UTC');

// Synergy Wholesale domain sync - queue jobs 3 times daily (8am, 2pm, 8pm UTC)
// Jobs are processed via Horizon to prevent gateway timeouts
Schedule::command('domains:queue-sync-jobs --type=info')
    ->dailyAt('08:00')
    ->timezone('UTC');

Schedule::command('domains:queue-sync-jobs --type=info')
    ->dailyAt('14:00')
    ->timezone('UTC');

Schedule::command('domains:queue-sync-jobs --type=info')
    ->dailyAt('20:00')
    ->timezone('UTC');

// DNS records sync - queue jobs 3 times daily (8:05am, 2:05pm, 8:05pm UTC)
Schedule::command('domains:queue-sync-jobs --type=dns')
    ->dailyAt('08:05')
    ->timezone('UTC');

Schedule::command('domains:queue-sync-jobs --type=dns')
    ->dailyAt('14:05')
    ->timezone('UTC');

Schedule::command('domains:queue-sync-jobs --type=dns')
    ->dailyAt('20:05')
    ->timezone('UTC');

// Domain import - run once daily (8:10am UTC)
Schedule::command('domains:queue-sync-jobs --type=import')
    ->dailyAt('08:10')
    ->timezone('UTC');

// Auto-renew domains - run daily to renew domains with auto_renew=true expiring in 30 days
Schedule::command('domains:auto-renew')
    ->daily()
    ->at('08:20')
    ->timezone('UTC');

// Check for expiring domains - run daily to send alerts at 30, 14, and 7 days
Schedule::command('domains:check-expiring')
    ->daily()
    ->at('08:30')
    ->timezone('UTC');

// Check for expiring SSL certificates - run daily to send alerts at 30, 14, 7, 3 days
Schedule::command('domains:check-expiring-ssl')
    ->daily()
    ->at('04:00')
    ->timezone('UTC');

// Weekly subdomain discovery and IP updates - queue jobs spread over the week
// Each domain gets checked once per week, with IP updates 20 minutes after discovery
Schedule::command('domains:queue-subdomain-checks')
    ->weekly()
    ->sundays()
    ->at('06:00')
    ->timezone('UTC');

// Prune old monitoring/history rows to keep tables lean
Schedule::command('domains:prune-monitoring-data')
    ->daily()
    ->at('09:00')
    ->timezone('UTC');

// Prune failed queue jobs older than 14 days (336 hours)
Schedule::command('queue:prune-failed --hours=336')
    ->daily()
    ->at('09:10')
    ->timezone('UTC');

// Prune old job batches older than 14 days (20160 minutes)
Schedule::command('queue:prune-batches --hours=336')
    ->daily()
    ->at('09:20')
    ->timezone('UTC');
