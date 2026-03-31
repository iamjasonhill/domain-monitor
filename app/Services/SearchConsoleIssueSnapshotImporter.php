<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class SearchConsoleIssueSnapshotImporter
{
    private const MAX_ARCHIVE_BYTES = 5_242_880;

    private const MAX_ENTRY_BYTES = 1_048_576;

    private const MAX_JSON_BYTES = 2_097_152;

    /**
     * @return array{snapshot: SearchConsoleIssueSnapshot, artifact_path: string, parsed: array<string, mixed>}
     */
    public function importDrilldownZipForProperty(WebProperty $property, string $archivePath, ?string $capturedBy = null): array
    {
        if (! is_file($archivePath)) {
            throw new InvalidArgumentException(sprintf('Issue detail archive not found at [%s].', $archivePath));
        }

        $this->assertArchiveWithinLimits($archivePath);
        $parsed = $this->parseDrilldownArchive($archivePath);

        $artifactPath = $this->storeArtifact($property, $archivePath, 'search-console-issue-evidence');
        $snapshot = $this->persistSnapshot(
            $property,
            $parsed['issue_class'],
            [
                'source_issue_label' => $parsed['source_issue_label'],
                'capture_method' => 'gsc_drilldown_zip',
                'source_report' => 'search_console_page_indexing_drilldown',
                'source_property' => $this->searchConsolePropertyForProperty($property),
                'artifact_path' => $artifactPath,
                'captured_at' => now(),
                'captured_by' => $capturedBy ?: 'manual_issue_detail_import',
                'first_detected_at' => $parsed['first_detected_at'],
                'last_updated_at' => $parsed['last_updated_at'],
                'property_scope' => $parsed['property_scope'],
                'affected_url_count' => $parsed['affected_url_count'],
                'sample_urls' => $parsed['sample_urls'],
                'examples' => $parsed['examples'],
                'chart_points' => $parsed['chart_points'],
                'normalized_payload' => [
                    'affected_urls' => $parsed['affected_urls'],
                ],
                'raw_payload' => $parsed['raw_payload'],
            ]
        );

        return [
            'snapshot' => $snapshot,
            'artifact_path' => $artifactPath,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{snapshot: SearchConsoleIssueSnapshot, artifact_path: string, parsed: array<string, mixed>}
     */
    public function importApiEvidenceForProperty(
        WebProperty $property,
        string $issueClass,
        string $jsonPath,
        string $captureMethod = 'gsc_api',
        ?string $capturedBy = null
    ): array {
        if (! in_array($captureMethod, ['gsc_api', 'gsc_mcp_api'], true)) {
            throw new InvalidArgumentException('Capture method must be gsc_api or gsc_mcp_api.');
        }

        if (! is_file($jsonPath)) {
            throw new InvalidArgumentException(sprintf('Issue evidence payload not found at [%s].', $jsonPath));
        }

        if (filesize($jsonPath) > self::MAX_JSON_BYTES) {
            throw new InvalidArgumentException('Issue evidence payload exceeds the supported size limit.');
        }

        $parsed = $this->parseApiEvidenceJson($issueClass, $jsonPath, $captureMethod);
        $artifactPath = $this->storeArtifact($property, $jsonPath, 'search-console-api-evidence');
        $snapshot = $this->persistSnapshot(
            $property,
            $issueClass,
            [
                'source_issue_label' => $parsed['source_issue_label'],
                'capture_method' => $captureMethod,
                'source_report' => $parsed['source_report'],
                'source_property' => $parsed['source_property'] ?: $this->searchConsolePropertyForProperty($property),
                'artifact_path' => $artifactPath,
                'captured_at' => now(),
                'captured_by' => $capturedBy ?: 'manual_api_issue_import',
                'first_detected_at' => $parsed['first_detected_at'],
                'last_updated_at' => $parsed['last_updated_at'],
                'property_scope' => $parsed['property_scope'],
                'affected_url_count' => $parsed['affected_url_count'],
                'sample_urls' => $parsed['sample_urls'],
                'examples' => $parsed['examples'],
                'chart_points' => $parsed['chart_points'],
                'normalized_payload' => $parsed['normalized_payload'],
                'raw_payload' => $parsed['raw_payload'],
            ]
        );

        return [
            'snapshot' => $snapshot,
            'artifact_path' => $artifactPath,
            'parsed' => $parsed,
        ];
    }

    /**
     * @return array{
     *   issue_class:string,
     *   source_issue_label:string,
     *   property_scope:string|null,
     *   first_detected_at:Carbon|null,
     *   last_updated_at:Carbon|null,
     *   affected_url_count:int,
     *   exact_example_count:int,
     *   affected_urls:array<int, string>,
     *   sample_urls:array<int, string>,
     *   examples:array<int, array{url:string,last_crawled:?string}>,
     *   chart_points:array<int, array{date:string,affected_pages:int|null}>,
     *   raw_payload:array<string, mixed>
     * }
     */
    public function parseDrilldownArchive(string $archivePath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException('Unable to open Search Console drilldown archive.');
        }

        $tableRows = $this->parseCsvString($this->zipEntryContents($zip, 'Table.csv'));
        $chartRows = $this->parseCsvString($this->zipEntryContents($zip, 'Chart.csv', false) ?? '');
        $metadataRows = $this->parseCsvString($this->zipEntryContents($zip, 'Metadata.csv'));

        $zip->close();

        $metadata = collect($metadataRows)
            ->filter(fn (array $row): bool => ($row['Property'] ?? '') !== '')
            ->mapWithKeys(fn (array $row): array => [(string) $row['Property'] => $row['Value'] ?? null])
            ->all();
        $issueLabel = is_string($metadata['Issue'] ?? null) ? $metadata['Issue'] : null;

        if ($issueLabel === null) {
            throw new RuntimeException('The drilldown export is missing the issue label metadata.');
        }

        $issueClass = $this->issueClassForLabel($issueLabel);
        if ($issueClass === null) {
            throw new RuntimeException(sprintf('Unsupported Search Console issue label [%s].', $issueLabel));
        }

        $examples = collect($tableRows)
            ->filter(fn (array $row): bool => is_string($row['URL'] ?? null) && trim((string) $row['URL']) !== '')
            ->map(fn (array $row): array => [
                'url' => trim((string) $row['URL']),
                'last_crawled' => $this->parseDate($row['Last crawled'] ?? null)?->toDateString(),
            ])
            ->values()
            ->all();

        if ($examples === []) {
            throw new RuntimeException('The drilldown export does not contain any example URLs.');
        }

        $affectedUrls = array_values(array_unique(array_map(
            static fn (array $example): string => $example['url'],
            $examples
        )));

        $chartPoints = collect($chartRows)
            ->filter(fn (array $row): bool => ($row['Date'] ?? '') !== '')
            ->map(fn (array $row): array => [
                'date' => (string) $row['Date'],
                'affected_pages' => $this->nullableInt($row['Affected pages'] ?? null),
            ])
            ->sortBy('date')
            ->values()
            ->all();

        $summaryAffectedCount = collect($chartPoints)
            ->pluck('affected_pages')
            ->filter(fn (mixed $value): bool => is_int($value))
            ->last();
        $exactExampleCount = count($affectedUrls);

        return [
            'issue_class' => $issueClass,
            'source_issue_label' => $issueLabel,
            'property_scope' => is_string($metadata['Sitemap'] ?? null) ? $metadata['Sitemap'] : null,
            'first_detected_at' => $this->parseDate($metadata['First detected'] ?? null),
            'last_updated_at' => $this->parseDate($metadata['Last update'] ?? null),
            'affected_url_count' => max($exactExampleCount, is_int($summaryAffectedCount) ? $summaryAffectedCount : 0),
            'exact_example_count' => $exactExampleCount,
            'affected_urls' => $affectedUrls,
            'sample_urls' => array_slice($affectedUrls, 0, 10),
            'examples' => $examples,
            'chart_points' => $chartPoints,
            'raw_payload' => [
                'table' => $tableRows,
                'chart' => $chartRows,
                'metadata' => $metadataRows,
            ],
        ];
    }

    /**
     * @return array{
     *   source_issue_label:string|null,
     *   source_report:string,
     *   source_property:string|null,
     *   property_scope:string|null,
     *   first_detected_at:Carbon|null,
     *   last_updated_at:Carbon|null,
     *   affected_url_count:int|null,
     *   sample_urls:array<int, string>|null,
     *   examples:array<int, array<string, mixed>>|null,
     *   chart_points:array<int, array<string, mixed>>|null,
     *   normalized_payload:array<string, mixed>,
     *   raw_payload:array<string, mixed>
     * }
     */
    public function parseApiEvidenceJson(string $issueClass, string $jsonPath, string $captureMethod): array
    {
        if (! is_array(config('domain_monitor.search_console_issue_catalog.'.$issueClass))) {
            throw new InvalidArgumentException(sprintf('Unsupported Search Console issue class [%s].', $issueClass));
        }

        $decoded = json_decode((string) file_get_contents($jsonPath), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Issue evidence payload is not valid JSON.');
        }

        $normalizedPayload = array_filter([
            'affected_urls' => $this->stringList($decoded['affected_urls'] ?? []),
            'url_inspection' => is_array($decoded['url_inspection'] ?? null) ? $decoded['url_inspection'] : null,
            'sitemaps' => is_array($decoded['sitemaps'] ?? null) ? $decoded['sitemaps'] : null,
            'referring_urls' => $this->stringList($decoded['referring_urls'] ?? []),
            'canonical_state' => is_array($decoded['canonical_state'] ?? null) ? $decoded['canonical_state'] : null,
            'search_analytics' => is_array($decoded['search_analytics'] ?? null) ? $decoded['search_analytics'] : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        if ($normalizedPayload === []) {
            throw new RuntimeException('Issue evidence payload does not contain any supported enrichment fields.');
        }

        /** @var array<int, array<string, mixed>> $exampleRows */
        $exampleRows = is_array($decoded['examples'] ?? null) ? $decoded['examples'] : [];
        $examples = collect($exampleRows)
            ->map(fn (array $example): array => [
                'url' => $example['url'],
                'last_crawled' => is_string($example['last_crawled'] ?? null) ? $example['last_crawled'] : null,
            ])
            ->values()
            ->all();
        $affectedUrls = $this->stringList($decoded['affected_urls'] ?? []);

        if ($affectedUrls === [] && $examples !== []) {
            $affectedUrls = array_values(array_unique(array_map(
                static fn (array $example): string => $example['url'],
                $examples
            )));
            $normalizedPayload['affected_urls'] = $affectedUrls;
        }

        return [
            'source_issue_label' => is_string($decoded['source_issue_label'] ?? null)
                ? $decoded['source_issue_label']
                : data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label'),
            'source_report' => is_string($decoded['source_report'] ?? null)
                ? $decoded['source_report']
                : ($captureMethod === 'gsc_mcp_api' ? 'search_console_mcp' : 'search_console_api'),
            'source_property' => is_string($decoded['source_property'] ?? null) ? $decoded['source_property'] : null,
            'property_scope' => is_string($decoded['property_scope'] ?? null) ? $decoded['property_scope'] : null,
            'first_detected_at' => $this->parseDate($decoded['first_detected'] ?? null),
            'last_updated_at' => $this->parseDate($decoded['last_update'] ?? null),
            'affected_url_count' => is_numeric($decoded['affected_url_count'] ?? null)
                ? (int) $decoded['affected_url_count']
                : ($affectedUrls !== [] ? count($affectedUrls) : null),
            'sample_urls' => $affectedUrls !== [] ? array_slice($affectedUrls, 0, 10) : null,
            'examples' => $examples !== [] ? $examples : null,
            'chart_points' => is_array($decoded['chart_points'] ?? null) ? $decoded['chart_points'] : null,
            'normalized_payload' => $normalizedPayload,
            'raw_payload' => $decoded,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persistSnapshot(WebProperty $property, string $issueClass, array $attributes): SearchConsoleIssueSnapshot
    {
        $domain = $property->primaryDomainModel();
        if (! $domain instanceof Domain) {
            throw new InvalidArgumentException('The property does not have a primary domain.');
        }

        $matomoSource = $property->primaryAnalyticsSource('matomo');

        return DB::transaction(function () use ($attributes, $domain, $issueClass, $matomoSource, $property): SearchConsoleIssueSnapshot {
            return SearchConsoleIssueSnapshot::query()->create([
                'domain_id' => $domain->id,
                'web_property_id' => $property->id,
                'property_analytics_source_id' => $matomoSource instanceof PropertyAnalyticsSource ? $matomoSource->id : null,
                'issue_class' => $issueClass,
                ...$attributes,
            ]);
        });
    }

    private function searchConsolePropertyForProperty(WebProperty $property): ?string
    {
        $primarySource = $property->primaryAnalyticsSource('matomo');
        $coverage = $primarySource instanceof PropertyAnalyticsSource
            ? $primarySource->latestSearchConsoleCoverage()->first()
            : null;

        if ($coverage instanceof SearchConsoleCoverageStatus && is_string($coverage->property_uri) && $coverage->property_uri !== '') {
            return $coverage->property_uri;
        }

        return $property->latestPropertySeoBaselineRecord()?->search_console_property_uri;
    }

    private function storeArtifact(WebProperty $property, string $sourcePath, string $directory): string
    {
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
        $safeExtension = $extension !== '' ? '.'.Str::lower($extension) : '';
        $filename = now()->format('Ymd-His').'-'.Str::slug(pathinfo($sourcePath, PATHINFO_FILENAME)).$safeExtension;
        $relativePath = $directory.'/'.$property->slug.'/'.$filename;
        $stream = fopen($sourcePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to read Search Console issue evidence artifact.');
        }

        try {
            $written = Storage::disk('local')->writeStream($relativePath, $stream);
        } finally {
            fclose($stream);
        }

        if ($written === false) {
            throw new RuntimeException('Unable to store Search Console issue evidence artifact.');
        }

        return $relativePath;
    }

    private function issueClassForLabel(string $label): ?string
    {
        $normalizedLabel = $this->normalizeIssueLabel($label);

        foreach (config('domain_monitor.search_console_issue_catalog', []) as $issueClass => $catalogEntry) {
            $labels = array_values(array_filter($catalogEntry['labels'] ?? [], 'is_string'));

            foreach ($labels as $candidateLabel) {
                if ($this->normalizeIssueLabel($candidateLabel) === $normalizedLabel) {
                    return is_string($issueClass) ? $issueClass : null;
                }
            }
        }

        return null;
    }

    private function normalizeIssueLabel(string $label): string
    {
        return Str::of($label)
            ->replace(['’', '‘'], "'")
            ->replace(['–', '—'], '-')
            ->squish()
            ->lower()
            ->toString();
    }

    private function assertArchiveWithinLimits(string $archivePath): void
    {
        $archiveSize = filesize($archivePath);

        if ($archiveSize === false || $archiveSize > self::MAX_ARCHIVE_BYTES) {
            throw new InvalidArgumentException('Issue detail archive exceeds the supported size limit.');
        }
    }

    private function zipEntryContents(ZipArchive $zip, string $entryName, bool $required = true): ?string
    {
        $stats = $zip->statName($entryName);
        if (is_array($stats) && (int) $stats['size'] > self::MAX_ENTRY_BYTES) {
            throw new RuntimeException(sprintf('The issue detail export entry [%s] exceeds the supported size limit.', $entryName));
        }

        $contents = $zip->getFromName($entryName);

        if (is_string($contents)) {
            return $contents;
        }

        if ($required) {
            throw new RuntimeException(sprintf('The issue detail export is missing [%s].', $entryName));
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
            if (trim((string) $line) === '') {
                continue;
            }

            $values = str_getcsv((string) $line);
            $row = [];

            foreach ($headers as $index => $header) {
                $row[$header] = isset($values[$index]) ? trim((string) $values[$index]) : '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || in_array(strtolower($trimmed), ['n/a', 'na', '-', '--', 'not available'], true)) {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function stringList(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null,
            $values
        )));
    }
}
