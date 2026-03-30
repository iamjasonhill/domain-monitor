<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Collection;

class ManualCsvBacklogService
{
    /**
     * @return array{items: Collection<int, mixed>, stats: array{pending_properties: int, pending_domains: int}}
     */
    public function snapshot(): array
    {
        $manualCsvTagName = (string) config('domain_monitor.coverage_tags.automation_tags.manual_csv_pending.name', 'automation.manual_csv_pending');

        $properties = WebProperty::query()
            ->with([
                'primaryDomain.tags',
                'primaryDomain.latestSeoBaseline',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain.tags',
            ])
            ->whereHas('primaryDomain.tags', fn ($query) => $query->where('name', $manualCsvTagName))
            ->orderBy('name')
            ->get();

        $pendingItems = $properties
            ->map(function (WebProperty $property): ?array {
                $automation = $property->automationCoverageSummary();

                if ($automation['status'] !== 'manual_csv_pending') {
                    return null;
                }

                $matomoSource = $property->primaryAnalyticsSource('matomo');

                return [
                    'property' => $property,
                    'automation' => $automation,
                    'primary_domain' => $property->primaryDomainName(),
                    'repository' => $property->repositories->first(),
                    'matomo_source' => $matomoSource,
                    'search_console_coverage' => $matomoSource?->latestSearchConsoleCoverage,
                    'latest_baseline' => $property->primaryDomainModel()?->latestSeoBaseline,
                ];
            })
            ->filter()
            ->values();

        return [
            'items' => $pendingItems,
            'stats' => [
                'pending_properties' => $pendingItems->count(),
                'pending_domains' => $pendingItems
                    ->pluck('primary_domain')
                    ->filter(fn (?string $domain): bool => is_string($domain) && $domain !== '')
                    ->unique()
                    ->count(),
            ],
        ];
    }
}
