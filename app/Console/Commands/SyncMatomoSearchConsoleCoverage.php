<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncMatomoSearchConsoleCoverage extends Command
{
    protected $signature = 'analytics:sync-search-console-coverage
                            {--domain= : Optional domain to sync only one matched Matomo site}';

    protected $description = 'Sync Search Console mapping coverage from Matomo into domain-monitor.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) config('services.matomo.base_url', ''), '/');
        $tokenAuth = (string) config('services.matomo.token_auth', '');

        if ($baseUrl === '' || $tokenAuth === '') {
            $this->error('Matomo API credentials are not configured. Set MATOMO_BASE_URL and MATOMO_TOKEN_AUTH.');

            return self::FAILURE;
        }

        $domainFilter = $this->normalizeDomain((string) $this->option('domain'));

        /** @var Response $response */
        $response = Http::acceptJson()
            ->timeout(60)
            ->asForm()
            ->post($baseUrl.'/index.php', [
                'module' => 'API',
                'method' => 'SearchConsoleIntegration.exportCoverage',
                'format' => 'JSON',
                'token_auth' => $tokenAuth,
            ]);

        $response->throw();

        $payload = $response->json();

        if (! is_array($payload) || ($payload['source_system'] ?? null) !== 'matomo_search_console') {
            $this->error('Matomo did not return a valid Search Console coverage payload.');

            return self::FAILURE;
        }

        if (($payload['contract_version'] ?? null) !== 1) {
            $this->error('Unsupported Search Console coverage contract version.');

            return self::FAILURE;
        }

        $sites = $payload['sites'] ?? null;
        if (! is_array($sites)) {
            $this->error('Coverage payload is missing sites.');

            return self::FAILURE;
        }

        $matched = 0;
        $skipped = 0;

        foreach ($sites as $site) {
            if (! is_array($site)) {
                continue;
            }

            $matomoSiteId = $this->stringOrNull($site['id_site'] ?? null);
            if (! $matomoSiteId) {
                continue;
            }

            [$domain, $source] = $this->resolveContext($matomoSiteId);

            if (! $domain instanceof Domain || ! $source instanceof PropertyAnalyticsSource) {
                $skipped++;

                continue;
            }

            if ($domainFilter && $domain->domain !== $domainFilter) {
                continue;
            }

            SearchConsoleCoverageStatus::query()->updateOrCreate(
                [
                    'source_provider' => 'matomo',
                    'matomo_site_id' => $matomoSiteId,
                ],
                [
                    'domain_id' => $domain->id,
                    'web_property_id' => $source->web_property_id,
                    'property_analytics_source_id' => $source->id,
                    'matomo_site_name' => $this->stringOrNull($site['site_name'] ?? null),
                    'matomo_main_url' => $this->stringOrNull($site['main_url'] ?? null),
                    'mapping_state' => (string) ($site['mapping_state'] ?? 'not_mapped'),
                    'property_uri' => $this->stringOrNull($site['property_uri'] ?? null),
                    'property_type' => $this->stringOrNull($site['property_type'] ?? null),
                    'mapped_at' => $this->timestamp($site['mapped_at'] ?? null),
                    'latest_completed_job_at' => $this->timestamp($site['latest_completed_job_at'] ?? null),
                    'latest_completed_job_type' => $this->stringOrNull($site['latest_completed_job_type'] ?? null),
                    'latest_completed_range_end' => $site['latest_completed_range_end'] ?? null,
                    'latest_metric_date' => $site['latest_metric_date'] ?? null,
                    'checked_at' => $this->timestamp($payload['generated_at'] ?? null) ?? now(),
                    'raw_payload' => $site,
                ]
            );

            $matched++;
        }

        $message = sprintf('Synced %d Search Console coverage record(s).', $matched);
        $this->info($message);

        if ($skipped > 0) {
            $this->warn(sprintf('%d Matomo site(s) could not be matched to a linked matomo analytics source.', $skipped));
        }

        if ($domainFilter && $matched === 0) {
            $this->warn(sprintf('No Search Console coverage record was synced for [%s].', $domainFilter));
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: Domain|null, 1: PropertyAnalyticsSource|null}
     */
    private function resolveContext(string $matomoSiteId): array
    {
        if (
            ! Schema::hasTable('domains')
            || ! Schema::hasTable('web_properties')
            || ! Schema::hasTable('web_property_domains')
            || ! Schema::hasTable('property_analytics_sources')
        ) {
            return [null, null];
        }

        $source = PropertyAnalyticsSource::query()
            ->with(['webProperty.primaryDomain', 'webProperty.propertyDomains.domain'])
            ->where('provider', 'matomo')
            ->where('external_id', $matomoSiteId)
            ->first();

        if (! $source instanceof PropertyAnalyticsSource || ! $source->webProperty) {
            return [null, null];
        }

        $domain = $source->webProperty->primaryDomainModel();

        return [$domain, $source];
    }

    private function normalizeDomain(?string $value): ?string
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

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
