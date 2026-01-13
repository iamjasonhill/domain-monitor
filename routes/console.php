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

// Synergy Wholesale expiry sync - run daily for Australian TLD domains
Schedule::command('domains:sync-synergy-expiry --all')
    ->daily()
    ->at('08:00')
    ->timezone('UTC');

// DNS records sync - run daily for Australian TLD domains
Schedule::command('domains:sync-dns-records --all')
    ->daily()
    ->at('08:10')
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
