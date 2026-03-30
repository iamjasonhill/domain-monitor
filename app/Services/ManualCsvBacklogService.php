<?php

namespace App\Services;

use App\Models\DomainSeoBaseline;
use App\Models\WebProperty;

class ManualCsvBacklogService
{
    /**
     * @return list<array{
     *     property: WebProperty,
     *     automation: array{status: string, label: string, reason: string},
     *     primary_domain: string|null,
     *     repository: \App\Models\PropertyRepository|null,
     *     matomo_source: \App\Models\PropertyAnalyticsSource|null,
     *     search_console_coverage: \App\Models\SearchConsoleCoverageStatus|null,
     *     latest_baseline: DomainSeoBaseline
     * }>
     */
    public function pendingItems(): array
    {
        $manualCsvTagName = (string) config('domain_monitor.coverage_tags.automation_tags.manual_csv_pending.name', 'automation.manual_csv_pending');

        $properties = WebProperty::query()
            ->with([
                'primaryDomain.tags',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain.tags',
            ])
            ->whereHas('primaryDomain.tags', fn ($query) => $query->where('name', $manualCsvTagName))
            ->orderBy('name')
            ->get();

        $baselinesByProperty = DomainSeoBaseline::query()
            ->whereIn('web_property_id', $properties->pluck('id'))
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at')
            ->get()
            ->unique('web_property_id')
            ->keyBy('web_property_id');

        $pendingItems = $properties
            ->map(function (WebProperty $property) use ($baselinesByProperty): ?array {
                $baseline = $baselinesByProperty->get($property->id);
                if (! $baseline instanceof DomainSeoBaseline) {
                    return null;
                }

                $repository = $property->repositoryCoverageSummary();
                $matomo = $property->matomoCoverageSummary();
                $searchConsole = $property->searchConsoleCoverageSummary();
                $matomoSource = $property->primaryAnalyticsSource('matomo');

                if (
                    $repository['status'] !== 'covered'
                    || $matomo['status'] !== 'covered'
                    || $searchConsole['status'] !== 'covered'
                    || $baseline->captured_at->lt(now()->subDays(30))
                    || $baseline->import_method === 'matomo_plus_manual_csv'
                ) {
                    return null;
                }

                return [
                    'property' => $property,
                    'automation' => [
                        'status' => 'manual_csv_pending',
                        'label' => 'Manual CSV pending',
                        'reason' => 'Automation is in place, but no manual Search Console CSV evidence has been uploaded yet',
                    ],
                    'primary_domain' => $property->primaryDomainName(),
                    'repository' => $property->repositories->first(),
                    'matomo_source' => $matomoSource,
                    'search_console_coverage' => $matomoSource?->latestSearchConsoleCoverage,
                    'latest_baseline' => $baseline,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return array_values($pendingItems);
    }

    /**
     * @return array{pending_properties: int, pending_domains: int}
     */
    public function stats(): array
    {
        $pendingItems = collect($this->pendingItems());

        return [
            'pending_properties' => $pendingItems->count(),
            'pending_domains' => $pendingItems
                ->pluck('primary_domain')
                ->filter(fn (?string $domain): bool => is_string($domain) && $domain !== '')
                ->unique()
                ->count(),
        ];
    }
}
