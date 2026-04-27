<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SyncMmGoogleSearchConsoleExport extends Command
{
    protected $signature = 'analytics:sync-mm-google-search-console-export
                            {path : Path to the MM-Google Search Console replacement export JSON}
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Import the MM-Google Search Console coverage/baseline export into Domain Monitor.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error(sprintf('Export file not found at [%s].', $path));

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            $this->error('Export file does not contain valid JSON.');

            return self::FAILURE;
        }

        if (($payload['sourceSystem'] ?? null) !== 'mm-google') {
            $this->error('Expected an MM-Google export payload.');

            return self::FAILURE;
        }

        if (($payload['contract'] ?? null) !== 'search-console-coverage-baseline-v1') {
            $this->error('Unsupported MM-Google Search Console export contract.');

            return self::FAILURE;
        }

        $coverageRecords = $payload['coverageRecords'] ?? null;
        $baselineRecords = $payload['baselineRecords'] ?? null;

        if (! is_array($coverageRecords) || ! is_array($baselineRecords)) {
            $this->error('MM-Google export payload is missing coverageRecords or baselineRecords.');

            return self::FAILURE;
        }

        $coverageImported = 0;
        $coverageSkipped = 0;
        $baselineImported = 0;
        $baselineSkipped = 0;

        foreach ($coverageRecords as $record) {
            if (! is_array($record)) {
                continue;
            }

            $webProperty = $this->resolveProperty($record);

            if (! $webProperty instanceof WebProperty) {
                $coverageSkipped++;
                $this->warn(sprintf(
                    'Could not match coverage record to a web property. site_key=%s website_url=%s',
                    $this->stringOrNull($record['siteKey'] ?? null) ?? 'null',
                    $this->stringOrNull($record['websiteUrl'] ?? null) ?? 'null',
                ));

                continue;
            }

            $source = $this->ga4SourceForProperty($webProperty);
            $checkedAt = $this->timestamp($record['lastCheckedAt'] ?? $payload['generatedAt'] ?? null);
            $mappedState = $this->mappedStateForRecord($record);
            $propertyType = $this->stringOrNull($record['expectedPropertyType'] ?? null);
            $propertyUri = $this->stringOrNull($record['matchedPropertyIdentifier'] ?? null)
                ?? $this->stringOrNull($record['expectedPropertyIdentifier'] ?? null);

            $attributes = [
                'domain_id' => $this->domainForProperty($webProperty)?->id,
                'web_property_id' => $webProperty->id,
                'property_analytics_source_id' => $source?->id,
                'source_provider' => 'mm-google',
                'matomo_site_id' => $this->stringOrNull($record['siteKey'] ?? null) ?? $webProperty->slug,
                'matomo_site_name' => $this->stringOrNull($record['displayName'] ?? null) ?? $webProperty->name,
                'matomo_main_url' => $this->stringOrNull($record['websiteUrl'] ?? null) ?? $webProperty->production_url,
                'mapping_state' => $mappedState,
                'property_uri' => $propertyUri,
                'property_type' => $propertyType,
                'mapped_at' => $checkedAt,
                'latest_completed_job_at' => $checkedAt,
                'latest_completed_job_type' => 'mm_google_export',
                'latest_completed_range_end' => $checkedAt->toDateString(),
                'latest_metric_date' => $checkedAt->toDateString(),
                'checked_at' => $checkedAt,
                'raw_payload' => $record,
            ];

            if ($dryRun) {
                $coverageImported++;
                $this->line(sprintf(
                    '[dry-run coverage] %s <- %s',
                    $webProperty->slug,
                    $record['coverageStatus'] ?? 'unknown'
                ));

                continue;
            }

            SearchConsoleCoverageStatus::query()->updateOrCreate(
                [
                    'source_provider' => 'mm-google',
                    'matomo_site_id' => $attributes['matomo_site_id'],
                ],
                $attributes
            );

            $coverageImported++;
        }

        foreach ($baselineRecords as $record) {
            if (! is_array($record)) {
                continue;
            }

            $webProperty = $this->resolveProperty($record);

            if (! $webProperty instanceof WebProperty) {
                $baselineSkipped++;
                $this->warn(sprintf(
                    'Could not match baseline record to a web property. site_key=%s website_url=%s',
                    $this->stringOrNull($record['siteKey'] ?? null) ?? 'null',
                    $this->stringOrNull($record['websiteUrl'] ?? null) ?? 'null',
                ));

                continue;
            }

            $source = $this->ga4SourceForProperty($webProperty);
            $capturedAt = $this->timestamp($record['lastCheckedAt'] ?? $payload['generatedAt'] ?? null);

            $attributes = [
                'domain_id' => $this->domainForProperty($webProperty)?->id,
                'web_property_id' => $webProperty->id,
                'property_analytics_source_id' => $source?->id,
                'baseline_type' => 'search_console_control_plane',
                'captured_at' => $capturedAt,
                'captured_by' => 'mm-google-export',
                'source_provider' => 'mm-google',
                'matomo_site_id' => $this->stringOrNull($record['siteKey'] ?? null) ?? $webProperty->slug,
                'search_console_property_uri' => $this->stringOrNull($record['propertyIdentifier'] ?? null),
                'search_type' => 'web',
                'date_range_start' => null,
                'date_range_end' => null,
                'import_method' => 'mm_google_export',
                'artifact_path' => null,
                'clicks' => 0,
                'impressions' => 0,
                'ctr' => 0,
                'average_position' => 0,
                'indexed_pages' => null,
                'not_indexed_pages' => null,
                'pages_with_redirect' => null,
                'not_found_404' => null,
                'blocked_by_robots' => null,
                'alternate_with_canonical' => null,
                'crawled_currently_not_indexed' => null,
                'discovered_currently_not_indexed' => null,
                'duplicate_without_user_selected_canonical' => null,
                'top_pages_count' => null,
                'top_queries_count' => null,
                'inspected_url_count' => null,
                'inspection_indexed_url_count' => null,
                'inspection_non_indexed_url_count' => null,
                'amp_urls' => null,
                'mobile_issue_urls' => null,
                'rich_result_urls' => null,
                'rich_result_issue_urls' => null,
                'notes' => sprintf(
                    'MM-Google replacement export %s (%s).',
                    $record['baselineStatus'] ?? 'unknown',
                    $record['readinessStatus'] ?? 'unknown',
                ),
                'raw_payload' => $record,
            ];

            if ($dryRun) {
                $baselineImported++;
                $this->line(sprintf(
                    '[dry-run baseline] %s <- %s',
                    $webProperty->slug,
                    $record['baselineStatus'] ?? 'unknown'
                ));

                continue;
            }

            DomainSeoBaseline::query()->updateOrCreate(
                [
                    'domain_id' => $attributes['domain_id'],
                    'baseline_type' => $attributes['baseline_type'],
                    'captured_at' => $attributes['captured_at'],
                    'source_provider' => 'mm-google',
                    'matomo_site_id' => $attributes['matomo_site_id'],
                ],
                $attributes
            );

            $baselineImported++;
        }

        $this->newLine();
        $this->info('MM-Google Search Console export sync summary');
        $this->line(sprintf('Coverage records imported: %d', $coverageImported));
        $this->line(sprintf('Coverage records skipped: %d', $coverageSkipped));
        $this->line(sprintf('Baseline records imported: %d', $baselineImported));
        $this->line(sprintf('Baseline records skipped: %d', $baselineSkipped));

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No changes were written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function resolveProperty(array $record): ?WebProperty
    {
        $host = $this->hostFromUrl($record['websiteUrl'] ?? null);
        $siteKey = $this->stringOrNull($record['siteKey'] ?? null);

        if ($host === null && $siteKey === null) {
            return null;
        }

        return WebProperty::query()
            ->with(['primaryDomain', 'analyticsSources'])
            ->where(function ($query) use ($host, $siteKey): void {
                if ($host !== null) {
                    $query->whereHas('domains', fn ($domainQuery) => $domainQuery->where('domain', $host))
                        ->orWhereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('domain', $host))
                        ->orWhere('production_url', 'like', 'https://'.$host.'%')
                        ->orWhere('production_url', 'like', 'http://'.$host.'%');
                }

                if ($siteKey !== null) {
                    $query->orWhere('site_key', $siteKey)
                        ->orWhere('slug', $siteKey);
                }
            })
            ->orderByDesc('priority')
            ->first();
    }

    private function domainForProperty(WebProperty $property): ?Domain
    {
        return $property->primaryDomainModel();
    }

    private function ga4SourceForProperty(WebProperty $property): ?PropertyAnalyticsSource
    {
        return $property->primaryAnalyticsSource('ga4');
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function mappedStateForRecord(array $record): string
    {
        $coverageStatus = $this->stringOrNull($record['coverageStatus'] ?? null);
        $expectedType = $this->stringOrNull($record['expectedPropertyType'] ?? null);

        return match ($coverageStatus) {
            'search_console_ready' => $expectedType === 'url-prefix' ? 'url_prefix' : 'domain_property',
            'search_console_wrong_property_type' => 'url_prefix',
            default => $expectedType === 'url-prefix' ? 'url_prefix' : ($expectedType === 'domain' ? 'domain_property' : 'not_mapped'),
        };
    }

    private function hostFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return Str::lower($host);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function timestamp(mixed $value): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return now();
    }
}
