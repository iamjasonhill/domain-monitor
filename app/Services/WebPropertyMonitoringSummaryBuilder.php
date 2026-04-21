<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
                    'issue_class' => $finding->finding_type,
                    'severity' => $this->severityFor($finding),
                    'lane' => $finding->lane,
                    'issue_type' => $finding->issue_type,
                    'title' => $finding->title,
                    'summary' => $finding->summary,
                    'status' => $finding->status,
                    'detected_at' => $finding->last_detected_at?->toIso8601String(),
                    'domain' => $finding->domain instanceof Domain ? $finding->domain->domain : null,
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
            $loadedFindings->loadMissing('domain:id,domain');

            return $loadedFindings
                ->filter(fn (MonitoringFinding $finding): bool => $finding->status === MonitoringFinding::STATUS_OPEN)
                ->sortByDesc(fn (MonitoringFinding $finding): int => $finding->last_detected_at?->getTimestamp() ?? 0)
                ->values();
        }

        return $property->monitoringFindings()
            ->where('status', MonitoringFinding::STATUS_OPEN)
            ->with('domain:id,domain')
            ->orderByDesc('last_detected_at')
            ->get();
    }

    private function severityFor(MonitoringFinding $finding): string
    {
        return in_array($finding->issue_type, ['incident', 'regression'], true)
            ? 'must_fix'
            : 'should_fix';
    }
}
