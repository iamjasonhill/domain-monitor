<?php

namespace App\Console\Commands;

use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoUnknownTriageCandidate;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TriageFleetTechnicalSeoUnknownsCommand extends Command
{
    protected $signature = 'monitoring:triage-fleet-technical-seo-unknowns
                            {--threshold=2 : Minimum repeated unknown results before candidate creation}
                            {--min-age-hours=24 : Minimum age of oldest matching unknown before candidate creation}
                            {--limit=50 : Maximum candidates to create or list}
                            {--dry-run : List candidates without writing records}';

    protected $description = 'Create durable review candidates for repeated Fleet technical SEO unknown audit evidence.';

    public function handle(): int
    {
        $threshold = max(1, (int) $this->option('threshold'));
        $minAgeHours = max(0, (int) $this->option('min-age-hours'));
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($minAgeHours);

        $groups = $this->unknownResultGroups();
        $candidates = $groups
            ->map(fn (Collection $results): ?array => $this->candidateForGroup($results, $threshold, $cutoff))
            ->filter()
            ->take($limit)
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('No Fleet technical SEO unknown triage candidates met the threshold.');

            return self::SUCCESS;
        }

        foreach ($candidates as $candidate) {
            $this->line(sprintf(
                '%s %s %s retry_count=%d owner=%s',
                $dryRun ? '[dry-run]' : '[candidate]',
                $candidate['property_slug'],
                $candidate['check_id'],
                $candidate['retry_count'],
                $candidate['owner_route']
            ));

            if ($dryRun) {
                continue;
            }

            FleetTechnicalSeoUnknownTriageCandidate::query()->updateOrCreate(
                ['dedupe_key' => $candidate['dedupe_key']],
                $candidate
            );
        }

        $this->info(sprintf(
            '%s %d Fleet technical SEO unknown triage candidate(s).',
            $dryRun ? 'Listed' : 'Stored',
            $candidates->count()
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<string, Collection<int, FleetTechnicalSeoAuditResult>>
     */
    private function unknownResultGroups(): Collection
    {
        if (
            ! Schema::hasTable('fleet_technical_seo_audit_results')
            || ! Schema::hasTable('fleet_technical_seo_audit_runs')
            || ! Schema::hasTable('fleet_technical_seo_unknown_triage_candidates')
        ) {
            return collect();
        }

        $results = FleetTechnicalSeoAuditResult::query()
            ->with(['auditRun.webProperty.primaryDomain'])
            ->where('result_status', FleetTechnicalSeoAuditResult::STATUS_UNKNOWN)
            ->whereHas('auditRun', fn ($query) => $query->whereNotNull('finished_at'))
            ->get();

        return collect($results)
            ->groupBy(fn (FleetTechnicalSeoAuditResult $result): string => $this->dedupeKeyForResult($result));
    }

    /**
     * @param  Collection<int, FleetTechnicalSeoAuditResult>  $results
     * @return array<string, mixed>|null
     */
    private function candidateForGroup(Collection $results, int $threshold, Carbon $cutoff): ?array
    {
        if ($results->count() < $threshold) {
            return null;
        }

        $sorted = $results->sortBy(fn (FleetTechnicalSeoAuditResult $result): int => $result->auditRun->started_at?->getTimestamp() ?? 0)->values();
        $first = $sorted->first();
        $latest = $sorted->last();

        if (! $first instanceof FleetTechnicalSeoAuditResult || ! $latest instanceof FleetTechnicalSeoAuditResult) {
            return null;
        }

        $firstSeenAt = $first->auditRun->started_at ?? $first->created_at;
        if ($firstSeenAt instanceof Carbon && $firstSeenAt->greaterThan($cutoff)) {
            return null;
        }

        $property = $latest->auditRun->webProperty;
        $primaryDomain = $property->primaryDomainModel();
        $ownerRoute = $this->ownerRouteForResult($latest);
        $coverageUnit = $this->coverageUnitForResult($property->slug, $latest);
        $auditProfile = $latest->auditRun->trigger_type;
        $dedupeKey = $this->dedupeKey($auditProfile, $coverageUnit, $latest->check_id, $ownerRoute);
        $payload = [
            'audit_profile' => $auditProfile,
            'coverage_unit' => $coverageUnit,
            'check_id' => $latest->check_id,
            'property_slug' => $property->slug,
            'domain' => $primaryDomain?->domain,
            'owner_route' => $ownerRoute,
            'retry_count' => $results->count(),
            'dedupe_key' => $dedupeKey,
            'first_seen_at' => $firstSeenAt?->toIso8601String(),
            'last_seen_at' => ($latest->auditRun->started_at ?? $latest->created_at)?->toIso8601String(),
            'latest_audit_run_id' => $latest->auditRun->id,
            'latest_audit_result_id' => $latest->id,
            'latest_evidence' => is_array($latest->evidence) ? $latest->evidence : [],
        ];

        return [
            'dedupe_key' => $dedupeKey,
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain?->id,
            'property_slug' => $property->slug,
            'audit_profile' => $auditProfile,
            'coverage_unit' => $coverageUnit,
            'check_id' => $latest->check_id,
            'owner_route' => $ownerRoute,
            'latest_audit_run_id' => $latest->auditRun->id,
            'latest_audit_result_id' => $latest->id,
            'retry_count' => $results->count(),
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => $latest->auditRun->started_at ?? $latest->created_at,
            'status' => FleetTechnicalSeoUnknownTriageCandidate::STATUS_OPEN,
            'candidate_payload' => $payload,
        ];
    }

    private function dedupeKeyForResult(FleetTechnicalSeoAuditResult $result): string
    {
        $property = $result->auditRun->webProperty;
        $ownerRoute = $this->ownerRouteForResult($result);

        return $this->dedupeKey(
            $result->auditRun->trigger_type,
            $this->coverageUnitForResult($property->slug, $result),
            $result->check_id,
            $ownerRoute
        );
    }

    private function dedupeKey(string $auditProfile, string $coverageUnit, string $checkId, string $ownerRoute): string
    {
        return hash('sha256', implode('|', [$auditProfile, $coverageUnit, $checkId, $ownerRoute]));
    }

    private function coverageUnitForResult(string $propertySlug, FleetTechnicalSeoAuditResult $result): string
    {
        if ($result->target_url !== null && trim($result->target_url) !== '') {
            return 'url:'.trim($result->target_url);
        }

        return 'web_property:'.$propertySlug;
    }

    private function ownerRouteForResult(FleetTechnicalSeoAuditResult $result): string
    {
        $ownerSystem = strtolower(trim((string) $result->owner_system));

        if ($ownerSystem === 'mm-google' || str_starts_with($result->check_id, 'analytics.')) {
            return 'mm-google';
        }

        if (in_array($ownerSystem, ['fleet', 'site-repo'], true)) {
            return 'fleet';
        }

        if ($ownerSystem === 'domain-monitor') {
            return 'domain-monitor';
        }

        return 'control';
    }
}
