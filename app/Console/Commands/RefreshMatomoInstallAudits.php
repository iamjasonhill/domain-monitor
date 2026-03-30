<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class RefreshMatomoInstallAudits extends Command
{
    protected $signature = 'analytics:refresh-matomo-install-audits
                            {--domain= : Optional domain to verify only one linked property}
                            {--timeout=10 : HTTP timeout in seconds for site verification requests}';

    protected $description = 'Verify live Matomo tracker installs for linked properties and import the latest audit state.';

    public function handle(): int
    {
        $domainFilter = $this->normalizeDomain((string) $this->option('domain'));
        $timeout = max(1, (int) $this->option('timeout'));
        $expectedTrackerHost = $this->expectedTrackerHost();

        $sources = PropertyAnalyticsSource::query()
            ->where('provider', 'matomo')
            ->where('status', 'active')
            ->with([
                'webProperty.primaryDomain',
                'webProperty.propertyDomains.domain',
            ])
            ->when(
                $domainFilter,
                fn ($query) => $query->whereHas(
                    'webProperty.propertyDomains.domain',
                    fn ($domainQuery) => $domainQuery->where('domain', $domainFilter)
                )
            )
            ->orderBy('external_name')
            ->get()
            ->filter(function (PropertyAnalyticsSource $source): bool {
                $property = $source->webProperty;

                return $property !== null && $property->matomoEligibility()['eligible'];
            })
            ->values();

        if ($sources->isEmpty()) {
            $this->warn('No eligible Matomo-linked properties found to verify.');

            return self::SUCCESS;
        }

        $payload = [
            'source_system' => 'matamo',
            'contract_version' => 1,
            'report_type' => 'install_verification',
            'generated_at' => now()->toIso8601String(),
            'install_audits' => $sources
                ->map(fn (PropertyAnalyticsSource $source): array => $this->verifySource($source, $expectedTrackerHost, $timeout))
                ->all(),
        ];

        $tempPath = tempnam(sys_get_temp_dir(), 'matomo-install-audit-');
        if (! is_string($tempPath)) {
            $this->error('Could not create a temporary file for the Matomo audit import.');

            return self::FAILURE;
        }

        file_put_contents($tempPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        try {
            $exitCode = Artisan::call('analytics:import-matomo-audit', ['path' => $tempPath]);
            $output = trim(Artisan::output());
        } finally {
            @unlink($tempPath);
        }

        if ($exitCode !== 0) {
            $this->error($output !== '' ? $output : 'Matomo install audit import failed.');

            return self::FAILURE;
        }

        $this->info(sprintf('Verified %d Matomo-linked property/site combination(s).', $sources->count()));

        if ($output !== '') {
            $this->line($output);
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   id_site: string,
     *   site_name: string|null,
     *   expected_tracker_host: string|null,
     *   verdict: string,
     *   best_url: string|null,
     *   detected_site_ids: array<int, string>,
     *   detected_tracker_hosts: array<int, string>,
     *   summary: string,
     *   urls: array<int, string>
     * }
     */
    private function verifySource(PropertyAnalyticsSource $source, ?string $expectedTrackerHost, int $timeout): array
    {
        $property = $source->webProperty;
        $urls = $property ? $this->candidateUrlsFor($property) : collect();
        $bestUrl = $urls->first();
        $html = null;

        foreach ($urls as $url) {
            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'User-Agent' => 'domain-monitor-matomo-audit/1.0',
                    ])
                    ->get($url);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $body = (string) $response->body();
            if ($body === '') {
                continue;
            }

            $bestUrl = $url;
            $html = $body;

            break;
        }

        if (! is_string($html)) {
            return [
                'id_site' => $source->external_id,
                'site_name' => $source->external_name,
                'expected_tracker_host' => $expectedTrackerHost,
                'verdict' => 'fetch_failed',
                'best_url' => $bestUrl,
                'detected_site_ids' => [],
                'detected_tracker_hosts' => [],
                'summary' => 'Could not fetch any candidate URL to verify the Matomo install.',
                'urls' => $urls->all(),
            ];
        }

        $detectedSiteIds = $this->detectedSiteIds($html);
        $detectedTrackerHosts = $this->detectedTrackerHosts($html);
        $hasMatomoSignals = $this->hasMatomoSignals($html);

        [$verdict, $summary] = $this->verdictForDetection(
            expectedSiteId: $source->external_id,
            expectedTrackerHost: $expectedTrackerHost,
            detectedSiteIds: $detectedSiteIds,
            detectedTrackerHosts: $detectedTrackerHosts,
            hasMatomoSignals: $hasMatomoSignals,
        );

        return [
            'id_site' => $source->external_id,
            'site_name' => $source->external_name,
            'expected_tracker_host' => $expectedTrackerHost,
            'verdict' => $verdict,
            'best_url' => $bestUrl,
            'detected_site_ids' => $detectedSiteIds,
            'detected_tracker_hosts' => $detectedTrackerHosts,
            'summary' => $summary,
            'urls' => $urls->all(),
        ];
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function candidateUrlsFor(\App\Models\WebProperty $property): Collection
    {
        $urls = [];

        if (is_string($property->production_url) && trim($property->production_url) !== '') {
            $urls[] = trim($property->production_url);
        }

        $primaryDomain = $property->primaryDomainName();
        if (is_string($primaryDomain) && $primaryDomain !== '') {
            $urls[] = 'https://'.$primaryDomain.'/';
            $urls[] = 'http://'.$primaryDomain.'/';
        }

        return collect($urls)
            ->map(fn (string $url): string => trim($url))
            ->filter(fn (string $url): bool => $url !== '')
            ->unique()
            ->values();
    }

    /**
     * @param  array<int, string>  $detectedSiteIds
     * @param  array<int, string>  $detectedTrackerHosts
     * @return array{0: string, 1: string}
     */
    private function verdictForDetection(
        string $expectedSiteId,
        ?string $expectedTrackerHost,
        array $detectedSiteIds,
        array $detectedTrackerHosts,
        bool $hasMatomoSignals
    ): array {
        $siteIdMatch = in_array($expectedSiteId, $detectedSiteIds, true);
        $trackerHostMatch = $expectedTrackerHost !== null && in_array($expectedTrackerHost, $detectedTrackerHosts, true);

        if ($siteIdMatch && ($expectedTrackerHost === null || $trackerHostMatch)) {
            return [
                'installed_match',
                'Matomo snippet detected with the expected tracker host and site ID.',
            ];
        }

        if ($expectedTrackerHost !== null && $detectedTrackerHosts !== [] && ! $trackerHostMatch) {
            return [
                'installed_other_tracker_host',
                'Tracking code was found, but it points at a different Matomo tracker host.',
            ];
        }

        if ($detectedSiteIds !== [] && ! $siteIdMatch) {
            return [
                'installed_wrong_site_id',
                'Tracking code was found, but the detected Matomo site ID does not match the linked property.',
            ];
        }

        if ($hasMatomoSignals) {
            return [
                'partial_detection',
                'Some Matomo signals were found, but the expected tracker host and site ID could not be fully confirmed.',
            ];
        }

        return [
            'not_detected',
            'No Matomo snippet signals were detected in the fetched source.',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function detectedSiteIds(string $html): array
    {
        $matches = [];
        $siteIds = [];

        preg_match_all(
            "/_paq\.push\(\s*\[\s*['\"]setSiteId['\"]\s*,\s*['\"]?(\d+)['\"]?\s*\]\s*\)/i",
            $html,
            $matches
        );

        $siteIds = array_merge($siteIds, $matches[1]);

        preg_match_all(
            "/\b(?:setSiteId|idSite)\b\s*[:(=,]\s*['\"]?(\d+)['\"]?/i",
            $html,
            $matches
        );

        $siteIds = array_merge($siteIds, $matches[1]);

        return collect($siteIds)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function detectedTrackerHosts(string $html): array
    {
        $matches = [];

        preg_match_all(
            '~https?://([a-z0-9.-]+)/(?:(?:matomo|piwik)\.php|(?:matomo|piwik)\.js)~i',
            $html,
            $matches
        );

        return collect($matches[1])
            ->map(fn ($value): string => mb_strtolower((string) $value))
            ->unique()
            ->values()
            ->all();
    }

    private function hasMatomoSignals(string $html): bool
    {
        return str_contains($html, '_paq')
            || str_contains(mb_strtolower($html), 'matomo.js')
            || str_contains(mb_strtolower($html), 'piwik.js');
    }

    private function expectedTrackerHost(): ?string
    {
        $host = parse_url((string) config('services.matomo.base_url', ''), PHP_URL_HOST);

        return is_string($host) && trim($host) !== '' ? mb_strtolower(trim($host)) : null;
    }

    private function normalizeDomain(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized !== '' ? $normalized : null;
    }
}
