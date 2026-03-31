<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DetectedIssueSummaryService
{
    public function __construct(
        private readonly DashboardIssueQueueService $queueService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $queueSnapshot = $this->queueService->snapshot();
        $mustFix = $this->flattenIssues($queueSnapshot['must_fix'] ?? [], 'must_fix');
        $shouldFix = $this->flattenIssues($queueSnapshot['should_fix'] ?? [], 'should_fix');
        $issues = $mustFix->concat($shouldFix)->values();

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
     * @return Collection<int, array<string, mixed>>
     */
    private function flattenIssues(array $items, string $severity): Collection
    {
        return collect($items)
            ->map(fn (array $item): array => $this->makeIssue($item, $severity))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function makeIssue(array $item, string $severity): array
    {
        $issueClass = is_string($item['issue_family'] ?? null) && $item['issue_family'] !== ''
            ? $item['issue_family']
            : 'unclassified';
        $domainId = (string) ($item['id'] ?? '');
        $domain = is_string($item['domain'] ?? null) ? $item['domain'] : null;
        $propertySlug = is_string($item['web_property_slug'] ?? null) ? $item['web_property_slug'] : null;
        $detectedAt = is_string($item['updated_at_iso'] ?? null) && $item['updated_at_iso'] !== ''
            ? $item['updated_at_iso']
            : now()->toIso8601String();

        return [
            'issue_id' => "{$domainId}:{$severity}:{$issueClass}",
            'property_slug' => $propertySlug,
            'property_name' => is_string($item['web_property_name'] ?? null) ? $item['web_property_name'] : null,
            'domain' => $domain,
            'issue_class' => $issueClass,
            'severity' => $severity,
            'detector' => 'domain_monitor.priority_queue',
            'status' => 'open',
            'detected_at' => $detectedAt,
            'rollout_scope' => is_string($item['rollout_scope'] ?? null) ? $item['rollout_scope'] : 'domain_only',
            'control_id' => is_string($item['control_id'] ?? null) ? $item['control_id'] : null,
            'platform_profile' => is_string($item['platform_profile'] ?? null) ? $item['platform_profile'] : null,
            'host_profile' => is_string($item['host_profile'] ?? null) ? $item['host_profile'] : null,
            'control_profile' => is_string($item['control_profile'] ?? null) ? $item['control_profile'] : null,
            'evidence' => [
                'primary_reasons' => array_values(array_filter($item['primary_reasons'] ?? [], 'is_string')),
                'secondary_reasons' => array_values(array_filter($item['secondary_reasons'] ?? [], 'is_string')),
                'related_issue_classes' => array_values(array_filter($item['issue_families'] ?? [], 'is_string')),
                'coverage_required' => (bool) ($item['coverage_required'] ?? false),
                'coverage_status' => is_string($item['coverage_status'] ?? null) ? $item['coverage_status'] : null,
                'coverage_gap' => (bool) ($item['coverage_gap'] ?? false),
                'property_match_confidence' => is_string($item['property_match_confidence'] ?? null) ? $item['property_match_confidence'] : null,
                'baseline_surface' => is_string($item['baseline_surface'] ?? null) ? $item['baseline_surface'] : null,
                'source_domain_id' => $domainId,
            ],
        ];
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
