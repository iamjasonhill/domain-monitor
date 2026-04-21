<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Services\Ga4SignalScanner;
use App\Services\MonitoringFindingManager;
use App\Services\PropertySiteSignalScanner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RunMonitoringLane extends Command
{
    protected $signature = 'monitoring:run-lane
                            {lane : Monitoring lane slug}
                            {--property= : Optional web property slug}
                            {--domain= : Optional primary domain name}
                            {--timeout=10 : HTTP timeout in seconds for live verification requests}';

    protected $description = 'Run an explicit monitoring lane and emit deduped findings through the Brain path.';

    public function handle(
        Ga4SignalScanner $ga4Scanner,
        PropertySiteSignalScanner $siteScanner,
        MonitoringFindingManager $findings
    ): int {
        $lane = (string) $this->argument('lane');
        $timeout = max(1, (int) $this->option('timeout'));
        $laneConfig = config('domain_monitor.monitoring_lanes.'.$lane);

        if (! is_array($laneConfig)) {
            $this->error(sprintf('Unknown monitoring lane [%s].', $lane));

            return self::FAILURE;
        }

        $properties = $this->laneProperties($lane);

        if ($properties->isEmpty()) {
            $this->warn(sprintf('No active properties matched the requested [%s] lane scope.', $lane));

            return self::SUCCESS;
        }

        $opened = 0;
        $updated = 0;
        $recovered = 0;

        foreach ($properties as $property) {
            $property->loadMissing([
                'primaryDomain',
                'propertyDomains.domain',
                'analyticsSources',
                'conversionSurfaces.analyticsSource',
                'conversionSurfaces.domain',
            ]);

            $primaryDomain = $property->primaryDomainModel();
            $audits = match ($lane) {
                'critical_live' => [
                    'critical.redirect_policy' => [
                        'title' => 'Root redirect policy mismatch on live property',
                        'issue_type' => 'incident',
                        'audit' => $siteScanner->auditRedirectPolicy($property, $timeout),
                    ],
                ],
                'marketing_integrity' => [
                    'marketing.ga4_install' => [
                        'title' => 'GA4 install mismatch on live property',
                        'issue_type' => 'regression',
                        'audit' => $ga4Scanner->auditPropertyHomepage($property, $timeout),
                    ],
                    'marketing.conversion_surface_ga4' => [
                        'title' => 'GA4 mismatch on conversion surfaces',
                        'issue_type' => 'regression',
                        'audit' => $ga4Scanner->auditConversionSurfaces($property, $timeout),
                    ],
                    'marketing.indexability' => [
                        'title' => 'Homepage indexability mismatch on live property',
                        'issue_type' => 'regression',
                        'audit' => $siteScanner->auditIndexability($property, $timeout),
                    ],
                ],
                'seo_agent_readiness' => [
                    'seo.structured_data' => [
                        'title' => 'Structured data missing or invalid on homepage',
                        'issue_type' => 'readiness_gap',
                        'audit' => $siteScanner->auditStructuredData($property, $timeout),
                    ],
                    'seo.agent_readiness' => [
                        'title' => 'Agent-readiness files missing on live property',
                        'issue_type' => 'readiness_gap',
                        'audit' => $siteScanner->auditAgentReadiness($property, $timeout),
                    ],
                ],
                default => [],
            };

            $verdicts = [];

            foreach ($audits as $findingType => $definition) {
                $outcome = $this->syncFinding(
                    findings: $findings,
                    property: $property,
                    findingType: $findingType,
                    issueType: (string) $definition['issue_type'],
                    title: (string) $definition['title'],
                    audit: (array) $definition['audit'],
                    primaryDomainId: $primaryDomain?->id
                );

                $opened += (int) in_array($outcome, ['opened', 'reopened'], true);
                $updated += (int) ($outcome === 'updated');
                $recovered += (int) ($outcome === 'recovered');
                $verdicts[] = sprintf(
                    '%s=%s',
                    Str::afterLast($findingType, '.'),
                    (string) data_get($definition, 'audit.verdict', 'unknown')
                );
            }

            $this->line(sprintf('[%s] %s', $property->slug, implode(' | ', $verdicts)));
        }

        $this->newLine();
        $this->info(sprintf(
            'Lane [%s] complete for %d propertie(s). Findings opened/reopened: %d, updated: %d, recovered: %d.',
            $lane,
            $properties->count(),
            $opened,
            $updated,
            $recovered
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, WebProperty>
     */
    private function laneProperties(string $lane): Collection
    {
        $propertyFilter = $this->optionString('property');
        $domainFilter = $this->optionString('domain');

        $query = WebProperty::query()
            ->where('status', 'active')
            ->with([
                'primaryDomain',
                'propertyDomains.domain',
                'analyticsSources',
                'conversionSurfaces.analyticsSource',
                'conversionSurfaces.domain',
            ])
            ->when(
                $propertyFilter,
                fn (Builder $query) => $query->where('slug', $propertyFilter)
            )
            ->when(
                $domainFilter,
                fn (Builder $query) => $query->whereHas(
                    'propertyDomains.domain',
                    fn (Builder $domainQuery) => $domainQuery->where('domain', $domainFilter)
                )
            );

        if ($lane === 'marketing_integrity') {
            $query->whereHas('analyticsSources', function (Builder $query): void {
                $query->where('provider', 'ga4')
                    ->where('status', 'active');
            });
        }

        return $query
            ->get()
            ->filter(function (WebProperty $property) use ($lane): bool {
                if ($lane === 'marketing_integrity') {
                    return $this->expectedMeasurementId($property) !== null;
                }

                return $property->production_url !== null || $property->primaryDomainName() !== null;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    private function syncFinding(
        MonitoringFindingManager $findings,
        WebProperty $property,
        string $findingType,
        string $issueType,
        string $title,
        array $audit,
        ?string $primaryDomainId
    ): string {
        if (($audit['status'] ?? null) === 'fail') {
            return $findings->reportPropertyFinding(
                property: $property,
                findingType: $findingType,
                lane: $this->argument('lane'),
                issueType: $issueType,
                title: $title,
                summary: (string) ($audit['summary'] ?? ''),
                evidence: $audit['evidence'] ?? [],
                primaryDomainId: $primaryDomainId
            );
        }

        return $findings->recoverPropertyFinding(
            property: $property,
            findingType: $findingType,
            lane: (string) $this->argument('lane'),
            recoverySummary: (string) ($audit['summary'] ?? ''),
            recoveryEvidence: $audit['evidence'] ?? [],
            primaryDomainId: $primaryDomainId
        );
    }

    private function optionString(string $name): ?string
    {
        $value = $this->option($name);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function expectedMeasurementId(WebProperty $property): ?string
    {
        $source = $property->primaryAnalyticsSource('ga4');

        if (! $source instanceof PropertyAnalyticsSource || $source->status !== 'active') {
            return null;
        }

        $providerConfig = $source->provider_config;
        $measurementId = is_array($providerConfig)
            ? ($providerConfig['measurement_id'] ?? null)
            : null;

        if (! is_string($measurementId) || trim($measurementId) === '') {
            $measurementId = $source->external_id;
        }

        if (trim($measurementId) === '') {
            return null;
        }

        $normalized = strtoupper(trim($measurementId));

        return $normalized !== '' ? $normalized : null;
    }
}
