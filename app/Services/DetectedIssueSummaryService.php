<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DetectedIssueSummaryService
{
    public function __construct(
        private readonly DashboardIssueQueueService $queueService,
        private readonly SearchConsoleIssueEvidenceService $issueEvidenceService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $queueSnapshot = $this->queueService->snapshot();
        $issueEvidence = $this->issueEvidenceService->evidenceMapForQueueItems([
            ...($queueSnapshot['must_fix'] ?? []),
            ...($queueSnapshot['should_fix'] ?? []),
        ]);
        $issues = $this->flattenIssues($queueSnapshot['must_fix'] ?? [], 'must_fix', $issueEvidence)
            ->concat($this->flattenIssues($queueSnapshot['should_fix'] ?? [], 'should_fix', $issueEvidence))
            ->values();
        $mustFix = $issues->where('severity', 'must_fix')->values();
        $shouldFix = $issues->where('severity', 'should_fix')->values();

        return [
            'source_system' => 'domain-monitor-issues',
            'contract_version' => 1,
            'generated_at' => $queueSnapshot['generated_at'] ?? now()->toIso8601String(),
            'stats' => [
                'open' => $issues->count(),
                'must_fix' => $mustFix->count(),
                'should_fix' => $shouldFix->count(),
                'issue_class_counts' => $this->countByStringKey($issues, 'issue_class'),
                'control_counts' => $this->countByStringKey($issues, 'control_id'),
                'rollout_scope_counts' => $this->countByStringKey($issues, 'rollout_scope'),
            ],
            'issues' => $issues->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $issueId): ?array
    {
        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->snapshot()['issues'] ?? [];
        /** @var array<string, mixed>|null $issue */
        $issue = collect($issues)->firstWhere('issue_id', $issueId);

        return $issue;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, array<string, array<string, mixed>>>  $issueEvidence
     * @return Collection<int, array<string, mixed>>
     */
    private function flattenIssues(array $items, string $severity, array $issueEvidence): Collection
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $this->makeIssues($item, $severity, $issueEvidence))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, array<string, mixed>>>  $issueEvidence
     * @return array<int, array<string, mixed>>
     */
    private function makeIssues(array $item, string $severity, array $issueEvidence): array
    {
        $domainId = (string) ($item['id'] ?? '');
        $domain = is_string($item['domain'] ?? null) ? $item['domain'] : null;
        $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
        $evidenceKey = $propertySlug ?: $domainId;
        $detectedAt = is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== ''
            ? $item['updated_at_iso']
            : now()->toIso8601String();
        $queueIssueEntries = $this->normalizedIssueEntries($item, $severity);
        $issueEntries = array_merge(
            $queueIssueEntries,
            $this->supplementalIssueEntries($item, $issueEvidence[$evidenceKey] ?? [], $queueIssueEntries)
        );
        $relatedIssueClasses = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_class'],
            $issueEntries
        )));
        sort($relatedIssueClasses);
        $issues = [];

        foreach ($issueEntries as $issueEntry) {
            $issueClass = $issueEntry['issue_class'];
            $issueIdentity = [
                'source_domain_id' => $domainId,
                'property_slug' => $propertySlug,
                'issue_class' => $issueClass,
            ];
            $issueId = sprintf(
                'dm:%s:%s',
                $domainId !== '' ? $domainId : 'unknown',
                substr(sha1(json_encode($issueIdentity, JSON_THROW_ON_ERROR)), 0, 16)
            );

            $issues[] = [
                'issue_id' => $issueId,
                'property_slug' => $propertySlug,
                'property_name' => is_string($item['web_property_name'] ?? null) ? $item['web_property_name'] : null,
                'domain' => $domain,
                'issue_class' => $issueClass,
                'severity' => $issueEntry['severity'],
                'detector' => 'domain_monitor.priority_queue',
                'status' => 'open',
                'detected_at' => $detectedAt,
                'rollout_scope' => $issueEntry['rollout_scope'],
                'control_id' => $issueEntry['control_id'],
                'platform_profile' => is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null,
                'host_profile' => is_string($item['host_profile'] ?? null) ? $item['host_profile'] : null,
                'control_profile' => is_string($item['control_profile'] ?? null) ? $item['control_profile'] : null,
                'control_state' => is_string($item['control_state'] ?? null) ? $item['control_state'] : null,
                'execution_surface' => is_string($item['execution_surface'] ?? null) ? $item['execution_surface'] : null,
                'fleet_managed' => (bool) ($item['fleet_managed'] ?? false),
                'controller_repo' => is_string($item['controller_repo'] ?? null) ? $item['controller_repo'] : null,
                'evidence' => [
                    'primary_reasons' => [$issueEntry['reason']],
                    'secondary_reasons' => array_values(array_filter(
                        array_map(
                            static fn (array $entry): ?string => $entry['issue_class'] !== $issueClass ? $entry['reason'] : null,
                            $issueEntries
                        ),
                        'is_string'
                    )),
                    'related_issue_classes' => $relatedIssueClasses,
                    'coverage_required' => (bool) ($item['coverage_required'] ?? false),
                    'coverage_status' => is_string($item['coverage_status'] ?? null) ? $item['coverage_status'] : null,
                    'coverage_gap' => (bool) ($item['coverage_gap'] ?? false),
                    'property_match_confidence' => is_string($item['property_match_confidence'] ?? null) ? $item['property_match_confidence'] : null,
                    'baseline_surface' => $issueEntry['baseline_surface'],
                    'source_domain_id' => $domainId,
                    ...($issueEvidence[$evidenceKey][$issueClass] ?? []),
                ],
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, array<string, mixed>>  $propertyEvidence
     * @param  array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>  $existingIssueEntries
     * @return array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
     */
    private function supplementalIssueEntries(array $item, array $propertyEvidence, array $existingIssueEntries): array
    {
        $existingIssueClasses = array_values(array_unique(array_map(
            static fn (array $entry): string => $entry['issue_class'],
            $existingIssueEntries
        )));
        $entries = [];

        foreach ($propertyEvidence as $issueClass => $evidence) {
            if (in_array($issueClass, $existingIssueClasses, true)) {
                continue;
            }

            if (! $this->shouldEmitSupplementalIssue($issueClass, $evidence)) {
                continue;
            }

            $controlId = $this->controlIdForIssueClass($issueClass);
            $baselineSurface = $this->defaultBaselineSurfaceForPlatformProfile(
                is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null
            );
            $configuredRolloutScope = $this->configuredRolloutScopeForControl($controlId);

            $entries[] = [
                'issue_class' => $issueClass,
                'reason' => $this->supplementalIssueReason($issueClass, $evidence),
                'severity' => $this->defaultSeverityForIssueClass($issueClass),
                'control_id' => $controlId,
                'rollout_scope' => $configuredRolloutScope
                    ?? (($controlId && $baselineSurface) ? 'fleet' : 'domain_only'),
                'baseline_surface' => ($configuredRolloutScope === 'domain_only' || ! $controlId || ! $baselineSurface)
                    ? null
                    : $baselineSurface,
            ];
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array{issue_class:string, reason:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
     */
    private function normalizedIssueEntries(array $item, string $fallbackSeverity): array
    {
        $issueEntries = is_array($item['issue_entries'] ?? null) ? $item['issue_entries'] : [];

        if ($issueEntries !== []) {
            return array_values(array_filter(array_map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $issueClass = is_string($entry['issue_family'] ?? null) ? $entry['issue_family'] : null;
                $severity = is_string($entry['severity'] ?? null) ? $entry['severity'] : null;
                $rolloutScope = is_string($entry['rollout_scope'] ?? null) ? $entry['rollout_scope'] : null;

                if ($issueClass === null || $severity === null || $rolloutScope === null) {
                    return null;
                }

                return [
                    'issue_class' => $issueClass,
                    'reason' => (string) $entry['reason'],
                    'severity' => $severity,
                    'control_id' => is_string($entry['control_id'] ?? null) ? $entry['control_id'] : null,
                    'rollout_scope' => $rolloutScope,
                    'baseline_surface' => is_string($entry['baseline_surface'] ?? null) ? $entry['baseline_surface'] : null,
                ];
            }, $issueEntries)));
        }

        $fallbackIssueClass = is_string($item['issue_family'] ?? null) && $item['issue_family'] !== ''
            ? $item['issue_family']
            : 'unclassified';

        return [[
            'issue_class' => $fallbackIssueClass,
            'reason' => is_string($item['primary_reasons'][0] ?? null) ? $item['primary_reasons'][0] : $fallbackIssueClass,
            'severity' => $fallbackSeverity,
            'control_id' => is_string($item['control_id'] ?? null) ? $item['control_id'] : null,
            'rollout_scope' => is_string($item['rollout_scope'] ?? null) ? $item['rollout_scope'] : 'domain_only',
            'baseline_surface' => is_string($item['baseline_surface'] ?? null) ? $item['baseline_surface'] : null,
        ]];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $issues
     * @return array<string, int>
     */
    private function countByStringKey(Collection $issues, string $key): array
    {
        $counts = [];

        foreach ($issues as $issue) {
            $value = $issue[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function shouldEmitSupplementalIssue(string $issueClass, array $evidence): bool
    {
        if (! is_array(config('domain_monitor.search_console_issue_catalog.'.$issueClass))) {
            return false;
        }

        if (is_numeric($evidence['affected_url_count'] ?? null) && (int) $evidence['affected_url_count'] > 0) {
            return true;
        }

        foreach (['affected_urls', 'examples', 'url_inspection', 'sitemaps', 'referring_urls', 'canonical_state', 'search_analytics'] as $key) {
            if (! empty($evidence[$key])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    private function supplementalIssueReason(string $issueClass, array $evidence): string
    {
        $label = data_get(config('domain_monitor.search_console_issue_catalog.'.$issueClass), 'label', $issueClass);
        $count = is_numeric($evidence['affected_url_count'] ?? null) ? (int) $evidence['affected_url_count'] : null;

        if ($count !== null && $count > 0) {
            return sprintf('Search Console reports %s (%d URLs)', strtolower((string) $label), $count);
        }

        return sprintf('Search Console reports %s', strtolower((string) $label));
    }

    private function defaultSeverityForIssueClass(string $issueClass): string
    {
        return in_array($issueClass, ['page_with_redirect_in_sitemap', 'blocked_by_robots_in_indexing'], true)
            ? 'must_fix'
            : 'should_fix';
    }

    private function controlIdForIssueClass(string $issueClass): ?string
    {
        $controls = data_get(config('domain_monitor.priority_queue_standards'), 'controls', []);

        if (! is_array($controls)) {
            return null;
        }

        foreach ($controls as $controlId => $controlConfig) {
            $mappedFamilies = data_get($controlConfig, 'issue_families', []);

            if (is_array($mappedFamilies) && in_array($issueClass, $mappedFamilies, true)) {
                return is_string($controlId) ? $controlId : null;
            }
        }

        return null;
    }

    private function configuredRolloutScopeForControl(?string $controlId): ?string
    {
        return is_string($controlId)
            ? data_get(config('domain_monitor.priority_queue_standards'), 'controls.'.$controlId.'.rollout_scope')
            : null;
    }

    private function defaultBaselineSurfaceForPlatformProfile(?string $platformProfile): ?string
    {
        return is_string($platformProfile)
            ? data_get(config('domain_monitor.priority_queue_standards'), 'platform_profiles.'.$platformProfile.'.baseline_surface')
            : null;
    }
}
