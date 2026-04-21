<?php

namespace App\Services;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Ga4SignalScanner
{
    private const int MAX_SCRIPT_ASSETS = 3;

    /**
     * @return array{
     *   status: 'ok'|'fail'|'skipped',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditPropertyHomepage(WebProperty $property, int $timeout = 10): array
    {
        $expectedMeasurementId = $this->expectedMeasurementId($property->primaryAnalyticsSource('ga4'));
        $candidateUrls = $this->candidateUrlsForProperty($property);

        if ($expectedMeasurementId === null) {
            $probe = $this->firstSuccessfulProbe($candidateUrls, $timeout);

            if ($probe === null) {
                return [
                    'status' => 'fail',
                    'verdict' => 'missing_expected_measurement_id',
                    'summary' => 'Property does not have an active GA4 measurement ID configured in domain-monitor, and no live page could be fetched to verify the current install.',
                    'evidence' => [
                        'verdict' => 'missing_expected_measurement_id',
                        'expected_measurement_id' => null,
                        'detected_measurement_ids' => [],
                        'detected_script_hosts' => [],
                        'best_url' => null,
                        'urls' => $candidateUrls->all(),
                    ],
                ];
            }

            $analysis = $this->analyzeHtml($probe['html'], $probe['url'], $timeout);
            $summary = $analysis['measurement_ids'] === []
                ? 'Property does not have an active GA4 measurement ID configured in domain-monitor, and no GA4 measurement ID was detected on the live page.'
                : 'Property does not have an active GA4 measurement ID configured in domain-monitor.';

            return [
                'status' => 'fail',
                'verdict' => 'missing_expected_measurement_id',
                'summary' => $summary,
                'evidence' => [
                    'verdict' => 'missing_expected_measurement_id',
                    'expected_measurement_id' => null,
                    'detected_measurement_ids' => $analysis['measurement_ids'],
                    'detected_script_hosts' => $analysis['tracker_hosts'],
                    'best_url' => $probe['url'],
                    'urls' => $candidateUrls->all(),
                ],
            ];
        }

        $probe = $this->firstSuccessfulProbe($candidateUrls, $timeout);

        if ($probe === null) {
            return [
                'status' => 'fail',
                'verdict' => 'fetch_failed',
                'summary' => 'Could not fetch any live property URL to verify the GA4 install.',
                'evidence' => [
                    'verdict' => 'fetch_failed',
                    'expected_measurement_id' => $expectedMeasurementId,
                    'urls' => $candidateUrls->all(),
                ],
            ];
        }

        $analysis = $this->analyzeHtml($probe['html'], $probe['url'], $timeout);
        [$verdict, $summary] = $this->verdictForSignalSet(
            $expectedMeasurementId,
            $analysis['measurement_ids']
        );

        return [
            'status' => $verdict === 'installed_match' ? 'ok' : 'fail',
            'verdict' => $verdict,
            'summary' => $summary,
            'evidence' => [
                'verdict' => $verdict,
                'expected_measurement_id' => $expectedMeasurementId,
                'detected_measurement_ids' => $analysis['measurement_ids'],
                'detected_script_hosts' => $analysis['tracker_hosts'],
                'best_url' => $probe['url'],
                'urls' => $candidateUrls->all(),
            ],
        ];
    }

    /**
     * @return array{
     *   status: 'ok'|'fail'|'skipped',
     *   verdict: string,
     *   summary: string,
     *   evidence: array<string, mixed>
     * }
     */
    public function auditConversionSurfaces(WebProperty $property, int $timeout = 10): array
    {
        $surfaces = $this->candidateSurfaces($property);

        if ($surfaces->isEmpty()) {
            return [
                'status' => 'skipped',
                'verdict' => 'no_conversion_surfaces',
                'summary' => 'Property does not currently expose any conversion surfaces with GA4 bindings.',
                'evidence' => [
                    'verdict' => 'no_conversion_surfaces',
                    'failing_surfaces' => [],
                    'surfaces' => [],
                ],
            ];
        }

        $results = $surfaces->map(
            fn (array $surface): array => $this->auditSurface($surface, $timeout)
        )->values();

        $failing = $results
            ->filter(fn (array $result): bool => $result['verdict'] !== 'installed_match')
            ->values();

        if ($failing->isEmpty()) {
            return [
                'status' => 'ok',
                'verdict' => 'installed_match',
                'summary' => 'All conversion surfaces resolve to the expected GA4 measurement ID.',
                'evidence' => [
                    'verdict' => 'installed_match',
                    'failing_surfaces' => [],
                    'surfaces' => $results->all(),
                ],
            ];
        }

        $summary = sprintf(
            '%d conversion surface(s) are not resolving to the expected GA4 measurement ID.',
            $failing->count()
        );

        $primaryVerdict = (string) ($failing->first()['verdict'] ?? 'conversion_surface_mismatch');

        return [
            'status' => 'fail',
            'verdict' => $primaryVerdict,
            'summary' => $summary,
            'evidence' => [
                'verdict' => $primaryVerdict,
                'failing_surfaces' => $failing->all(),
                'surfaces' => $results->all(),
            ],
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function candidateUrlsForProperty(WebProperty $property): Collection
    {
        $urls = [];

        if (is_string($property->production_url) && trim($property->production_url) !== '') {
            $urls[] = trim($property->production_url);
        }

        $primaryDomain = $property->primaryDomainName();
        if (is_string($primaryDomain) && $primaryDomain !== '') {
            $urls[] = 'https://'.$primaryDomain.'/';
        }

        $normalizedUrls = collect($urls)
            ->map(fn (string $url): string => $this->normalizedUrl($url))
            ->filter()
            ->unique()
            ->values();

        /** @var Collection<int, string> $normalizedUrls */
        return $normalizedUrls;
    }

    /**
     * @return Collection<int, array{surface: WebPropertyConversionSurface, expected_measurement_id: string, url: string}>
     */
    private function candidateSurfaces(WebProperty $property): Collection
    {
        $surfaces = $property->relationLoaded('conversionSurfaces')
            ? $property->conversionSurfaces
            : $property->conversionSurfaces()->with(['analyticsSource', 'domain'])->get();

        return $surfaces
            ->loadMissing(['analyticsSource', 'domain'])
            ->map(function (WebPropertyConversionSurface $surface) use ($property): ?array {
                $source = $surface->analytics_binding_mode === 'inherits_property'
                    ? $property->primaryAnalyticsSource('ga4')
                    : $surface->analyticsSource;

                $expectedMeasurementId = $this->expectedMeasurementId($source);
                if ($expectedMeasurementId === null) {
                    return null;
                }

                $hostname = trim($surface->hostname);
                if ($hostname === '') {
                    return null;
                }

                return [
                    'surface' => $surface,
                    'expected_measurement_id' => $expectedMeasurementId,
                    'url' => 'https://'.$hostname.'/',
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array{surface: WebPropertyConversionSurface, expected_measurement_id: string, url: string}  $surface
     * @return array<string, mixed>
     */
    private function auditSurface(array $surface, int $timeout): array
    {
        $probe = $this->firstSuccessfulProbe(collect([$surface['url']]), $timeout);

        if ($probe === null) {
            return [
                'hostname' => $surface['surface']->hostname,
                'verdict' => 'fetch_failed',
                'summary' => 'Could not fetch the conversion surface to verify GA4.',
                'expected_measurement_id' => $surface['expected_measurement_id'],
                'detected_measurement_ids' => [],
                'detected_script_hosts' => [],
                'best_url' => $surface['url'],
            ];
        }

        $analysis = $this->analyzeHtml($probe['html'], $probe['url'], $timeout);
        [$verdict, $summary] = $this->verdictForSignalSet(
            $surface['expected_measurement_id'],
            $analysis['measurement_ids']
        );

        return [
            'hostname' => $surface['surface']->hostname,
            'verdict' => $verdict,
            'summary' => $summary,
            'expected_measurement_id' => $surface['expected_measurement_id'],
            'detected_measurement_ids' => $analysis['measurement_ids'],
            'detected_script_hosts' => $analysis['tracker_hosts'],
            'best_url' => $probe['url'],
        ];
    }

    private function expectedMeasurementId(?PropertyAnalyticsSource $source): ?string
    {
        if (! $source instanceof PropertyAnalyticsSource || $source->status !== 'active') {
            return null;
        }

        $providerConfig = $source->provider_config;
        $measurementId = is_array($providerConfig)
            ? ($providerConfig['measurement_id'] ?? null)
            : null;

        if (! is_string($measurementId) || trim($measurementId) === '') {
            $measurementId = $source->external_id;
        }

        if (trim($measurementId) === '') {
            return null;
        }

        $normalized = strtoupper(trim($measurementId));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @param  array<int, string>  $detectedMeasurementIds
     * @return array{0: string, 1: string}
     */
    private function verdictForSignalSet(string $expectedMeasurementId, array $detectedMeasurementIds): array
    {
        $normalizedDetected = array_values(array_unique(array_map(
            static fn (string $measurementId): string => strtoupper($measurementId),
            array_filter($detectedMeasurementIds, 'is_string')
        )));

        $expectedPresent = in_array($expectedMeasurementId, $normalizedDetected, true);

        if ($normalizedDetected === []) {
            return [
                'missing_ga4',
                'No GA4 measurement ID was detected on the live page.',
            ];
        }

        if (count($normalizedDetected) > 1) {
            return [
                'duplicate_streams',
                sprintf(
                    'Multiple GA4 measurement IDs were detected on the live page: %s.',
                    implode(', ', $normalizedDetected)
                ),
            ];
        }

        if (! $expectedPresent) {
            return [
                'wrong_measurement_id',
                sprintf(
                    'The live page is using [%s] instead of the expected [%s].',
                    $normalizedDetected[0],
                    $expectedMeasurementId
                ),
            ];
        }

        return [
            'installed_match',
            'The live page is using the expected GA4 measurement ID.',
        ];
    }

    /**
     * @param  Collection<int, string>  $urls
     * @return array{url: string, html: string}|null
     */
    private function firstSuccessfulProbe(Collection $urls, int $timeout): ?array
    {
        foreach ($urls as $url) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'User-Agent' => 'domain-monitor-ga4-audit/1.0',
                    ])
                    ->get($url);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $html = (string) $response->body();
            if ($html === '') {
                continue;
            }

            return [
                'url' => $url,
                'html' => $html,
            ];
        }

        return null;
    }

    /**
     * @return array{measurement_ids: array<int, string>, tracker_hosts: array<int, string>}
     */
    private function analyzeHtml(string $html, string $baseUrl, int $timeout): array
    {
        $measurementIds = $this->extractMeasurementIds($html);
        $trackerHosts = $this->extractTrackerHosts($html);
        $scriptUrls = $this->extractScriptUrls($html, $baseUrl);
        $baseHost = parse_url($baseUrl, PHP_URL_HOST);

        foreach ($scriptUrls as $scriptUrl) {
            $scriptHost = parse_url($scriptUrl, PHP_URL_HOST);
            if (! is_string($scriptHost) || ! is_string($baseHost) || Str::lower($scriptHost) !== Str::lower($baseHost)) {
                continue;
            }

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'User-Agent' => 'domain-monitor-ga4-audit/1.0',
                    ])
                    ->get($scriptUrl);
            } catch (\Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $scriptBody = (string) $response->body();
            if ($scriptBody === '') {
                continue;
            }

            $measurementIds = array_values(array_unique(array_merge(
                $measurementIds,
                $this->extractMeasurementIds($scriptBody)
            )));
            $trackerHosts = array_values(array_unique(array_merge(
                $trackerHosts,
                $this->extractTrackerHosts($scriptBody)
            )));
        }

        sort($measurementIds);
        sort($trackerHosts);

        return [
            'measurement_ids' => $measurementIds,
            'tracker_hosts' => $trackerHosts,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function extractMeasurementIds(string $content): array
    {
        preg_match_all('/\bG-[A-Z0-9]{4,}\b/i', $content, $matches);
        $matchedIds = $matches[0];

        return array_values(array_unique(array_map(
            static fn (string $measurementId): string => strtoupper($measurementId),
            $matchedIds
        )));
    }

    /**
     * @return array<int, string>
     */
    private function extractTrackerHosts(string $content): array
    {
        $hosts = [];

        preg_match_all('/https?:\/\/([^\/"\']+)/i', $content, $matches);
        $matchedHosts = $matches[1];

        foreach ($matchedHosts as $host) {
            $normalized = Str::lower(trim($host));
            if (
                str_contains($normalized, 'googletagmanager.com')
                || str_contains($normalized, 'google-analytics.com')
            ) {
                $hosts[] = $normalized;
            }
        }

        return array_values(array_unique($hosts));
    }

    /**
     * @return array<int, string>
     */
    private function extractScriptUrls(string $html, string $baseUrl): array
    {
        preg_match_all('/<script[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);

        $baseParts = parse_url($baseUrl);
        if (! is_array($baseParts) || ! isset($baseParts['scheme'], $baseParts['host'])) {
            return [];
        }

        $origin = strtolower((string) $baseParts['scheme']).'://'.strtolower((string) $baseParts['host']);

        $resolved = collect($matches[1])
            ->filter(fn (string $src): bool => trim($src) !== '')
            ->map(function (string $src) use ($origin, $baseUrl): string {
                $trimmed = trim($src);

                if (str_starts_with($trimmed, '//')) {
                    return 'https:'.$trimmed;
                }

                if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
                    return $trimmed;
                }

                if (str_starts_with($trimmed, '/')) {
                    return $origin.$trimmed;
                }

                $basePath = rtrim(dirname(parse_url($baseUrl, PHP_URL_PATH) ?: '/'), '/');

                return $origin.($basePath !== '' ? $basePath.'/' : '/').ltrim($trimmed, '/');
            })
            ->filter()
            ->unique()
            ->take(self::MAX_SCRIPT_ASSETS)
            ->values();

        return $resolved->all();
    }

    private function normalizedUrl(string $url): ?string
    {
        $trimmed = trim($url);

        if ($trimmed === '') {
            return null;
        }

        if (! str_starts_with(Str::lower($trimmed), 'https://')) {
            return null;
        }

        return str_ends_with($trimmed, '/') ? $trimmed : $trimmed.'/';
    }
}
