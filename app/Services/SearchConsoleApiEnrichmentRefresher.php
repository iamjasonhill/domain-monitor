<?php

namespace App\Services;

use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use Illuminate\Support\Collection;
use RuntimeException;

class SearchConsoleApiEnrichmentRefresher
{
    public function __construct(
        private readonly SearchConsoleApiBundleCollector $collector,
        private readonly SearchConsoleApiBundleImporter $importer,
    ) {}

    /**
     * @return array{
     *   dry_run: bool,
     *   stale_days: int,
     *   batch_limit: int,
     *   candidate_count: int,
     *   processed_count: int,
     *   refreshed_count: int,
     *   properties: array<int, array<string, mixed>>,
     *   errors: array<int, array{property_slug:string,message:string}>
     * }
     */
    public function run(
        int $staleDays = 7,
        int $batchLimit = 3,
        int $days = 28,
        ?int $urlLimit = null,
        ?int $rowLimit = null,
        string $captureMethod = 'gsc_api',
        ?string $capturedBy = null,
        bool $dryRun = false,
    ): array {
        $candidates = $this->candidateProperties($staleDays, $batchLimit);
        $results = [];
        $errors = [];

        foreach ($candidates as $property) {
            try {
                if ($dryRun) {
                    $results[] = [
                        'property_slug' => $property->slug,
                        'search_console_property_uri' => $property->searchConsolePropertyUri(),
                        'issue_count' => (int) SearchConsoleIssueSnapshot::query()
                            ->where('web_property_id', $property->id)
                            ->where('capture_method', 'gsc_drilldown_zip')
                            ->count(),
                        'latest_api_captured_at' => $this->normalizeCapturedAt($property->getAttribute('gsc_api_last_captured_at'))?->toIso8601String(),
                    ];

                    continue;
                }

                $bundle = $this->collector->collectBundleForProperty(
                    $property,
                    $days,
                    $urlLimit,
                    $rowLimit,
                );

                $temporaryPath = tempnam(sys_get_temp_dir(), 'gsc-refresh-bundle-');

                if (! is_string($temporaryPath)) {
                    throw new RuntimeException('Unable to create a temporary Search Console API bundle file.');
                }

                try {
                    $encodedBundle = json_encode($bundle, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                    $bytesWritten = file_put_contents($temporaryPath, $encodedBundle);

                    if ($bytesWritten === false) {
                        throw new RuntimeException('Unable to write the temporary Search Console API bundle file.');
                    }

                    $imported = $this->importer->importBundleForProperty(
                        $property,
                        $temporaryPath,
                        $captureMethod,
                        $capturedBy ?: 'scheduled_api_enrichment_refresh'
                    );
                } finally {
                    @unlink($temporaryPath);
                }

                $results[] = [
                    'property_slug' => $property->slug,
                    'search_console_property_uri' => $property->searchConsolePropertyUri(),
                    'issue_classes' => $imported['imported_issue_classes'],
                    'issue_count' => count($imported['snapshots']),
                    'artifact_path' => $imported['artifact_path'],
                ];
            } catch (\Throwable $exception) {
                $errors[] = [
                    'property_slug' => $property->slug,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'dry_run' => $dryRun,
            'stale_days' => $staleDays,
            'batch_limit' => $batchLimit,
            'candidate_count' => $candidates->count(),
            'processed_count' => count($results),
            'refreshed_count' => $dryRun ? 0 : count($results),
            'properties' => $results,
            'errors' => $errors,
        ];
    }

    /**
     * @return Collection<int, WebProperty>
     */
    public function candidateProperties(int $staleDays = 7, int $batchLimit = 3): Collection
    {
        $staleCutoff = now()->subDays(max(1, $staleDays));

        $properties = WebProperty::query()
            ->where('status', 'active')
            ->where('property_type', '!=', 'domain_asset')
            ->whereHas('searchConsoleIssueSnapshots', fn ($query) => $query->where('capture_method', 'gsc_drilldown_zip'))
            ->with([
                'primaryDomain.tags',
                'analyticsSources.latestSearchConsoleCoverage',
                'latestSeoBaselineForProperty',
            ])
            ->addSelect([
                'gsc_api_last_captured_at' => SearchConsoleIssueSnapshot::query()
                    ->selectRaw('max(captured_at)')
                    ->whereColumn('web_property_id', 'web_properties.id')
                    ->where('capture_method', '!=', 'gsc_drilldown_zip'),
            ])
            ->orderBy('name')
            ->get();

        return $properties
            ->filter(function (WebProperty $property) use ($staleCutoff): bool {
                if (! $property->coverageEligibility()['eligible']) {
                    return false;
                }

                $searchConsolePropertyUri = $property->searchConsolePropertyUri();

                if (! is_string($searchConsolePropertyUri) || $searchConsolePropertyUri === '') {
                    return false;
                }

                $latestApiCapturedAt = $property->getAttribute('gsc_api_last_captured_at');

                if (! $latestApiCapturedAt instanceof \DateTimeInterface && ! is_string($latestApiCapturedAt)) {
                    return true;
                }

                $normalized = $this->normalizeCapturedAt($latestApiCapturedAt);

                return $normalized === null || $normalized->lt($staleCutoff);
            })
            ->sortBy([
                fn (WebProperty $property): int => $property->getAttribute('gsc_api_last_captured_at') ? 1 : 0,
                function (WebProperty $property): int {
                    $capturedAt = $this->normalizeCapturedAt($property->getAttribute('gsc_api_last_captured_at'));

                    return $capturedAt instanceof \Illuminate\Support\Carbon
                        ? $capturedAt->getTimestamp()
                        : 0;
                },
                fn (WebProperty $property): string => $property->name,
            ])
            ->take(max(1, $batchLimit))
            ->values();
    }

    private function normalizeCapturedAt(mixed $value): ?\Illuminate\Support\Carbon
    {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return \Illuminate\Support\Carbon::instance($value);
        }

        if (is_string($value) && $value !== '') {
            return \Illuminate\Support\Carbon::parse($value, 'UTC');
        }

        return null;
    }
}
