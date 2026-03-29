<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ImportMatomoSearchConsoleBaseline extends Command
{
    protected $signature = 'analytics:import-search-console-baseline
                            {path : Path to the Matomo Search Console baseline JSON export}';

    protected $description = 'Import a Matomo Search Console baseline export and attach milestone SEO snapshots to domains.';

    public function handle(): int
    {
        $path = (string) $this->argument('path');

        if (! is_file($path)) {
            $this->error(sprintf('Baseline file not found at [%s].', $path));

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload)) {
            $this->error('Baseline file does not contain valid JSON.');

            return self::FAILURE;
        }

        if (($payload['source_system'] ?? null) !== 'matamo_search_console') {
            $this->error('Expected a Matomo Search Console baseline export.');

            return self::FAILURE;
        }

        if (($payload['contract_version'] ?? null) !== 1) {
            $this->error('Unsupported Search Console baseline contract version.');

            return self::FAILURE;
        }

        $baselines = $payload['baselines'] ?? null;

        if (! is_array($baselines)) {
            $this->error('Baseline payload is missing baselines.');

            return self::FAILURE;
        }

        $imported = 0;
        $skipped = 0;

        foreach ($baselines as $baseline) {
            if (! is_array($baseline)) {
                continue;
            }

            $domainName = $this->normalizeDomain($baseline['domain'] ?? null);
            $matomoSiteId = $this->stringOrNull($baseline['matomo_site_id'] ?? null);

            $domain = $this->resolveDomain($domainName, $matomoSiteId);

            if (! $domain instanceof Domain) {
                $this->warn(sprintf('Could not match baseline to a domain. domain=%s, matomo_site_id=%s', $domainName ?? 'null', $matomoSiteId ?? 'null'));
                $skipped++;

                continue;
            }

            [$webProperty, $analyticsSource] = $this->resolvePropertyContext($domain, $matomoSiteId);

            DomainSeoBaseline::query()->updateOrCreate(
                [
                    'domain_id' => $domain->id,
                    'baseline_type' => (string) ($baseline['baseline_type'] ?? 'manual_checkpoint'),
                    'captured_at' => $this->timestamp($baseline['captured_at'] ?? null),
                    'source_provider' => (string) ($baseline['source_provider'] ?? 'matomo'),
                    'matomo_site_id' => $matomoSiteId,
                ],
                [
                    'web_property_id' => $webProperty?->id,
                    'property_analytics_source_id' => $analyticsSource?->id,
                    'captured_by' => $this->stringOrNull($baseline['captured_by'] ?? null),
                    'search_console_property_uri' => $this->stringOrNull($baseline['search_console_property_uri'] ?? null),
                    'search_type' => (string) ($baseline['search_type'] ?? 'web'),
                    'date_range_start' => $baseline['date_range_start'] ?? null,
                    'date_range_end' => $baseline['date_range_end'] ?? null,
                    'import_method' => (string) ($baseline['import_method'] ?? 'matomo_api'),
                    'artifact_path' => $this->stringOrNull($baseline['artifact_path'] ?? null),
                    'clicks' => (float) ($baseline['clicks'] ?? 0),
                    'impressions' => (float) ($baseline['impressions'] ?? 0),
                    'ctr' => (float) ($baseline['ctr'] ?? 0),
                    'average_position' => (float) ($baseline['average_position'] ?? 0),
                    'indexed_pages' => $this->nullableInt($baseline['indexed_pages'] ?? null),
                    'not_indexed_pages' => $this->nullableInt($baseline['not_indexed_pages'] ?? null),
                    'pages_with_redirect' => $this->nullableInt($baseline['pages_with_redirect'] ?? null),
                    'not_found_404' => $this->nullableInt($baseline['not_found_404'] ?? null),
                    'blocked_by_robots' => $this->nullableInt($baseline['blocked_by_robots'] ?? null),
                    'alternate_with_canonical' => $this->nullableInt($baseline['alternate_with_canonical'] ?? null),
                    'crawled_currently_not_indexed' => $this->nullableInt($baseline['crawled_currently_not_indexed'] ?? null),
                    'discovered_currently_not_indexed' => $this->nullableInt($baseline['discovered_currently_not_indexed'] ?? null),
                    'duplicate_without_user_selected_canonical' => $this->nullableInt($baseline['duplicate_without_user_selected_canonical'] ?? null),
                    'top_pages_count' => $this->nullableInt($baseline['top_pages_count'] ?? null),
                    'top_queries_count' => $this->nullableInt($baseline['top_queries_count'] ?? null),
                    'inspected_url_count' => $this->nullableInt($baseline['inspected_url_count'] ?? null),
                    'inspection_indexed_url_count' => $this->nullableInt($baseline['inspection_indexed_url_count'] ?? null),
                    'inspection_non_indexed_url_count' => $this->nullableInt($baseline['inspection_non_indexed_url_count'] ?? null),
                    'amp_urls' => $this->nullableInt($baseline['amp_urls'] ?? null),
                    'mobile_issue_urls' => $this->nullableInt($baseline['mobile_issue_urls'] ?? null),
                    'rich_result_urls' => $this->nullableInt($baseline['rich_result_urls'] ?? null),
                    'rich_result_issue_urls' => $this->nullableInt($baseline['rich_result_issue_urls'] ?? null),
                    'notes' => $this->stringOrNull($baseline['notes'] ?? null),
                    'raw_payload' => is_array($baseline['raw_payload'] ?? null) ? $baseline['raw_payload'] : null,
                ]
            );

            $imported++;
        }

        $this->info(sprintf('Imported %d Search Console baseline record(s).', $imported));

        if ($skipped > 0) {
            $this->warn(sprintf('%d baseline record(s) could not be matched to a domain.', $skipped));
        }

        return self::SUCCESS;
    }

    private function resolveDomain(?string $domainName, ?string $matomoSiteId): ?Domain
    {
        if ($domainName) {
            return Domain::query()
                ->where('domain', $domainName)
                ->first();
        }

        if (! $matomoSiteId) {
            return null;
        }

        $source = PropertyAnalyticsSource::query()
            ->where('provider', 'matomo')
            ->where('external_id', $matomoSiteId)
            ->with('webProperty.domains')
            ->first();

        return $source?->webProperty?->primaryDomainModel()
            ?? $source?->webProperty?->domains->first();
    }

    /**
     * @return array{0: WebProperty|null, 1: PropertyAnalyticsSource|null}
     */
    private function resolvePropertyContext(Domain $domain, ?string $matomoSiteId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, WebProperty> $properties */
        $properties = $domain->webProperties()->with('analyticsSources')->get();

        if ($matomoSiteId) {
            foreach ($properties as $property) {
                $source = $property->analyticsSources
                    ->first(fn (PropertyAnalyticsSource $candidate): bool => $candidate->provider === 'matomo' && $candidate->external_id === $matomoSiteId);

                if ($source instanceof PropertyAnalyticsSource) {
                    return [$property, $source];
                }
            }
        }

        $property = $properties->first();
        if (! $property instanceof WebProperty) {
            return [null, null];
        }

        $source = $property->analyticsSources
            ->first(fn (PropertyAnalyticsSource $candidate): bool => $candidate->provider === 'matomo');

        return [$property, $source instanceof PropertyAnalyticsSource ? $source : null];
    }

    private function normalizeDomain(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtolower(trim($value));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function timestamp(mixed $value): Carbon
    {
        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return now();
    }
}
