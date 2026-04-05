<?php

namespace App\Services;

use App\Models\DomainSeoBaseline;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use Illuminate\Support\Collection;

class SearchConsoleIssueEvidenceService
{
    public function __construct(
        private readonly DetectedIssueIdentityService $issueIdentityService,
        private readonly DetectedIssueVerificationService $issueVerificationService,
        private readonly SearchConsoleIssueActionabilityService $issueActionabilityService,
    ) {}

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
            ->with([
                'latestSeoBaselineForProperty',
                'primaryDomain.latestSeoCheck',
                'propertyDomains.domain.latestSeoCheck',
            ])
            ->get([
                'id',
                'slug',
                'primary_domain_id',
                'target_moveroo_subdomain_url',
                'target_legacy_bookings_replacement_url',
                'target_legacy_payments_replacement_url',
                'legacy_moveroo_endpoint_scan',
            ]);

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
        $issueDomainId = $this->issueDomainIdForProperty($property);
        $issueIds = [];

        foreach (array_keys($propertyEvidence) as $issueClass) {
            if ($issueClass === '' || ! is_string($issueDomainId) || $issueDomainId === '') {
                continue;
            }

            $issueIds[$issueClass] = $this->issueIdentityService->makeIssueId(
                $issueDomainId,
                $property->slug,
                $issueClass
            );
        }

        $verificationMap = $this->issueVerificationService->latestMapForIssueIds(array_values($issueIds));

        return collect($propertyEvidence)
            ->map(function (array $evidence, string $issueClass) use ($baseline, $snapshots, $property, $issueIds, $verificationMap): ?array {
                $catalogEntry = config('domain_monitor.search_console_issue_catalog.'.$issueClass, []);
                $detailSnapshot = $snapshots[$issueClass]['detail'] ?? null;
                $apiSnapshot = $snapshots[$issueClass]['api'] ?? null;
                $count = $this->displayAffectedUrlCount($evidence);
                $examples = is_array($evidence['examples'] ?? null) ? $evidence['examples'] : [];
                $summaryCount = $count ?? $baseline?->issueCount($issueClass);

                $issueId = $issueIds[$issueClass] ?? null;
                $verification = is_string($issueId) ? ($verificationMap[$issueId] ?? null) : null;
                $issueContext = [
                    'issue_id' => $issueId,
                    'property_slug' => $property->slug,
                    'domain' => $property->primaryDomainName(),
                    'issue_class' => $issueClass,
                    'detected_at' => $evidence['captured_at'] ?? null,
                    'evidence' => array_filter([
                        'captured_at' => is_string($evidence['captured_at'] ?? null) ? $evidence['captured_at'] : null,
                        'api_captured_at' => is_string($evidence['api_captured_at'] ?? null) ? $evidence['api_captured_at'] : null,
                    ]),
                ];

                if (is_string($issueId)
                    && $verification instanceof \App\Models\DetectedIssueVerification
                    && $this->issueVerificationService->isCurrentlySuppressed($issueContext, $verification)) {
                    return null;
                }

                if (is_array($evidence['expected_exclusion'] ?? null)) {
                    return null;
                }

                if (! $this->issueActionabilityService->isActionable($issueClass, $evidence)) {
                    return null;
                }

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
            ->filter(fn (mixed $summary): bool => is_array($summary))
            ->sortByDesc(fn (array $summary): int => (int) ($summary['affected_url_count'] ?? 0))
            ->values()
            ->all();
    }

    private function issueDomainIdForProperty(WebProperty $property): ?string
    {
        $candidates = [
            $property->primary_domain_id,
            $property->primaryDomain?->id,
            $property->propertyDomains
                ->firstWhere('is_canonical', true)?->domain_id,
            $property->propertyDomains
                ->firstWhere('usage_type', 'primary')?->domain_id,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     * @return array{
     *   evidence: array<string, array<string, array<string, mixed>>>,
     *   snapshots: array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot, live:?SearchConsoleIssueSnapshot}>>
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
                    'live' => null,
                ]);
                $mergedEvidence = array_replace($baselineEvidence, $snapshotEvidence);
                $issueEvidence[$issueClass] = $this->issueActionabilityService->normalize($property, $issueClass, $mergedEvidence);
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
     * @return array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot, live:?SearchConsoleIssueSnapshot}>>
     */
    private function latestSnapshotsForProperties(array $propertyIds): array
    {
        if ($propertyIds === []) {
            return [];
        }

        /** @var array<string, array<string, array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot, live:?SearchConsoleIssueSnapshot}>> $grouped */
        $grouped = [];

        SearchConsoleIssueSnapshot::query()
            ->whereIn('web_property_id', $propertyIds)
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (SearchConsoleIssueSnapshot $snapshot) use (&$grouped): void {
                $bucket = match ($snapshot->capture_method) {
                    'gsc_drilldown_zip' => 'detail',
                    'gsc_live_recheck' => 'live',
                    default => 'api',
                };
                $grouped[$snapshot->web_property_id] ??= [];
                $grouped[$snapshot->web_property_id][$snapshot->issue_class] ??= ['detail' => null, 'api' => null, 'live' => null];
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
     * @param  array{detail:?SearchConsoleIssueSnapshot, api:?SearchConsoleIssueSnapshot, live:?SearchConsoleIssueSnapshot}  $snapshots
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

        if ($snapshots['live'] instanceof SearchConsoleIssueSnapshot) {
            $liveEvidence = $snapshots['live']->issueEvidence();

            if (array_key_exists('live_url_checks', $liveEvidence)) {
                $evidence['live_url_checks'] = $liveEvidence['live_url_checks'];
            }

            if (is_string($liveEvidence['captured_at'] ?? null) && $liveEvidence['captured_at'] !== '') {
                $evidence['live_captured_at'] = $liveEvidence['captured_at'];
            }
        }

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function displayAffectedUrlCount(array $evidence): ?int
    {
        if (is_numeric($evidence['active_affected_url_count'] ?? null)) {
            return (int) $evidence['active_affected_url_count'];
        }

        return is_numeric($evidence['affected_url_count'] ?? null)
            ? (int) $evidence['affected_url_count']
            : null;
    }
}
