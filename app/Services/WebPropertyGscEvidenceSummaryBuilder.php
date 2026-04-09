<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Carbon;

class WebPropertyGscEvidenceSummaryBuilder
{
    /**
     * @return array{
     *   has_issue_detail: bool,
     *   issue_detail_snapshot_count: int,
     *   latest_issue_detail_captured_at: string|null,
     *   has_api_enrichment: bool,
     *   api_snapshot_count: int,
     *   latest_api_captured_at: string|null
     * }
     */
    public function build(WebProperty $property): array
    {
        $hasIssueDetail = (bool) ($property->getAttribute('has_gsc_issue_detail') ?? false);
        $snapshotCount = (int) ($property->getAttribute('gsc_issue_detail_snapshot_count') ?? 0);
        $latestCapturedAt = $property->getAttribute('gsc_issue_detail_last_captured_at');
        $hasApiEnrichment = (bool) ($property->getAttribute('has_gsc_api_enrichment') ?? false);
        $apiSnapshotCount = (int) ($property->getAttribute('gsc_api_snapshot_count') ?? 0);
        $latestApiCapturedAt = $property->getAttribute('gsc_api_last_captured_at');

        return [
            'has_issue_detail' => $hasIssueDetail,
            'issue_detail_snapshot_count' => $snapshotCount,
            'latest_issue_detail_captured_at' => $this->normalizeTimestamp($latestCapturedAt),
            'has_api_enrichment' => $hasApiEnrichment,
            'api_snapshot_count' => $apiSnapshotCount,
            'latest_api_captured_at' => $this->normalizeTimestamp($latestApiCapturedAt),
        ];
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value, 'UTC')->utc()->toIso8601String();
        }

        return null;
    }
}
