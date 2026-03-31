<?php

use App\Models\WebProperty;
use App\Services\ManualSearchConsoleEvidenceImporter;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('analytics:import-search-console-evidence {property : Web property slug} {path : Path to the Google Search Console page indexing export ZIP} {--captured-by= : Optional captured_by value}', function (ManualSearchConsoleEvidenceImporter $importer) {
    $propertyArgument = $this->argument('property');
    $pathArgument = $this->argument('path');
    $capturedByOption = $this->option('captured-by');

    $property = WebProperty::query()
        ->where('slug', is_string($propertyArgument) ? $propertyArgument : '')
        ->first();

    if (! $property instanceof WebProperty) {
        $this->error('Could not find the requested web property.');

        return Command::FAILURE;
    }

    try {
        $result = $importer->importForProperty(
            $property,
            is_string($pathArgument) ? $pathArgument : '',
            is_string($capturedByOption) && $capturedByOption !== ''
                ? $capturedByOption
                : 'artisan_manual_csv_import'
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $baseline = $result['baseline'];
    $tagRefreshMessage = null;
    $primaryDomain = $property->primaryDomainName();

    if (is_string($primaryDomain) && $primaryDomain !== '') {
        try {
            $tagRefreshExitCode = Artisan::call('coverage:sync-tags', [
                '--domain' => [$primaryDomain],
            ]);

            if ($tagRefreshExitCode !== 0) {
                $tagRefreshMessage = trim(Artisan::output()) ?: 'Coverage tags will refresh on the next scheduled sync.';
            }
        } catch (\Throwable $exception) {
            $tagRefreshMessage = $exception->getMessage();
        }
    }

    $this->info(sprintf(
        'Imported manual Search Console evidence for [%s] and stored baseline [%s].',
        $property->slug,
        $baseline->id
    ));
    $this->line(sprintf('Artifact path: %s', $result['artifact_path']));
    if (is_string($tagRefreshMessage) && $tagRefreshMessage !== '') {
        $this->warn(sprintf('Coverage tags were not refreshed automatically: %s', $tagRefreshMessage));
    } else {
        $this->line('Coverage tags refreshed for the affected domain.');
    }

    return Command::SUCCESS;
})->purpose('Import a Google Search Console page indexing export ZIP and clear a manual CSV backlog item.');

$brainConfigured = filled(config('services.brain.base_url')) && filled(config('services.brain.api_key'));

// Health ping - only schedule when Brain is configured
if ($brainConfigured) {
    Schedule::command('health:ping')
        ->everyFiveMinutes()
        ->timezone('UTC');
}

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

// Domain contacts sync - queue jobs 3 times daily (8:15am, 2:15pm, 8:15pm UTC)
Schedule::command('domains:queue-sync-jobs --type=contacts')
    ->dailyAt('08:15')
    ->timezone('UTC');
Schedule::command('domains:queue-sync-jobs --type=contacts')
    ->dailyAt('14:15')
    ->timezone('UTC');
Schedule::command('domains:queue-sync-jobs --type=contacts')
    ->dailyAt('20:15')
    ->timezone('UTC');

// Auto-renew domains - run daily to renew domains with auto_renew=true expiring within 30 days
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

// Weekly compliance check for .au domains - queue job to check all domains
Schedule::job(new \App\Jobs\CheckComplianceJob)
    ->weekly()
    ->sundays()
    ->at('06:30')
    ->timezone('UTC');

// Prune old monitoring/history rows to keep tables lean
Schedule::command('domains:prune-monitoring-data')
    ->daily()
    ->at('09:00')
    ->timezone('UTC');

// Verify live Matomo tracker installs before automation coverage is refreshed.
Schedule::command('analytics:refresh-matomo-install-audits')
    ->daily()
    ->at('08:50')
    ->timezone('UTC');

// Refresh fleet automation coverage after upstream analytics imports have had time to settle.
Schedule::command('analytics:refresh-automation-coverage')
    ->daily()
    ->at('09:05')
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
