<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class FleetPropertyContextRefreshService
{
    public function __construct(
        private readonly PropertyConversionLinkScanner $conversionLinkScanner,
        private readonly DomainHealthCheckRunner $domainHealthCheckRunner,
        private readonly SearchConsoleApiEnrichmentRefresher $searchConsoleApiEnrichmentRefresher,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   property_slug: string,
     *   primary_domain: string|null,
     *   refreshed: array<string, array<string, mixed>>
     * }
     */
    public function refresh(WebProperty|string $property, bool $forceSearchConsoleApiEnrichment = false, ?int $searchConsoleStaleDays = null): array
    {
        $property = $property instanceof WebProperty
            ? $this->hydrateProperty($property)
            : $this->findPropertyOrFail($property);

        $eligibility = $property->coverageEligibility();

        if (! $eligibility['eligible']) {
            return $this->ineligiblePropertySummary($property, (string) $eligibility['reason']);
        }

        $primaryDomain = $this->refreshableDomain($property);

        $conversionLinks = $this->refreshConversionLinks($property);
        $brokenLinks = $this->refreshHealthCheck($primaryDomain, 'broken_links');
        $securityHeaders = $this->refreshHealthCheck($primaryDomain, 'security_headers');
        $seo = $this->refreshHealthCheck($primaryDomain, 'seo');
        $searchConsole = $this->searchConsoleApiEnrichmentRefresher->refreshProperty(
            $property,
            $searchConsoleStaleDays ?? max(1, (int) config('services.google.search_console.api_refresh_stale_days', 7)),
            capturedBy: 'fleet_context_refresh',
            force: $forceSearchConsoleApiEnrichment,
        );

        $steps = [
            'conversion_links' => $conversionLinks,
            'broken_links' => $brokenLinks,
            'security_headers' => $securityHeaders,
            'seo' => $seo,
            'search_console_api_enrichment' => $searchConsole,
        ];

        $success = collect($steps)->every(
            fn (array $step): bool => $step['status'] !== 'failed'
        );

        return [
            'success' => $success,
            'property_slug' => $property->slug,
            'primary_domain' => $primaryDomain?->domain,
            'refreshed' => $steps,
        ];
    }

    private function findPropertyOrFail(string $slug): WebProperty
    {
        $property = WebProperty::query()
            ->where('slug', $slug)
            ->first();

        if (! $property instanceof WebProperty) {
            throw (new ModelNotFoundException)->setModel(WebProperty::class, [$slug]);
        }

        return $this->hydrateProperty($property);
    }

    private function hydrateProperty(WebProperty $property): WebProperty
    {
        $property->loadMissing([
            'propertyDomains.domain.platform',
            'propertyDomains.domain.tags',
            'analyticsSources.latestSearchConsoleCoverage',
            'latestSeoBaselineForProperty',
        ]);

        return $property;
    }

    /**
     * @return array{status:'refreshed'|'failed',scanned_at:string|null,reason:string|null}
     */
    private function refreshConversionLinks(WebProperty $property): array
    {
        try {
            $scan = $this->conversionLinkScanner->persistForProperty($property);

            return [
                'status' => 'refreshed',
                'scanned_at' => $scan['conversion_links_scanned_at']->toIso8601String(),
                'reason' => null,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Fleet property conversion link refresh failed', [
                'web_property_id' => $property->id,
                'property_slug' => $property->slug,
                'exception' => $exception->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'scanned_at' => $property->fresh()?->conversion_links_scanned_at?->toIso8601String(),
                'reason' => 'conversion_links_refresh_failed',
            ];
        }
    }

    /**
     * @return array{status:'refreshed'|'skipped'|'failed',checked_at:string|null,reason:string|null}
     */
    private function refreshHealthCheck(?\App\Models\Domain $domain, string $type): array
    {
        if (! $domain instanceof \App\Models\Domain) {
            return [
                'status' => 'skipped',
                'checked_at' => null,
                'reason' => 'Property does not have a primary domain.',
            ];
        }

        return $this->domainHealthCheckRunner->run($domain, $type);
    }

    private function refreshableDomain(WebProperty $property): ?Domain
    {
        $canonicalLink = $property->canonicalDomainLink();

        if ($canonicalLink !== null) {
            return $canonicalLink->domain;
        }

        return $property->primaryDomainModel();
    }

    /**
     * @return array{
     *   success: bool,
     *   property_slug: string,
     *   primary_domain: string|null,
     *   refreshed: array{
     *     conversion_links: array{status:'skipped',scanned_at:null,reason:string},
     *     broken_links: array{status:'skipped',checked_at:null,reason:string},
     *     security_headers: array{status:'skipped',checked_at:null,reason:string},
     *     seo: array{status:'skipped',checked_at:null,reason:string},
     *     search_console_api_enrichment: array{status:'skipped',captured_at:null,reason:'ineligible',message:string}
     *   }
     * }
     */
    private function ineligiblePropertySummary(WebProperty $property, string $reason): array
    {
        $domain = $this->refreshableDomain($property);

        return [
            'success' => true,
            'property_slug' => $property->slug,
            'primary_domain' => $domain ? $domain->domain : null,
            'refreshed' => [
                'conversion_links' => [
                    'status' => 'skipped',
                    'scanned_at' => null,
                    'reason' => $reason,
                ],
                'broken_links' => [
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => $reason,
                ],
                'security_headers' => [
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => $reason,
                ],
                'seo' => [
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => $reason,
                ],
                'search_console_api_enrichment' => [
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'ineligible',
                    'message' => 'Property is not eligible for Fleet context refresh.',
                ],
            ],
        ];
    }
}
