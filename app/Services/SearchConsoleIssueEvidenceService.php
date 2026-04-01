<?php

namespace App\Services;

use App\Models\DomainSeoBaseline;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use Illuminate\Support\Collection;

class SearchConsoleIssueEvidenceService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function evidenceMapForQueueItems(array $items): array
    {
        $propertySlugs = collect($items)
            ->pluck('web_property_slug')
            ->filter(fn (mixed $slug): bool => is_string($slug) && $slug !== '')
            ->values()
            ->all();

        if ($propertySlugs === []) {
            return [];
        }

        $properties = WebProperty::query()
            ->whereIn('slug', $propertySlugs)
            ->with('latestSeoBaselineForProperty')
            ->get(['id', 'slug']);

        return $this->evidenceMapForProperties($properties);
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function evidenceMapForProperties(Collection $properties): array
    {
        if ($properties->isEmpty()) {
            return [];
        }

        return $this->buildPropertyEvidenceContext($properties)['evidence'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function propertyIssueSummaries(WebProperty $property): array
    {
        $context = $this->buildPropertyEvidenceContext(collect([$property]));
        $propertyEvidence = $context['evidence'][$property->slug] ?? [];
        $baseline = $property->latestPropertySeoBaselineRecord();
        $snapshots = $context['snapshots'][$property->id] ?? [];

        return collect($propertyEvidence)
            ->map(function (array $evidence, string $issueClass) use ($baseline, $snapshots): array {
                $catalogEntry = config('domain_monitor.search_console_issue_catalog.'.$issueClass, []);
                $detailSnapshot = $snapshots[$issueClass]['detail'] ?? null;
                $apiSnapshot = $snapshots[$issueClass]['api'] ?? null;
                $count = is_numeric($evidence['affected_url_count'] ?? null) ? (int) $evidence['affected_url_count'] : null;
                $examples = is_array($evidence['examples'] ?? null) ? $evidence['examples'] : [];
                $summaryCount = $count ?? $baseline?->issueCount($issueClass);

                return [
                    'issue_class' => $issueClass,
                    'label' => is_string($catalogEntry['label'] ?? null) ? $catalogEntry['label'] : $issueClass,
                    'affected_url_count' => $summaryCount,
                    'has_exact_examples' => $examples !== [] || (is_array($evidence['affected_urls'] ?? null) && $evidence['affected_urls'] !== []),
                    'source_capture_method' => $evidence['source_capture_method'] ?? null,
                    'source_report' => $evidence['source_report'] ?? null,
                    'source_property' => $evidence['source_property'] ?? null,
                    'captured_at' => $evidence['captured_at'] ?? null,
                    'examples' => array_slice($examples, 0, 5),
                    'sample_urls' => array_slice(is_array($evidence['sample_urls'] ?? null) ? $evidence['sample_urls'] : [], 0, 5),
                    'first_detected' => $evidence['first_detected'] ?? null,
                    'detail_snapshot_id' => $detailSnapshot?->id,
                    'api_snapshot_id' => $apiSnapshot?->id,
                ];
            })
            ->sortByDesc(fn (array $summary): int => (int) ($summary['affected_url_count'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     * @return array{
     *   evidence: array<string, array<string, array<string, mixed>>>,
     *   snapshots: array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot}>>
     * }
     */
    private function buildPropertyEvidenceContext(Collection $properties): array
    {
        $snapshots = $this->latestSnapshotsForProperties($properties->pluck('id')->values()->all());

        $evidence = $properties->mapWithKeys(function (WebProperty $property) use ($snapshots): array {
            $baseline = $property->latestPropertySeoBaselineRecord();
            $snapshotEntries = $snapshots[$property->id] ?? [];
            $issueClasses = array_values(array_unique(array_filter([
                ...array_keys($snapshotEntries),
                ...array_keys($this->baselineIssueEvidence($baseline)),
            ])));

            $issueEvidence = [];

            foreach ($issueClasses as $issueClass) {
                $baselineEvidence = $this->baselineIssueEvidenceForClass($baseline, $issueClass);
                $snapshotEvidence = $this->snapshotEvidenceForClass($snapshotEntries[$issueClass] ?? [
                    'detail' => null,
                    'api' => null,
                ]);
                $issueEvidence[$issueClass] = array_replace($baselineEvidence, $snapshotEvidence);
            }

            return [$property->slug => $issueEvidence];
        })->all();

        return [
            'evidence' => $evidence,
            'snapshots' => $snapshots,
        ];
    }

    /**
     * @param  array<int, string>  $propertyIds
     * @return array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot}>>
     */
    private function latestSnapshotsForProperties(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        /** @var array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot}>> $grouped */
        $grouped = [];

        SearchConsoleIssueSnapshot::query()
            ->whereIn('web_property_id', $propertyIds)
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (SearchConsoleIssueSnapshot $snapshot) use (&$grouped): void {
                $bucket = $snapshot->capture_method === 'gsc_drilldown_zip' ? 'detail' : 'api';
                $grouped[$snapshot->web_property_id] ??= [];
                $grouped[$snapshot->web_property_id][$snapshot->issue_class] ??= ['detail' => null, 'api' => null];
                $grouped[$snapshot->web_property_id][$snapshot->issue_class][$bucket] ??= $snapshot;
            });

        return $grouped;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function baselineIssueEvidence(?DomainSeoBaseline $baseline): array
    {
        if (! $baseline instanceof DomainSeoBaseline) {
            return [];
        }

        $issueEvidence = [];

        foreach (config('domain_monitor.search_console_issue_catalog', []) as $issueClass => $catalogEntry) {
            if (! is_string($issueClass)) {
                continue;
            }

            $evidence = $this->baselineIssueEvidenceForClass($baseline, $issueClass);

            if ($evidence !== []) {
                $issueEvidence[$issueClass] = $evidence;
            }
        }

        return $issueEvidence;
    }

    /**
     * @return array<string, mixed>
     */
    private function baselineIssueEvidenceForClass(?DomainSeoBaseline $baseline, string $issueClass): array
    {
        if (! $baseline instanceof DomainSeoBaseline) {
            return [];
        }

        $count = $baseline->issueCount($issueClass);

        if ($count === null || $count <= 0) {
            return [];
        }

        return $baseline->issueEvidence($issueClass);
    }

    /**
     * @param  array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot}  $snapshots
     * @return array<string, mixed>
     */
    private function snapshotEvidenceForClass(array $snapshots): array
    {
        $evidence = [];

        if ($snapshots['detail'] instanceof SearchConsoleIssueSnapshot) {
            $evidence = array_replace($evidence, $snapshots['detail']->issueEvidence());
        }

        if ($snapshots['api'] instanceof SearchConsoleIssueSnapshot) {
            $apiEvidence = $snapshots['api']->issueEvidence();

            foreach (['url_inspection', 'sitemaps', 'referring_urls', 'canonical_state', 'search_analytics'] as $key) {
                if (array_key_exists($key, $apiEvidence)) {
                    $evidence[$key] = $apiEvidence[$key];
                }
            }

            if (! array_key_exists('source_capture_method', $evidence)) {
                foreach (['source_capture_method', 'source_report', 'source_property', 'captured_at'] as $key) {
                    if (array_key_exists($key, $apiEvidence)) {
                        $evidence[$key] = $apiEvidence[$key];
                    }
                }
            } else {
                foreach ([
                    'source_capture_method' => 'api_source_capture_method',
                    'source_report' => 'api_source_report',
                    'source_property' => 'api_source_property',
                    'captured_at' => 'api_captured_at',
                ] as $sourceKey => $targetKey) {
                    if (array_key_exists($sourceKey, $apiEvidence)) {
                        $evidence[$targetKey] = $apiEvidence[$sourceKey];
                    }
                }
            }
        }

        return $evidence;
    }
}
