<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Services\Ga4SignalScanner;
use App\Services\MonitoringFindingManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RunMonitoringLane extends Command
{
    protected $signature = 'monitoring:run-lane
                            {lane : Monitoring lane slug}
                            {--property= : Optional web property slug}
                            {--domain= : Optional primary domain name}
                            {--timeout=10 : HTTP timeout in seconds for live verification requests}';

    protected $description = 'Run an explicit monitoring lane and emit deduped findings through the Brain path.';

    public function handle(Ga4SignalScanner $scanner, MonitoringFindingManager $findings): int
    {
        $lane = (string) $this->argument('lane');
        $timeout = max(1, (int) $this->option('timeout'));
        $laneConfig = config('domain_monitor.monitoring_lanes.'.$lane);

        if (! is_array($laneConfig)) {
            $this->error(sprintf('Unknown monitoring lane [%s].', $lane));

            return self::FAILURE;
        }

        if ($lane !== 'marketing_integrity') {
            $this->warn(sprintf('Lane [%s] is configured but does not have runnable checks yet.', $lane));

            return self::SUCCESS;
        }

        $properties = $this->marketingIntegrityProperties();

        if ($properties->isEmpty()) {
            $this->warn('No active GA4-linked properties matched the requested lane scope.');

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

            $homepageAudit = $scanner->auditPropertyHomepage($property, $timeout);
            $homepageOutcome = $this->syncFinding(
                findings: $findings,
                property: $property,
                findingType: 'marketing.ga4_install',
                title: 'GA4 install mismatch on live property',
                audit: $homepageAudit,
                primaryDomainId: $primaryDomain?->id
            );

            $surfaceAudit = $scanner->auditConversionSurfaces($property, $timeout);
            $surfaceOutcome = $this->syncFinding(
                findings: $findings,
                property: $property,
                findingType: 'marketing.conversion_surface_ga4',
                title: 'GA4 mismatch on conversion surfaces',
                audit: $surfaceAudit,
                primaryDomainId: $primaryDomain?->id
            );

            $opened += (int) in_array($homepageOutcome, ['opened', 'reopened'], true);
            $updated += (int) ($homepageOutcome === 'updated');
            $recovered += (int) ($homepageOutcome === 'recovered');

            $opened += (int) in_array($surfaceOutcome, ['opened', 'reopened'], true);
            $updated += (int) ($surfaceOutcome === 'updated');
            $recovered += (int) ($surfaceOutcome === 'recovered');

            $this->line(sprintf(
                '[%s] homepage=%s | conversion_surfaces=%s',
                $property->slug,
                $homepageAudit['verdict'],
                $surfaceAudit['verdict']
            ));
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
    private function marketingIntegrityProperties(): Collection
    {
        $propertyFilter = $this->optionString('property');
        $domainFilter = $this->optionString('domain');

        return WebProperty::query()
            ->where('status', 'active')
            ->with([
                'primaryDomain',
                'propertyDomains.domain',
                'analyticsSources',
                'conversionSurfaces.analyticsSource',
                'conversionSurfaces.domain',
            ])
            ->whereHas('analyticsSources', function (Builder $query): void {
                $query->where('provider', 'ga4')
                    ->where('status', 'active');
            })
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
            )
            ->get()
            ->filter(fn (WebProperty $property): bool => $this->expectedMeasurementId($property) !== null)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $audit
     */
    private function syncFinding(
        MonitoringFindingManager $findings,
        WebProperty $property,
        string $findingType,
        string $title,
        array $audit,
        ?string $primaryDomainId
    ): string {
        if (($audit['status'] ?? null) === 'fail') {
            return $findings->reportPropertyFinding(
                property: $property,
                findingType: $findingType,
                lane: 'marketing_integrity',
                issueType: 'regression',
                title: $title,
                summary: (string) ($audit['summary'] ?? ''),
                evidence: $audit['evidence'] ?? [],
                primaryDomainId: $primaryDomainId
            );
        }

        return $findings->recoverPropertyFinding(
            property: $property,
            findingType: $findingType,
            lane: 'marketing_integrity',
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

        if (! $source instanceof PropertyAnalyticsSource) {
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
