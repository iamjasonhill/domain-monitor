<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class ManualSearchConsoleEvidenceImporter
{
    private const MAX_ARCHIVE_BYTES = 5_242_880;

    private const MAX_ENTRY_BYTES = 1_048_576;

    /**
     * @return array{baseline: DomainSeoBaseline, artifact_path: string, parsed: array<string, mixed>}
     */
    public function importForProperty(WebProperty $property, string $archivePath, ?string $capturedBy = null): array
    {
        if (! is_file($archivePath)) {
            throw new InvalidArgumentException(sprintf('Evidence archive not found at [%s].', $archivePath));
        }

        $this->assertArchiveWithinLimits($archivePath);

        $automation = $property->automationCoverageSummary();
        if ($automation['status'] !== 'manual_csv_pending') {
            throw new InvalidArgumentException('This property is not currently waiting on manual Search Console CSV evidence.');
        }

        $domain = $property->primaryDomainModel();
        if (! $domain instanceof Domain) {
            throw new InvalidArgumentException('The property does not have a primary domain.');
        }

        $matomoSource = $property->primaryAnalyticsSource('matomo');
        if (! $matomoSource instanceof PropertyAnalyticsSource) {
            throw new InvalidArgumentException('The property does not have a primary Matomo source.');
        }

        $latestBaseline = $property->latestPropertySeoBaselineRecord();
        if (! $latestBaseline instanceof DomainSeoBaseline) {
            throw new InvalidArgumentException('The property does not have an existing SEO baseline to enrich.');
        }

        $parsed = $this->parseArchive($archivePath);
        $coverage = $matomoSource->latestSearchConsoleCoverage()->first();
        $artifactPath = $this->storeArtifact($property, $archivePath);

        try {
            $baseline = DB::transaction(function () use (
                $capturedBy,
                $coverage,
                $domain,
                $latestBaseline,
                $matomoSource,
                $parsed,
                $property,
                $archivePath,
                $artifactPath
            ): DomainSeoBaseline {
                return DomainSeoBaseline::query()->create([
                    'domain_id' => $domain->id,
                    'web_property_id' => $property->id,
                    'property_analytics_source_id' => $matomoSource->id,
                    'baseline_type' => $latestBaseline->baseline_type ?: 'search_console',
                    'captured_at' => now(),
                    'captured_by' => $capturedBy ?: 'manual_csv_import',
                    'source_provider' => $latestBaseline->source_provider ?: 'matomo',
                    'matomo_site_id' => $matomoSource->external_id,
                    'search_console_property_uri' => $coverage instanceof SearchConsoleCoverageStatus
                        ? $coverage->property_uri
                        : $latestBaseline->search_console_property_uri,
                    'search_type' => $latestBaseline->search_type ?: 'web',
                    // Keep the original Matomo/API window on the enriched baseline.
                    'date_range_start' => $latestBaseline->date_range_start,
                    'date_range_end' => $latestBaseline->date_range_end,
                    'import_method' => 'matomo_plus_manual_csv',
                    'artifact_path' => $artifactPath,
                    'clicks' => $latestBaseline->clicks,
                    'impressions' => $latestBaseline->impressions,
                    'ctr' => $latestBaseline->ctr,
                    'average_position' => $latestBaseline->average_position,
                    'indexed_pages' => $parsed['indexed_pages'],
                    'not_indexed_pages' => $parsed['not_indexed_pages'],
                    'pages_with_redirect' => $parsed['pages_with_redirect'],
                    'not_found_404' => $parsed['not_found_404'],
                    'blocked_by_robots' => $parsed['blocked_by_robots'],
                    'alternate_with_canonical' => $parsed['alternate_with_canonical'],
                    'crawled_currently_not_indexed' => $parsed['crawled_currently_not_indexed'],
                    'discovered_currently_not_indexed' => $parsed['discovered_currently_not_indexed'],
                    'duplicate_without_user_selected_canonical' => $parsed['duplicate_without_user_selected_canonical'],
                    'top_pages_count' => $latestBaseline->top_pages_count,
                    'top_queries_count' => $latestBaseline->top_queries_count,
                    'inspected_url_count' => $latestBaseline->inspected_url_count,
                    'inspection_indexed_url_count' => $latestBaseline->inspection_indexed_url_count,
                    'inspection_non_indexed_url_count' => $latestBaseline->inspection_non_indexed_url_count,
                    'amp_urls' => $latestBaseline->amp_urls,
                    'mobile_issue_urls' => $latestBaseline->mobile_issue_urls,
                    'rich_result_urls' => $latestBaseline->rich_result_urls,
                    'rich_result_issue_urls' => $latestBaseline->rich_result_issue_urls,
                    'notes' => $this->buildNotes($latestBaseline->notes, $archivePath, $parsed),
                    'raw_payload' => [
                        'source_system' => 'google_search_console_page_indexing_export',
                        'imported_from' => basename($archivePath),
                        'parsed_export' => $parsed['raw_payload'],
                        'page_indexing_window' => [
                            'date_range_start' => $parsed['date_range_start']?->toDateString(),
                            'date_range_end' => $parsed['date_range_end']?->toDateString(),
                        ],
                        'previous_baseline_id' => $latestBaseline->id,
                    ],
                ]);
            });
        } catch (\Throwable $exception) {
            if ($artifactPath !== '') {
                Storage::disk('local')->delete($artifactPath);
            }

            throw $exception;
        }

        return [
            'baseline' => $baseline,
            'artifact_path' => $artifactPath,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{
     *   date_range_start: Carbon|null,
     *   date_range_end: Carbon|null,
     *   indexed_pages: int|null,
     *   not_indexed_pages: int|null,
     *   pages_with_redirect: int|null,
     *   not_found_404: int|null,
     *   blocked_by_robots: int|null,
     *   alternate_with_canonical: int|null,
     *   crawled_currently_not_indexed: int|null,
     *   discovered_currently_not_indexed: int|null,
     *   duplicate_without_user_selected_canonical: int|null,
     *   raw_payload: array<string, mixed>
     * }
     */
    public function parseArchive(string $archivePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open Search Console evidence archive.');
        }

        $chartRows = $this->parseCsvString($this->zipEntryContents($zip, 'Chart.csv'));
        $criticalRows = $this->parseCsvString($this->zipEntryContents($zip, 'Critical issues.csv'));
        $nonCriticalRows = $this->parseCsvString($this->zipEntryContents($zip, 'Non-critical issues.csv', false) ?? '');
        $metadataRows = $this->parseCsvString($this->zipEntryContents($zip, 'Metadata.csv', false) ?? '');

        $zip->close();

        $chartRows = array_values(array_filter($chartRows, fn (array $row): bool => $row['Date'] !== ''));
        if ($chartRows === []) {
            throw new RuntimeException('The Search Console export does not contain chart data.');
        }

        $latestChartRow = end($chartRows);
        $issueRows = [...$criticalRows, ...$nonCriticalRows];

        return [
            'date_range_start' => $this->parseDate($chartRows[0]['Date']),
            'date_range_end' => $this->parseDate($latestChartRow['Date']),
            'indexed_pages' => $this->nullableInt($latestChartRow['Indexed'] ?? null),
            'not_indexed_pages' => $this->nullableInt($latestChartRow['Not indexed'] ?? null),
            'pages_with_redirect' => $this->issueCount($issueRows, ['Page with redirect']),
            'not_found_404' => $this->issueCount($issueRows, ['Not found (404)']),
            'blocked_by_robots' => $this->issueCount($issueRows, ['Blocked by robots.txt']),
            'alternate_with_canonical' => $this->issueCount($issueRows, ['Alternative page with proper canonical tag']),
            'crawled_currently_not_indexed' => $this->issueCount($issueRows, ['Crawled - currently not indexed']),
            'discovered_currently_not_indexed' => $this->issueCount($issueRows, ['Discovered - currently not indexed']),
            'duplicate_without_user_selected_canonical' => $this->issueCount($issueRows, ['Duplicate without user-selected canonical']),
            'raw_payload' => [
                'chart' => $chartRows,
                'critical_issues' => $criticalRows,
                'non_critical_issues' => $nonCriticalRows,
                'metadata' => $metadataRows,
            ],
        ];
    }

    private function storeArtifact(WebProperty $property, string $archivePath): string
    {
        $extension = pathinfo($archivePath, PATHINFO_EXTENSION);
        $safeExtension = $extension !== '' ? '.'.Str::lower($extension) : '';
        $filename = now()->format('Ymd-His').'-'.Str::slug(pathinfo($archivePath, PATHINFO_FILENAME)).$safeExtension;
        $relativePath = 'search-console-manual-evidence/'.$property->slug.'/'.$filename;
        $stream = fopen($archivePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read Search Console evidence archive.');
        }

        try {
            $written = Storage::disk('local')->writeStream($relativePath, $stream);
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            throw new RuntimeException('Unable to store Search Console evidence archive.');
        }

        return $relativePath;
    }

    private function zipEntryContents(ZipArchive $zip, string $entryName, bool $required = true): ?string
    {
        $stats = $zip->statName($entryName);
        if (is_array($stats) && (int) $stats['size'] > self::MAX_ENTRY_BYTES) {
            throw new RuntimeException(sprintf('The Search Console export entry [%s] exceeds the supported size limit.', $entryName));
        }

        $contents = $zip->getFromName($entryName);

        if (is_string($contents)) {
            return $contents;
        }

        if ($required) {
            throw new RuntimeException(sprintf('The Search Console export is missing [%s].', $entryName));
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseCsvString(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/u', '', $contents) ?? $contents;
        $lines = preg_split('/\\r\\n|\\n|\\r/', trim($contents));

        if (! is_array($lines) || $lines === [] || trim((string) $lines[0]) === '') {
            return [];
        }

        $headers = array_map(
            static fn ($header): string => trim((string) $header),
            str_getcsv((string) array_shift($lines))
        );
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $values = str_getcsv($line);
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @param  array<int, string>  $reasons
     */
    private function issueCount(array $rows, array $reasons): ?int
    {
        $reasonMap = array_flip(array_map([$this, 'normalizeReason'], $reasons));

        foreach ($rows as $row) {
            $reason = $this->normalizeReason($row['Reason'] ?? null);

            if (isset($reasonMap[$reason])) {
                return $this->nullableInt($row['Pages'] ?? null) ?? 0;
            }
        }

        return null;
    }

    private function normalizeReason(?string $reason): string
    {
        return Str::of((string) $reason)
            ->lower()
            ->replace(['‘', '’', '“', '”'], ["'", "'", '"', '"'])
            ->squish()
            ->toString();
    }

    private function parseDate(?string $date): ?Carbon
    {
        if (! is_string($date) || trim($date) === '') {
            return null;
        }

        return Carbon::parse($date);
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function buildNotes(?string $existingNotes, string $archivePath, array $parsed): string
    {
        $segments = array_filter([
            $existingNotes,
            sprintf('Manual Search Console evidence imported from %s.', basename($archivePath)),
            isset($parsed['date_range_end']) && $parsed['date_range_end'] instanceof Carbon
                ? sprintf('Page indexing snapshot current to %s.', $parsed['date_range_end']->toDateString())
                : null,
        ]);

        return implode(' ', $segments);
    }

    private function assertArchiveWithinLimits(string $archivePath): void
    {
        $size = filesize($archivePath);

        if ($size === false) {
            throw new InvalidArgumentException('Unable to determine Search Console evidence archive size.');
        }

        if ($size > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException('The Search Console evidence archive exceeds the 5 MB upload limit.');
        }
    }
}
