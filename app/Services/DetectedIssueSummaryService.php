<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DetectedIssueSummaryService
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedSnapshot = null;

    public function __construct(
        private readonly DashboardIssueQueueService $queueService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        if ($this->cachedSnapshot !== null) {
            return $this->cachedSnapshot;
        }

        $queueSnapshot = $this->queueService->snapshot();
        $mustFix = $this->flattenIssues($queueSnapshot['must_fix'] ?? [], 'must_fix');
        $shouldFix = $this->flattenIssues($queueSnapshot['should_fix'] ?? [], 'should_fix');
        $issues = $mustFix->concat($shouldFix)->values();

        return $this->cachedSnapshot = [
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
     * @return Collection<int, array<string, mixed>>
     */
    private function flattenIssues(array $items, string $severity): Collection
    {
        return collect($items)
            ->flatMap(fn (array $item): array => $this->makeIssues($item, $severity))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array<string, mixed>>
     */
    private function makeIssues(array $item, string $severity): array
    {
        $domainId = (string) ($item['id'] ?? '');
        $domain = is_string($item['domain'] ?? null) ? $item['domain'] : null;
        $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
        $detectedAt = is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== ''
            ? $item['updated_at_iso']
            : now()->toIso8601String();
        $issueEntries = $this->normalizedIssueEntries($item, $severity);
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
                    'primary_reasons' => array_values(array_filter($item['primary_reasons'] ?? [], 'is_string')),
                    'secondary_reasons' => array_values(array_filter($item['secondary_reasons'] ?? [], 'is_string')),
                    'related_issue_classes' => $relatedIssueClasses,
                    'coverage_required' => (bool) ($item['coverage_required'] ?? false),
                    'coverage_status' => is_string($item['coverage_status'] ?? null) ? $item['coverage_status'] : null,
                    'coverage_gap' => (bool) ($item['coverage_gap'] ?? false),
                    'property_match_confidence' => is_string($item['property_match_confidence'] ?? null) ? $item['property_match_confidence'] : null,
                    'baseline_surface' => $issueEntry['baseline_surface'],
                    'source_domain_id' => $domainId,
                ],
            ];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<int, array{issue_class:string, severity:string, control_id:?string, rollout_scope:string, baseline_surface:?string}>
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
}
