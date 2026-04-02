<?php

use App\Models\Domain;
use App\Models\DomainTag;
use App\Models\WebProperty;
use App\Services\ManualSearchConsoleEvidenceImporter;
use App\Services\PropertyConversionLinkScanner;
use App\Services\SearchConsoleApiBundleCollector;
use App\Services\SearchConsoleApiBundleImporter;
use App\Services\SearchConsoleApiEnrichmentRefresher;
use App\Services\SearchConsoleIssueSnapshotImporter;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fleet:sync-focus-domains {--dry-run : Only report the changes that would be made}', function () {
    $tagName = (string) config('domain_monitor.fleet_focus.tag_name', 'fleet.live');
    /** @var array<int, string> $domainNames */
    $domainNames = array_values(array_filter(
        (array) config('domain_monitor.fleet_focus.domains', []),
        fn (mixed $domain): bool => is_string($domain) && trim($domain) !== ''
    ));
    $dryRun = (bool) $this->option('dry-run');

    if ($tagName === '' || $domainNames === []) {
        $this->warn('Fleet focus tag configuration is empty.');

        return Command::INVALID;
    }

    $tag = DomainTag::withTrashed()->firstOrCreate(
        ['name' => $tagName],
        [
            'priority' => 95,
            'color' => '#2563EB',
            'description' => 'Fleet-managed live domains used for the dedicated Fleet working set view.',
        ]
    );

    if ($tag->trashed()) {
        $tag->restore();
    }

    $domains = Domain::query()
        ->whereIn('domain', $domainNames)
        ->get()
        ->keyBy('domain');

    $missingDomains = collect($domainNames)
        ->reject(fn (string $domain) => $domains->has($domain))
        ->values();

    $attachIds = $domains
        ->reject(fn (Domain $domain) => $domain->tags()->where('domain_tags.id', $tag->id)->exists())
        ->pluck('id')
        ->values();

    if ($dryRun) {
        $this->info(sprintf(
            'Would attach [%d] domains to [%s]. Missing [%d] configured domains.',
            $attachIds->count(),
            $tagName,
            $missingDomains->count()
        ));

        if ($attachIds->isNotEmpty()) {
            $this->line('Attach: '.implode(', ', $domains->whereIn('id', $attachIds)->keys()->all()));
        }

        if ($missingDomains->isNotEmpty()) {
            $this->warn('Missing: '.implode(', ', $missingDomains->all()));
        }

        return Command::SUCCESS;
    }

    foreach ($attachIds as $domainId) {
        $domain = $domains->firstWhere('id', $domainId);

        if ($domain instanceof Domain) {
            $domain->tags()->syncWithoutDetaching([$tag->id]);
        }
    }

    $this->info(sprintf(
        'Fleet focus sync complete. Attached [%d] domains to [%s].',
        $attachIds->count(),
        $tagName
    ));

    if ($missingDomains->isNotEmpty()) {
        $this->warn('Configured domains not found: '.implode(', ', $missingDomains->all()));
    }

    return Command::SUCCESS;
})->purpose('Attach the configured Fleet focus tag to the current Fleet domain list.');

Artisan::command('fleet:scan-conversion-links {propertySlug? : Optional web property slug} {--dry-run : Report the links without persisting them}', function (PropertyConversionLinkScanner $scanner) {
    $propertySlug = $this->argument('propertySlug');
    $dryRun = (bool) $this->option('dry-run');

    $query = WebProperty::query()
        ->with(['primaryDomain', 'propertyDomains.domain']);

    if (is_string($propertySlug) && $propertySlug !== '') {
        $query->where('slug', $propertySlug);
    } else {
        $query->fleetFocus();
    }

    $properties = $query
        ->orderBy('name')
        ->get();

    if ($properties->isEmpty()) {
        $this->warn('No web properties matched the conversion-link scan scope.');

        return Command::INVALID;
    }

    $scanned = 0;
    $failed = 0;

    foreach ($properties as $property) {
        try {
            $scan = $scanner->scanForProperty($property);

            if (! $dryRun) {
                $property->forceFill($scan)->save();
            }

            $this->info(sprintf(
                '[%s] household quote=%s | household booking=%s | vehicle quote=%s | vehicle booking=%s',
                $property->slug,
                $scan['current_household_quote_url'] ?? 'n/a',
                $scan['current_household_booking_url'] ?? 'n/a',
                $scan['current_vehicle_quote_url'] ?? 'n/a',
                $scan['current_vehicle_booking_url'] ?? 'n/a'
            ));
            $scanned++;
        } catch (\Throwable $exception) {
            $this->warn(sprintf('[%s] scan failed: %s', $property->slug, $exception->getMessage()));
            $failed++;
        }
    }

    $this->line(sprintf(
        'Conversion link scan complete. Scanned [%d], failed [%d]%s.',
        $scanned,
        $failed,
        $dryRun ? ' (dry run)' : ''
    ));

    return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
})->purpose('Scan live homepage navigation for current quote and booking links on Fleet properties.');

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

