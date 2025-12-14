<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly platform detection for all active domains
Schedule::command('domains:detect-platforms --all')
    ->weekly()
    ->sundays()
    ->at('02:00')
    ->timezone('UTC');

// Weekly hosting detection for all active domains
Schedule::command('domains:detect-hosting --all')
    ->weekly()
    ->sundays()
    ->at('02:30')
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

// Synergy Wholesale expiry sync - run daily for .com.au domains
Schedule::command('domains:sync-synergy-expiry --all')
    ->daily()
    ->at('04:00')
    ->timezone('UTC');

// DNS records sync - run daily for .com.au domains
Schedule::command('domains:sync-dns-records --all')
    ->daily()
    ->at('04:30')
    ->timezone('UTC');
