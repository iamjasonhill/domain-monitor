<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Collection;
use RuntimeException;

class SearchConsoleApiBundleCollector
{
    public function __construct(
        private readonly GoogleSearchConsoleClient $client,
        private readonly SearchConsoleIssueEvidenceService $issueEvidenceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collectBundleForProperty(
        WebProperty $property,
        int $days = 28,
        ?int $urlLimit = null,
        ?int $analyticsRowLimit = null
    ): array {
        $siteUrl = $property->searchConsolePropertyUri();

        if (! is_string($siteUrl) || $siteUrl === '') {
            throw new RuntimeException('This property does not have a Search Console property URI.');
        }

        $evidenceMap = $this->issueEvidenceService
            ->evidenceMapForProperties(new Collection([$property]))[$property->slug] ?? [];

        if ($evidenceMap === []) {
            throw new RuntimeException('This property does not have any current Search Console issue evidence to enrich.');
        }

        $issueClasses = array_keys($evidenceMap);

        $analyticsRowLimit ??= max(1, (int) config('services.google.search_console.analytics_row_limit', 250));
        $urlLimit ??= max(1, (int) config('services.google.search_console.inspection_url_limit', 10));
        $inspectionDelayMicros = max(0, (int) config('services.google.search_console.inspection_request_delay_micros', 200000));

        $baseline = $property->latestPropertySeoBaselineRecord();
        $endDate = $baseline?->date_range_end?->toDateString() ?: now()->subDay()->toDateString();
        $startDate = $baseline?->date_range_start?->toDateString() ?: now()->subDays(max(1, $days))->toDateString();

        $sitemaps = $this->normalizeSitemaps($this->client->listSitemaps($siteUrl));
        $searchAnalytics = $this->normalizeSearchAnalytics(
            $this->client->querySearchAnalytics($siteUrl, $startDate, $endDate, $analyticsRowLimit),
            $startDate,
            $endDate
        );

        $issueEvidence = [];

        foreach ($issueClasses as $issueClass) {
            $evidence = $evidenceMap[$issueClass] ?? [];
            $urls = $this->urlsForIssueEvidence($evidence, $urlLimit);
            $inspectionRows = [];

            foreach ($urls as $url) {
                $inspectionRows[] = $this->normalizeInspectionResult(
                    $url,
                    $this->client->inspectUrl($siteUrl, $url)
                );

                if ($inspectionDelayMicros > 0) {
                    usleep($inspectionDelayMicros);
                }
            }

            $issueEvidence[$issueClass] = array_filter([
                'source_issue_label' => data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label'),
                'url_inspection' => $inspectionRows !== []
                    ? [
                        'inspected_urls' => $inspectionRows,
                        'summary' => $this->inspectionSummary($inspectionRows),
                    ]
                    : null,
                'referring_urls' => $this->referringUrlsFromInspections($inspectionRows),
                'canonical_state' => $this->canonicalStateFromInspections($inspectionRows),
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return [
            'source_report' => 'search_console_api_bundle',
            'source_property' => $siteUrl,
            'shared' => array_filter([
                'sitemaps' => $sitemaps !== [] ? $sitemaps : null,
                'search_analytics' => $searchAnalytics,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            'issue_evidence' => $issueEvidence,
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<int, string>
     */
    private function urlsForIssueEvidence(array $evidence, int $limit): array
    {
        $urls = [];

        foreach (['affected_urls', 'sample_urls'] as $key) {
            $values = $evidence[$key] ?? null;

            if (! is_array($values)) {
                continue;
            }

            foreach ($values as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        $examples = $evidence['examples'] ?? null;
        if (is_array($examples)) {
            foreach ($examples as $example) {
                $url = is_array($example) ? ($example['url'] ?? null) : null;
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, $limit);
    }

    /**
     * @param  array<int, array<string, mixed>>  $sitemaps
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSitemaps(array $sitemaps): array
    {
        return array_values(array_map(
            static fn (array $sitemap): array => array_filter([
                'path' => $sitemap['path'] ?? null,
                'last_downloaded' => $sitemap['lastDownloaded'] ?? null,
                'warnings' => is_numeric($sitemap['warnings'] ?? null) ? (int) $sitemap['warnings'] : null,
                'errors' => is_numeric($sitemap['errors'] ?? null) ? (int) $sitemap['errors'] : null,
                'is_pending' => is_bool($sitemap['isPending'] ?? null) ? $sitemap['isPending'] : null,
                'is_sitemaps_index' => is_bool($sitemap['isSitemapsIndex'] ?? null) ? $sitemap['isSitemapsIndex'] : null,
                'type' => $sitemap['type'] ?? null,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            array_filter($sitemaps, 'is_array')
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeSearchAnalytics(array $payload, string $startDate, string $endDate): array
    {
        $rows = array_values(array_map(
            static fn (array $row): array => [
                'page' => is_array($row['keys'] ?? null) ? ($row['keys'][0] ?? null) : null,
                'clicks' => is_numeric($row['clicks'] ?? null) ? (float) $row['clicks'] : null,
                'impressions' => is_numeric($row['impressions'] ?? null) ? (float) $row['impressions'] : null,
                'ctr' => is_numeric($row['ctr'] ?? null) ? (float) $row['ctr'] : null,
                'position' => is_numeric($row['position'] ?? null) ? (float) $row['position'] : null,
            ],
            array_filter(is_array($payload['rows'] ?? null) ? $payload['rows'] : [], 'is_array')
        ));

        $totals = [
            'clicks' => array_sum(array_map(static fn (array $row): float => (float) ($row['clicks'] ?? 0), $rows)),
            'impressions' => array_sum(array_map(static fn (array $row): float => (float) ($row['impressions'] ?? 0), $rows)),
        ];

        return [
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'totals' => $totals,
            'top_pages' => array_values(array_filter($rows, static fn (array $row): bool => is_string($row['page'] ?? null))),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeInspectionResult(string $url, array $payload): array
    {
        /** @var array<string, mixed> $result */
        $result = is_array($payload['inspectionResult'] ?? null) ? $payload['inspectionResult'] : [];
        /** @var array<string, mixed> $indexStatus */
        $indexStatus = is_array($result['indexStatusResult'] ?? null) ? $result['indexStatusResult'] : [];

        return array_filter([
            'url' => $url,
            'coverage_state' => $indexStatus['coverageState'] ?? null,
            'robots_txt_state' => $indexStatus['robotsTxtState'] ?? null,
            'indexing_state' => $indexStatus['indexingState'] ?? null,
            'page_fetch_state' => $indexStatus['pageFetchState'] ?? null,
            'last_crawl_time' => $indexStatus['lastCrawlTime'] ?? null,
            'google_canonical' => $indexStatus['googleCanonical'] ?? null,
            'user_canonical' => $indexStatus['userCanonical'] ?? null,
            'referring_urls' => is_array($indexStatus['referringUrls'] ?? null) ? array_values(array_filter($indexStatus['referringUrls'], 'is_string')) : null,
            'sitemaps' => is_array($indexStatus['sitemap'] ?? null)
                ? array_values(array_filter($indexStatus['sitemap'], 'is_string'))
                : (is_string($indexStatus['sitemap'] ?? null) ? [(string) $indexStatus['sitemap']] : null),
            'verdict' => $result['inspectionResultLink'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     * @return array<string, array<string, int>>
     */
    private function inspectionSummary(array $inspections): array
    {
        $summary = [];

        foreach ([
            'coverage_state' => 'coverage_states',
            'robots_txt_state' => 'robots_txt_states',
            'indexing_state' => 'indexing_states',
            'page_fetch_state' => 'page_fetch_states',
        ] as $sourceKey => $summaryKey) {
            $counts = [];

            foreach ($inspections as $inspection) {
                $value = $inspection[$sourceKey] ?? null;
                if (! is_string($value) || $value === '') {
                    continue;
                }
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }

            if ($counts !== []) {
                $summary[$summaryKey] = $counts;
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     * @return array<int, string>|null
     */
    private function referringUrlsFromInspections(array $inspections): ?array
    {
        $urls = [];

        foreach ($inspections as $inspection) {
            foreach (($inspection['referring_urls'] ?? []) as $url) {
                if (is_string($url) && $url !== '') {
                    $urls[] = $url;
                }
            }
        }

        $urls = array_values(array_unique($urls));

        return $urls !== [] ? $urls : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $inspections
     * @return array<string, mixed>|null
     */
    private function canonicalStateFromInspections(array $inspections): ?array
    {
        $pairs = [];
        $hasMismatch = false;

        foreach ($inspections as $inspection) {
            $googleCanonical = $inspection['google_canonical'] ?? null;
            $userCanonical = $inspection['user_canonical'] ?? null;

            if (! is_string($googleCanonical) && ! is_string($userCanonical)) {
                continue;
            }

            $pairs[] = array_filter([
                'url' => $inspection['url'] ?? null,
                'google_canonical' => $googleCanonical,
                'user_canonical' => $userCanonical,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            if (is_string($googleCanonical) && is_string($userCanonical) && $googleCanonical !== $userCanonical) {
                $hasMismatch = true;
            }
        }

        if ($pairs === []) {
            return null;
        }

        return [
            'pairs' => $pairs,
            'has_mismatch' => $hasMismatch,
        ];
    }
}
