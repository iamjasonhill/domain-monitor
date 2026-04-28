<?php

namespace App\Console\Commands;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Services\DomainHealthCheckRunner;
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
        MonitoringFindingManager $findings,
        DomainHealthCheckRunner $domainHealthCheckRunner
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
            $expectedMeasurementId = $this->expectedMeasurementId($property);
            $audits = match ($lane) {
                'critical_live' => [
                    'critical.uptime' => [
                        'title' => 'Root uptime failure on live property',
                        'issue_type' => 'incident',
                        'audit' => $siteScanner->auditUptime($property, $timeout),
                    ],
                    'critical.http_response' => [
                        'title' => 'Root HTTP response failure on live property',
                        'issue_type' => 'incident',
                        'audit' => $siteScanner->auditHttpResponse($property, $timeout),
                    ],
                    'critical.ssl' => [
                        'title' => 'SSL certificate failure on live property',
                        'issue_type' => 'incident',
                        'audit' => $siteScanner->auditSsl($property, $timeout),
                    ],
                    'critical.redirect_policy' => [
                        'title' => 'Root redirect policy mismatch on live property',
                        'issue_type' => 'incident',
                        'audit' => $siteScanner->auditRedirectPolicy($property, $timeout),
                    ],
                ],
                'marketing_integrity' => $this->marketingIntegrityAudits(
                    property: $property,
                    ga4Scanner: $ga4Scanner,
                    siteScanner: $siteScanner,
                    timeout: $timeout,
                    expectedMeasurementId: $expectedMeasurementId
                ),
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
                'deep_audit' => $this->deepAuditAudits(
                    property: $property,
                    siteScanner: $siteScanner,
                    healthCheckRunner: $domainHealthCheckRunner,
                    primaryDomainId: $primaryDomain?->id
                ),
                default => [],
            };

            $verdicts = [];

            foreach ($audits as $findingType => $definition) {
                $audit = (array) $definition['audit'];
                $outcome = $this->syncFinding(
                    findings: $findings,
                    property: $property,
                    findingType: $findingType,
                    issueType: $this->issueTypeForAudit(
                        findingType: $findingType,
                        defaultIssueType: (string) $definition['issue_type'],
                        audit: $audit
                    ),
                    title: (string) $definition['title'],
                    audit: $audit,
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

        return $query
            ->get()
            ->filter(function (WebProperty $property) use ($lane): bool {
                if ($lane === 'critical_live') {
                    $primaryDomain = $property->primaryDomainModel();

                    return $primaryDomain !== null
                        && $primaryDomain->monitoringSkipReason('uptime') === null
                        && $primaryDomain->monitoringSkipReason('http') === null
                        && $primaryDomain->monitoringSkipReason('ssl') === null;
                }

                if ($lane === 'deep_audit') {
                    $primaryDomain = $property->primaryDomainModel();

                    return $primaryDomain !== null
                        && $primaryDomain->monitoringSkipReason('broken_links') === null
                        && $primaryDomain->monitoringSkipReason('external_links') === null;
                }

                return $property->production_url !== null || $property->primaryDomainName() !== null;
            })
            ->values();
    }

    /**
     * @return array<string, array{title: string, issue_type: string, audit: array<string, mixed>}>
     */
    private function marketingIntegrityAudits(
        WebProperty $property,
        Ga4SignalScanner $ga4Scanner,
        PropertySiteSignalScanner $siteScanner,
        int $timeout,
        ?string $expectedMeasurementId
    ): array {
        $audits = [
            'marketing.indexability' => [
                'title' => 'Homepage indexability mismatch on live property',
                'issue_type' => 'cleanup',
                'audit' => $siteScanner->auditIndexability($property, $timeout),
            ],
        ];

        $audits['marketing.ga4_install'] = [
            'title' => 'GA4 install mismatch on live property',
            'issue_type' => 'regression',
            'audit' => $this->homepageGa4Required($property)
                ? $ga4Scanner->auditPropertyHomepage($property, $timeout)
                : $this->optionalHomepageGa4Audit($property),
        ];

        if ($expectedMeasurementId !== null) {
            $audits['marketing.conversion_surface_ga4'] = [
                'title' => 'GA4 mismatch on conversion surfaces',
                'issue_type' => 'regression',
                'audit' => $ga4Scanner->auditConversionSurfaces($property, $timeout),
            ];
        }

        if ($this->hasQuoteHandoffTargets($property)) {
            $audits['marketing.quote_handoff_integrity'] = [
                'title' => 'Quote handoff mismatch on live property',
                'issue_type' => 'regression',
                'audit' => $siteScanner->auditQuoteHandoffIntegrity($property),
            ];
        } elseif (! $this->quoteHandoffRequired($property)) {
            $audits['marketing.quote_handoff_integrity'] = [
                'title' => 'Quote handoff mismatch on live property',
                'issue_type' => 'regression',
                'audit' => $this->optionalQuoteHandoffAudit($property),
            ];
        }

        return $audits;
    }

    /**
     * @return array<string, array{title: string, issue_type: string, audit: array<string, mixed>}>
     */
    private function deepAuditAudits(
        WebProperty $property,
        PropertySiteSignalScanner $siteScanner,
        DomainHealthCheckRunner $healthCheckRunner,
        ?string $primaryDomainId
    ): array {
        if ($primaryDomainId === null || ! $property->primaryDomainModel()) {
            return [];
        }

        return [
            'seo.broken_links' => [
                'title' => 'Broken links found during deep audit crawl',
                'issue_type' => 'cleanup',
                'audit' => $siteScanner->auditBrokenLinks($property, $healthCheckRunner),
            ],
            'cleanup.external_links_inventory' => [
                'title' => 'Off-host links found during deep audit inventory',
                'issue_type' => 'cleanup',
                'audit' => $siteScanner->auditExternalLinks($property, $healthCheckRunner),
            ],
        ];
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

    /**
     * @param  array<string, mixed>  $audit
     */
    private function issueTypeForAudit(string $findingType, string $defaultIssueType, array $audit): string
    {
        if ($findingType !== 'marketing.ga4_install') {
            return $defaultIssueType;
        }

        $evidence = is_array($audit['evidence'] ?? null) ? $audit['evidence'] : [];

        if (($evidence['verdict'] ?? null) !== 'missing_expected_measurement_id') {
            return $defaultIssueType;
        }

        $bestUrl = $evidence['best_url'] ?? null;
        $detectedMeasurementIds = $evidence['detected_measurement_ids'] ?? [];

        if (
            ! is_string($bestUrl)
            && (is_countable($detectedMeasurementIds) ? count($detectedMeasurementIds) : 0) === 0
        ) {
            return 'cleanup';
        }

        return $defaultIssueType;
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

    private function hasQuoteHandoffTargets(WebProperty $property): bool
    {
        if (! $this->quoteHandoffRequired($property)) {
            return false;
        }

        foreach ([
            $property->target_household_quote_url,
            $property->target_household_booking_url,
            $property->target_vehicle_quote_url,
            $property->target_vehicle_booking_url,
        ] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function quoteHandoffRequired(WebProperty $property): bool
    {
        $override = $this->domainOverrideForProperty($property);

        return data_get($override, 'analytics_monitoring.quote_handoff_required') !== false;
    }

    /**
     * Some apex domains are operational application shells, not acquisition
     * pages. Their quote attribution is intentionally owned by branded
     * marketing sites or quote/conversion subdomains.
     */
    private function homepageGa4Required(WebProperty $property): bool
    {
        $override = $this->domainOverrideForProperty($property);

        return data_get($override, 'analytics_monitoring.homepage_ga4_required') !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalHomepageGa4Audit(WebProperty $property): array
    {
        $override = $this->domainOverrideForProperty($property);
        $reason = data_get($override, 'analytics_monitoring.reason');

        return [
            'status' => 'pass',
            'summary' => 'Homepage GA4 is intentionally not required for this app-shell property.',
            'evidence' => [
                'verdict' => 'homepage_ga4_not_required',
                'reason' => is_string($reason) && trim($reason) !== ''
                    ? trim($reason)
                    : 'Operational app shell; attribution is handled on branded marketing sites or quote/conversion surfaces.',
                'primary_domain' => $property->primaryDomainName(),
                'property_type' => $property->property_type,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function optionalQuoteHandoffAudit(WebProperty $property): array
    {
        $override = $this->domainOverrideForProperty($property);
        $reason = data_get($override, 'analytics_monitoring.reason');

        return [
            'status' => 'pass',
            'summary' => 'Quote handoff checks are intentionally not required for this app-shell property.',
            'evidence' => [
                'verdict' => 'quote_handoff_not_required',
                'reason' => is_string($reason) && trim($reason) !== ''
                    ? trim($reason)
                    : 'Operational app shell; users should normally enter through branded marketing sites or quote/conversion surfaces.',
                'primary_domain' => $property->primaryDomainName(),
                'property_type' => $property->property_type,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function domainOverrideForProperty(WebProperty $property): array
    {
        $domain = $property->primaryDomainName();

        if (! is_string($domain) || trim($domain) === '') {
            return [];
        }

        $overrides = config('domain_monitor.web_property_bootstrap.overrides', []);
        if (! is_array($overrides)) {
            return [];
        }

        $override = $overrides[mb_strtolower(trim($domain))] ?? [];

        return is_array($override) ? $override : [];
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
