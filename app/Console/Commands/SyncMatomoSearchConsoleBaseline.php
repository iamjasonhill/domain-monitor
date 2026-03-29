<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SyncMatomoSearchConsoleBaseline extends Command
{
    protected $signature = 'analytics:sync-search-console-baseline
                            {--domain= : Domain name to sync from Matomo}
                            {--baseline-type=manual_checkpoint : Baseline type to store}
                            {--start= : Start date (YYYY-MM-DD), defaults to 90 days ending yesterday}
                            {--end= : End date (YYYY-MM-DD), defaults to yesterday}
                            {--captured-by=domain-monitor : Captured by label}
                            {--notes= : Optional notes to attach to the imported baseline}';

    protected $description = 'Export the latest Search Console baseline from Matomo for one domain and import it into domain-monitor.';

    public function handle(): int
    {
        $domainName = $this->normalizeDomain((string) $this->option('domain'));

        if (! $domainName) {
            $this->error('Please provide --domain=<domain>.');

            return self::FAILURE;
        }

        $domain = Domain::query()->where('domain', $domainName)->first();

        if (! $domain instanceof Domain) {
            $this->error(sprintf('Domain [%s] was not found.', $domainName));

            return self::FAILURE;
        }

        $source = $this->resolveMatomoSource($domain);

        if (! is_array($source)) {
            $this->error(sprintf('Domain [%s] does not have a Matomo analytics binding.', $domain->domain));

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) config('services.matomo.base_url', ''), '/');
        $tokenAuth = (string) config('services.matomo.token_auth', '');

        if ($baseUrl === '' || $tokenAuth === '') {
            $this->error('Matomo API credentials are not configured. Set MATOMO_BASE_URL and MATOMO_TOKEN_AUTH.');

            return self::FAILURE;
        }

        [$startDate, $endDate] = $this->resolveDateRange(
            $this->stringOrNull($this->option('start')),
            $this->stringOrNull($this->option('end')),
        );

        $artifactPath = sprintf(
            'domain-monitor/search-console-baselines/%s/%s/',
            str_replace('.', '-', $domain->domain),
            now()->toDateString(),
        );

        /** @var \Illuminate\Http\Client\Response $response */
        $response = Http::acceptJson()
            ->timeout(60)
            ->get($baseUrl.'/index.php', [
                'module' => 'API',
                'method' => 'SearchConsoleIntegration.exportBaseline',
                'format' => 'JSON',
                'token_auth' => $tokenAuth,
                'idSite' => $source['external_id'],
                'startDate' => $startDate,
                'endDate' => $endDate,
                'baselineType' => (string) $this->option('baseline-type'),
                'capturedBy' => (string) $this->option('captured-by'),
                'artifactPath' => $artifactPath,
                'notes' => $this->stringOrNull($this->option('notes')),
            ]);

        $response->throw();

        $baseline = $response->json();

        if (! is_array($baseline) || ($baseline['domain'] ?? null) === null) {
            $this->error('Matomo did not return a valid Search Console baseline payload.');

            return self::FAILURE;
        }

        $payload = [
            'source_system' => 'matamo_search_console',
            'contract_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'baselines' => [$baseline],
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'sc-baseline-sync-');
        if (! is_string($tempPath)) {
            $this->error('Could not create a temporary file for the baseline import.');

            return self::FAILURE;
        }

        file_put_contents($tempPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $exitCode = Artisan::call('analytics:import-search-console-baseline', ['path' => $tempPath]);
            $output = trim(Artisan::output());
        } finally {
            @unlink($tempPath);
        }

        if ($exitCode !== 0) {
            $this->error($output !== '' ? $output : 'Import command failed.');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Synced Search Console baseline for %s (%s to %s).',
            $domain->domain,
            $startDate,
            $endDate,
        ));

        if ($output !== '') {
            $this->line($output);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{external_id: string, external_name: string|null, workspace_path: string|null}|null
     */
    private function resolveMatomoSource(Domain $domain): ?array
    {
        if (
            Schema::hasTable('web_properties')
            && Schema::hasTable('web_property_domains')
            && Schema::hasTable('property_analytics_sources')
        ) {
            $source = $domain->webProperties()
                ->with('analyticsSources')
                ->get()
                ->flatMap(fn ($property) => $property->analyticsSources)
                ->first(fn (PropertyAnalyticsSource $candidate): bool => $candidate->provider === 'matomo');

            if ($source instanceof PropertyAnalyticsSource) {
                return [
                    'external_id' => $source->external_id,
                    'external_name' => $source->external_name,
                    'workspace_path' => $source->workspace_path,
                ];
            }
        }

        $overrides = (array) config('domain_monitor.web_property_bootstrap.overrides', []);
        $override = (array) ($overrides[$domain->domain] ?? []);
        $sources = (array) ($override['analytics_sources'] ?? []);

        foreach ($sources as $source) {
            if (($source['provider'] ?? null) !== 'matomo') {
                continue;
            }

            return [
                'external_id' => (string) ($source['external_id'] ?? ''),
                'external_name' => $this->stringOrNull($source['external_name'] ?? null),
                'workspace_path' => $this->stringOrNull($source['workspace_path'] ?? null),
            ];
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveDateRange(?string $startDate, ?string $endDate): array
    {
        $end = $endDate ? Carbon::parse($endDate) : now()->subDay()->startOfDay();
        $start = $startDate ? Carbon::parse($startDate) : $end->copy()->subDays(89);

        return [$start->toDateString(), $end->toDateString()];
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
}
