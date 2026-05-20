<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Schema;

class WebPropertyMonitoringSummaryBuilder
{
    /**
     * @return array{
     *   open_findings_count: int,
     *   must_fix_count: int,
     *   should_fix_count: int,
     *   latest_detected_at: string|null,
     *   lane_counts: array<string, int>,
     *   open_findings: array<int, array{
     *     issue_id: string,
     *     issue_class: string,
     *     severity: string,
     *     lane: string,
     *     issue_type: string,
     *     title: string,
     *     summary: string|null,
     *     status: string,
     *     detected_at: string|null,
     *     domain: string|null
     *   }>
     * }
     */
    public function build(WebProperty $property): array
    {
        if (! Schema::hasTable('monitoring_findings')) {
            return [
                'open_findings_count' => 0,
                'must_fix_count' => 0,
                'should_fix_count' => 0,
                'latest_detected_at' => null,
                'lane_counts' => [],
                'open_findings' => [],
            ];
        }

        $openFindings = $this->openFindings($property);

        return [
            'open_findings_count' => $openFindings->count(),
            'must_fix_count' => $openFindings
                ->filter(fn (MonitoringFinding $finding): bool => $this->severityFor($finding) === 'must_fix')
                ->count(),
            'should_fix_count' => $openFindings
                ->filter(fn (MonitoringFinding $finding): bool => $this->severityFor($finding) === 'should_fix')
                ->count(),
            'latest_detected_at' => $openFindings->first()?->last_detected_at?->toIso8601String(),
            'lane_counts' => $openFindings
                ->countBy(fn (MonitoringFinding $finding): string => $finding->lane)
                ->map(fn (int $count): int => $count)
                ->all(),
            'open_findings' => $openFindings
                ->map(fn (MonitoringFinding $finding): array => [
                    'issue_id' => $finding->issue_id,
                    'finding_identity' => $this->findingIdentity($finding, $property),
                    'issue_class' => $finding->finding_type,
                    'severity' => $this->severityFor($finding),
                    'lane' => $finding->lane,
                    'issue_type' => $finding->issue_type,
                    'title' => $finding->title,
                    'summary' => $finding->summary,
                    'status' => $finding->status,
                    'detected_at' => $finding->last_detected_at?->toIso8601String(),
                    'domain' => $finding->domain instanceof Domain ? $finding->domain->domain : null,
                    'actionable_evidence' => $this->actionableEvidence($finding),
                    'owner_issue_linkage' => $this->ownerIssueLinkage($finding, $property),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return EloquentCollection<int, MonitoringFinding>
     */
    private function openFindings(WebProperty $property): EloquentCollection
    {
        if ($property->relationLoaded('monitoringFindings')) {
            /** @var EloquentCollection<int, MonitoringFinding> $loadedFindings */
            $loadedFindings = $property->getRelation('monitoringFindings');
            $loadedFindings->loadMissing('domain:id,domain,platform,dns_config_name,parked_override');

            return $loadedFindings
                ->filter(fn (MonitoringFinding $finding): bool => $finding->status === MonitoringFinding::STATUS_OPEN)
                ->reject(fn (MonitoringFinding $finding): bool => $this->shouldSuppressFinding($finding, $property))
                ->sortByDesc(fn (MonitoringFinding $finding): int => $finding->last_detected_at?->getTimestamp() ?? 0)
                ->values();
        }

        return $property->monitoringFindings()
            ->where('status', MonitoringFinding::STATUS_OPEN)
            ->with('domain:id,domain,platform,dns_config_name,parked_override')
            ->orderByDesc('last_detected_at')
            ->get()
            ->reject(fn (MonitoringFinding $finding): bool => $this->shouldSuppressFinding($finding, $property))
            ->values();
    }

    private function severityFor(MonitoringFinding $finding): string
    {
        return in_array($finding->issue_type, ['incident', 'regression'], true)
            ? 'must_fix'
            : 'should_fix';
    }

    private function shouldSuppressFinding(MonitoringFinding $finding, WebProperty $property): bool
    {
        if ($property->shouldSuppressLiveWebsiteQualityFindings()) {
            return true;
        }

        $domain = $finding->domain;

        return $domain instanceof Domain && $domain->isParkedForHosting();
    }

    /**
     * @return array{
     *   issue_id: string,
     *   fingerprint: string,
     *   issue_class: string,
     *   lane: string,
     *   property_slug: string,
     *   domain: string|null
     * }
     */
    private function findingIdentity(MonitoringFinding $finding, WebProperty $property): array
    {
        return [
            'issue_id' => $finding->issue_id,
            'fingerprint' => $this->findingFingerprint($finding, $property),
            'issue_class' => $finding->finding_type,
            'lane' => $finding->lane,
            'property_slug' => $property->slug,
            'domain' => $finding->domain instanceof Domain ? $finding->domain->domain : null,
        ];
    }

    private function findingFingerprint(MonitoringFinding $finding, WebProperty $property): string
    {
        return 'domain_monitor.finding:'.hash('sha256', implode('|', [
            $property->slug,
            $finding->domain_id ?? '',
            $finding->finding_type,
            $finding->lane,
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function actionableEvidence(MonitoringFinding $finding): ?array
    {
        if (! str_contains($finding->finding_type, 'broken_links')) {
            return null;
        }

        $evidence = is_array($finding->evidence) ? $finding->evidence : [];
        $brokenLinks = collect(is_array($evidence['broken_links'] ?? null) ? $evidence['broken_links'] : [])
            ->filter(fn (mixed $link): bool => is_array($link))
            ->values();
        $suppressedLinks = collect(is_array($evidence['suppressed_links'] ?? null) ? $evidence['suppressed_links'] : [])
            ->filter(fn (mixed $link): bool => is_array($link))
            ->values();
        $sampleLimit = 5;
        $totalCount = is_numeric($evidence['broken_links_count'] ?? null)
            ? (int) $evidence['broken_links_count']
            : $brokenLinks->count();

        return [
            'type' => 'broken_links',
            'crawl_mode' => $finding->lane,
            'captured_at' => $this->stringOrNull($evidence['captured_at'] ?? null) ?? $finding->last_detected_at?->toIso8601String(),
            'detected_at' => $finding->last_detected_at?->toIso8601String(),
            'total_count' => $totalCount,
            'sample_limit' => $sampleLimit,
            'truncated' => $brokenLinks->count() > $sampleLimit || $totalCount > $sampleLimit,
            'links' => $brokenLinks
                ->take($sampleLimit)
                ->map(fn (array $link): array => $this->safeBrokenLinkEvidence($link, $finding))
                ->values()
                ->all(),
            'suppressed_links_count' => is_numeric($evidence['suppressed_links_count'] ?? null)
                ? (int) $evidence['suppressed_links_count']
                : $suppressedLinks->count(),
            'suppressed_links' => $suppressedLinks
                ->take($sampleLimit)
                ->map(fn (array $link): array => $this->safeBrokenLinkEvidence($link, $finding))
                ->values()
                ->all(),
            'truncation_note' => $brokenLinks->count() > $sampleLimit || $totalCount > $sampleLimit
                ? sprintf('Showing first %d broken-link evidence item(s); fetch the source Domain Monitor record for full protected crawl evidence.', $sampleLimit)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>
     */
    private function safeBrokenLinkEvidence(array $link, MonitoringFinding $finding): array
    {
        $linkHref = $this->stringOrNull($link['url'] ?? null) ?? $this->stringOrNull($link['href'] ?? null);

        return [
            'source_page_url' => $this->stringOrNull($link['found_on'] ?? null)
                ?? $this->stringOrNull($link['source_page_url'] ?? null),
            'link_href' => $linkHref,
            'final_url' => $this->stringOrNull($link['final_url'] ?? null)
                ?? $this->stringOrNull($link['resolved_url'] ?? null)
                ?? $linkHref,
            'http_status' => is_numeric($link['status'] ?? null) ? (int) $link['status'] : null,
            'failure_reason' => $this->stringOrNull($link['failure_reason'] ?? null)
                ?? $this->stringOrNull($link['error'] ?? null)
                ?? $this->stringOrNull($link['error_message'] ?? null),
            'relationship' => $this->stringOrNull($link['relationship'] ?? null),
            'classification' => $this->stringOrNull($link['classification'] ?? null)
                ?? $this->stringOrNull($link['policy_classification'] ?? null)
                ?? 'unclassified',
            'policy_reason' => $this->stringOrNull($link['policy_reason'] ?? null),
            'suppressed' => (bool) ($link['suppressed'] ?? false),
            'suppression_reason' => $this->stringOrNull($link['suppression_reason'] ?? null),
            'crawl_lane' => $finding->lane,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownerIssueLinkage(MonitoringFinding $finding, WebProperty $property): array
    {
        $evidence = is_array($finding->evidence) ? $finding->evidence : [];
        $candidates = $this->ownerIssueCandidates($evidence);

        if ($candidates === []) {
            return [
                'state' => 'current_owner_issue_missing',
                'active_owner_issue' => null,
                'related_owner_issues' => [],
                'finding_identity' => $this->findingIdentity($finding, $property),
            ];
        }

        $matching = collect($candidates)
            ->filter(fn (array $candidate): bool => $this->ownerIssueMatchesFinding($candidate, $finding, $property))
            ->values();
        $active = $matching
            ->first(fn (array $candidate): bool => ! $this->ownerIssueIsClosed($candidate));

        if (is_array($active)) {
            return [
                'state' => 'active_owner_issue_current',
                'active_owner_issue' => $this->safeOwnerIssue($active),
                'related_owner_issues' => collect($candidates)
                    ->reject(fn (array $candidate): bool => $candidate === $active)
                    ->map(fn (array $candidate): array => $this->safeOwnerIssue($candidate))
                    ->values()
                    ->all(),
                'finding_identity' => $this->findingIdentity($finding, $property),
            ];
        }

        return [
            'state' => $matching->isNotEmpty() ? 'current_owner_issue_closed' : 'stale_or_different_finding_owner_issue',
            'active_owner_issue' => null,
            'related_owner_issues' => collect($candidates)
                ->map(fn (array $candidate): array => $this->safeOwnerIssue($candidate))
                ->values()
                ->all(),
            'finding_identity' => $this->findingIdentity($finding, $property),
        ];
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<int, array<string, mixed>>
     */
    private function ownerIssueCandidates(array $evidence): array
    {
        $candidates = [];

        foreach (['owner_issue', 'owner_issue_candidate'] as $key) {
            if (is_array($evidence[$key] ?? null)) {
                $candidates[] = $evidence[$key];
            }
        }

        if (is_array($evidence['owner_issues'] ?? null)) {
            foreach ($evidence['owner_issues'] as $candidate) {
                if (is_array($candidate)) {
                    $candidates[] = $candidate;
                }
            }
        }

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function ownerIssueMatchesFinding(array $candidate, MonitoringFinding $finding, WebProperty $property): bool
    {
        $candidateIssueClass = $this->stringOrNull($candidate['issue_class'] ?? null)
            ?? $this->stringOrNull($candidate['finding_type'] ?? null);
        $candidateIssueId = $this->stringOrNull($candidate['issue_id'] ?? null);
        $candidateFingerprint = $this->stringOrNull($candidate['finding_fingerprint'] ?? null)
            ?? $this->stringOrNull($candidate['fingerprint'] ?? null);

        if ($candidateIssueClass !== null && $candidateIssueClass !== $finding->finding_type) {
            return false;
        }

        if ($candidateIssueId !== null && $candidateIssueId !== $finding->issue_id) {
            return false;
        }

        if ($candidateFingerprint !== null && $candidateFingerprint !== $this->findingFingerprint($finding, $property)) {
            return false;
        }

        return $candidateIssueClass !== null || $candidateIssueId !== null || $candidateFingerprint !== null;
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function ownerIssueIsClosed(array $candidate): bool
    {
        $state = strtolower((string) ($candidate['state'] ?? $candidate['status'] ?? ''));

        return in_array($state, ['closed', 'done', 'resolved'], true);
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function safeOwnerIssue(array $candidate): array
    {
        return [
            'url' => $this->stringOrNull($candidate['url'] ?? null)
                ?? $this->stringOrNull($candidate['html_url'] ?? null)
                ?? $this->stringOrNull($candidate['owner_issue_url'] ?? null),
            'repo' => $this->stringOrNull($candidate['repo'] ?? null)
                ?? $this->stringOrNull($candidate['repository'] ?? null),
            'number' => is_numeric($candidate['number'] ?? null) ? (int) $candidate['number'] : null,
            'state' => $this->stringOrNull($candidate['state'] ?? null)
                ?? $this->stringOrNull($candidate['status'] ?? null),
            'issue_class' => $this->stringOrNull($candidate['issue_class'] ?? null)
                ?? $this->stringOrNull($candidate['finding_type'] ?? null),
            'issue_id' => $this->stringOrNull($candidate['issue_id'] ?? null),
            'finding_fingerprint' => $this->stringOrNull($candidate['finding_fingerprint'] ?? null)
                ?? $this->stringOrNull($candidate['fingerprint'] ?? null),
            'relationship' => $this->stringOrNull($candidate['relationship'] ?? null) ?? 'related',
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