Artisan::command('analytics:import-search-console-issue-detail {property : Web property slug} {path : Path to the Google Search Console issue-detail drilldown ZIP} {--captured-by= : Optional captured_by value}', function (SearchConsoleIssueSnapshotImporter $importer) {
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
        $result = $importer->importDrilldownZipForProperty(
            $property,
            is_string($pathArgument) ? $pathArgument : '',
            is_string($capturedByOption) && $capturedByOption !== ''
                ? $capturedByOption
                : 'artisan_issue_detail_import'
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $snapshot = $result['snapshot'];

    $this->info(sprintf(
        'Imported Search Console issue detail for [%s] issue [%s] and stored snapshot [%s].',
        $property->slug,
        $snapshot->issue_class,
        $snapshot->id
    ));
    $this->line(sprintf('Artifact path: %s', $result['artifact_path']));

    return Command::SUCCESS;
})->purpose('Import a Google Search Console issue-detail drilldown ZIP for one property.');

Artisan::command('analytics:import-search-console-api-evidence {property : Web property slug} {issueClass : Normalized issue class} {path : Path to the Search Console API or MCP JSON payload} {--capture-method=gsc_api : Either gsc_api or gsc_mcp_api} {--captured-by= : Optional captured_by value}', function (SearchConsoleIssueSnapshotImporter $importer) {
    $propertyArgument = $this->argument('property');
    $issueClassArgument = $this->argument('issueClass');
    $pathArgument = $this->argument('path');
    $captureMethodOption = $this->option('capture-method');
    $capturedByOption = $this->option('captured-by');

    $property = WebProperty::query()
        ->where('slug', is_string($propertyArgument) ? $propertyArgument : '')
        ->first();

    if (! $property instanceof WebProperty) {
        $this->error('Could not find the requested web property.');

        return Command::FAILURE;
    }

    try {
        $result = $importer->importApiEvidenceForProperty(
            $property,
            is_string($issueClassArgument) ? $issueClassArgument : '',
            is_string($pathArgument) ? $pathArgument : '',
            is_string($captureMethodOption) && $captureMethodOption !== ''
                ? $captureMethodOption
                : 'gsc_api',
            is_string($capturedByOption) && $capturedByOption !== ''
                ? $capturedByOption
                : 'artisan_api_issue_import'
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $snapshot = $result['snapshot'];

    $this->info(sprintf(
        'Imported Search Console API evidence for [%s] issue [%s] and stored snapshot [%s].',
        $property->slug,
        $snapshot->issue_class,
        $snapshot->id
    ));
    $this->line(sprintf('Artifact path: %s', $result['artifact_path']));

    return Command::SUCCESS;
})->purpose('Import Search Console API or MCP evidence for one issue class on one property.');

Artisan::command('analytics:import-search-console-api-bundle {property : Web property slug} {path : Path to the Search Console API or MCP JSON bundle} {--capture-method=gsc_api : Either gsc_api or gsc_mcp_api} {--captured-by= : Optional captured_by value}', function (SearchConsoleApiBundleImporter $importer) {
    $propertyArgument = $this->argument('property');
    $pathArgument = $this->argument('path');
    $captureMethodOption = $this->option('capture-method');
    $capturedByOption = $this->option('captured-by');

    $property = WebProperty::query()
        ->where('slug', is_string($propertyArgument) ? $propertyArgument : '')
        ->first();

    if (! $property instanceof WebProperty) {
        $this->error('Could not find the requested web property.');

        return Command::FAILURE;
    }

    try {
        $result = $importer->importBundleForProperty(
            $property,
            is_string($pathArgument) ? $pathArgument : '',
            is_string($captureMethodOption) && $captureMethodOption !== ''
                ? $captureMethodOption
                : 'gsc_api',
            is_string($capturedByOption) && $capturedByOption !== ''
                ? $capturedByOption
                : 'artisan_api_bundle_import'
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    $this->info(sprintf(
        'Imported Search Console API bundle for [%s] and stored [%d] issue snapshots.',
        $property->slug,
        count($result['snapshots'])
    ));
    $this->line(sprintf('Artifact path: %s', $result['artifact_path']));
    $this->line(sprintf('Issue classes: %s', implode(', ', $result['imported_issue_classes'])));

    return Command::SUCCESS;
})->purpose('Import a Search Console API or MCP bundle and fan it out into per-issue snapshots.');

Artisan::command('analytics:collect-search-console-api-bundle {property : Web property slug} {--capture-method=gsc_api : Either gsc_api or gsc_mcp_api} {--days=28 : Lookback window for Search Analytics totals} {--url-limit= : Override the max inspected URLs per issue class} {--row-limit= : Override the Search Analytics row limit} {--captured-by= : Optional captured_by value} {--dry-run : Only collect and print a summary without importing}', function (SearchConsoleApiBundleCollector $collector, SearchConsoleApiBundleImporter $importer) {
    $propertyArgument = $this->argument('property');
    $captureMethodOption = $this->option('capture-method');
    $daysOption = $this->option('days');
    $urlLimitOption = $this->option('url-limit');
    $rowLimitOption = $this->option('row-limit');
    $capturedByOption = $this->option('captured-by');
    $dryRunOption = (bool) $this->option('dry-run');

    $property = WebProperty::query()
        ->where('slug', is_string($propertyArgument) ? $propertyArgument : '')
        ->first();

    if (! $property instanceof WebProperty) {
        $this->error('Could not find the requested web property.');

        return Command::FAILURE;
    }

    try {
        $bundle = $collector->collectBundleForProperty(
            $property,
            is_numeric($daysOption) ? (int) $daysOption : 28,
            is_numeric($urlLimitOption) ? (int) $urlLimitOption : null,
            is_numeric($rowLimitOption) ? (int) $rowLimitOption : null,
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    if ($dryRunOption) {
        $this->line(json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        return Command::SUCCESS;
    }

    $temporaryPath = tempnam(sys_get_temp_dir(), 'gsc-api-bundle-');
    if (! is_string($temporaryPath)) {
        $this->error('Unable to create a temporary Search Console API bundle file.');

        return Command::FAILURE;
    }

    try {
        $bytesWritten = file_put_contents($temporaryPath, json_encode($bundle, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        if ($bytesWritten === false) {
            throw new RuntimeException('Unable to write the temporary Search Console API bundle file.');
        }

        $result = $importer->importBundleForProperty(
            $property,
            $temporaryPath,
            is_string($captureMethodOption) && $captureMethodOption !== ''
                ? $captureMethodOption
                : 'gsc_api',
            is_string($capturedByOption) && $capturedByOption !== ''
                ? $capturedByOption
                : 'artisan_api_bundle_collect'
        );
    } catch (\Throwable $exception) {
        @unlink($temporaryPath);
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    @unlink($temporaryPath);

    $this->info(sprintf(
        'Collected and imported Search Console API bundle for [%s]; stored [%d] issue snapshots.',
        $property->slug,
        count($result['snapshots'])
    ));
    $this->line(sprintf('Artifact path: %s', $result['artifact_path']));
    $this->line(sprintf('Issue classes: %s', implode(', ', $result['imported_issue_classes'])));

    return Command::SUCCESS;
})->purpose('Collect Search Console API evidence for one property and import it as a per-issue bundle.');

Artisan::command('analytics:refresh-search-console-api-enrichment {--capture-method=gsc_api : Either gsc_api or gsc_mcp_api} {--days=28 : Lookback window for Search Analytics totals} {--url-limit= : Override the max inspected URLs per issue class} {--row-limit= : Override the Search Analytics row limit} {--stale-days= : Refresh properties whose latest API enrichment is older than this many days} {--limit= : Max properties to refresh in one run} {--captured-by= : Optional captured_by value} {--dry-run : Only list the properties that would refresh}', function (SearchConsoleApiEnrichmentRefresher $refresher) {
    $captureMethodOption = $this->option('capture-method');
    $daysOption = $this->option('days');
    $urlLimitOption = $this->option('url-limit');
    $rowLimitOption = $this->option('row-limit');
    $staleDaysOption = $this->option('stale-days');
    $limitOption = $this->option('limit');
    $capturedByOption = $this->option('captured-by');
    $dryRunOption = (bool) $this->option('dry-run');

    $staleDays = is_numeric($staleDaysOption)
        ? (int) $staleDaysOption
        : max(1, (int) config('services.google.search_console.api_refresh_stale_days', 7));
    $batchLimit = is_numeric($limitOption)
        ? (int) $limitOption
        : max(1, (int) config('services.google.search_console.api_refresh_batch_limit', 3));
    $captureMethod = is_string($captureMethodOption) && $captureMethodOption !== ''
        ? $captureMethodOption
        : 'gsc_api';

    if (! in_array($captureMethod, ['gsc_api', 'gsc_mcp_api'], true)) {
        $this->error(sprintf(
            'Invalid --capture-method "%s". Allowed values: gsc_api, gsc_mcp_api.',
            $captureMethod
        ));

        return Command::FAILURE;
    }

    try {
        $result = $refresher->run(
            $staleDays,
            $batchLimit,
            is_numeric($daysOption) ? (int) $daysOption : 28,
            is_numeric($urlLimitOption) ? (int) $urlLimitOption : null,
            is_numeric($rowLimitOption) ? (int) $rowLimitOption : null,
            $captureMethod,
            is_string($capturedByOption) && $capturedByOption !== '' ? $capturedByOption : null,
            $dryRunOption,
        );
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());

        return Command::FAILURE;
    }

    if ($result['candidate_count'] === 0) {
        $this->info('No eligible properties currently need Search Console API enrichment refresh.');

        return Command::SUCCESS;
    }

    foreach ($result['properties'] as $propertyResult) {
        if ($dryRunOption) {
            $this->line(sprintf(
                '[dry-run] %s (%d issue snapshots; latest api: %s)',
                $propertyResult['property_slug'],
                $propertyResult['issue_count'],
                $propertyResult['latest_api_captured_at'] ?? 'never'
            ));

            continue;
        }

        $this->line(sprintf(
            'Refreshed %s (%d issue snapshots) -> %s',
            $propertyResult['property_slug'],
            $propertyResult['issue_count'],
            $propertyResult['artifact_path'] ?? 'artifact path unavailable'
        ));
    }

    foreach ($result['errors'] as $error) {
        $this->warn(sprintf('%s: %s', $error['property_slug'], $error['message']));
    }

    $this->info(sprintf(
        '%s %d of %d candidate properties for Search Console API enrichment.',
        $dryRunOption ? 'Listed' : 'Refreshed',
        $result['processed_count'],
        $result['candidate_count']
    ));

    return $result['errors'] === []
        ? Command::SUCCESS
        : Command::FAILURE;
})->purpose('Refresh missing or stale Search Console API enrichment for drilldown-backed properties.');

$brainConfigured = filled(config('services.brain.base_url')) && filled(config('services.brain.api_key'));
$googleSearchConsoleConfigured = filled(config('services.google.search_console.access_token'))
    || (
        filled(config('services.google.search_console.refresh_token'))
        && filled(config('services.google.search_console.client_id'))
        && filled(config('services.google.search_console.client_secret'))
    );

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

// Refresh a small batch of stale or missing official Search Console API enrichment after analytics coverage settles.
if ($googleSearchConsoleConfigured) {
    Schedule::command('analytics:refresh-search-console-api-enrichment')
        ->daily()
        ->at('09:15')
        ->timezone('UTC');
}

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
