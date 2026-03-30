<?php

namespace App\Livewire;

use App\Models\DomainSeoBaseline;
use App\Models\WebProperty;
use Illuminate\Support\Collection;
use Livewire\Component;

class ManualCsvBacklogQueue extends Component
{
    public function render(): \Illuminate\View\View
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
                $baseline = $this->latestBaselineForProperty($property);
                if (! $baseline instanceof DomainSeoBaseline) {
                    return null;
                }

                $repository = $property->repositoryCoverageSummary();
                $matomo = $property->matomoCoverageSummary();
                $searchConsole = $property->searchConsoleCoverageSummary();

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
                    'matomo_source' => $property->primaryAnalyticsSource('matomo'),
                    'search_console_coverage' => $property->primaryAnalyticsSource('matomo')?->latestSearchConsoleCoverage,
                    'latest_baseline' => $baseline,
                ];
            })
            ->filter()
            ->values();

        /** @var Collection<int, array{
         * property: WebProperty,
         * automation: array<string, mixed>,
         * primary_domain: string|null,
         * repository: mixed,
         * matomo_source: mixed,
         * search_console_coverage: mixed,
         * latest_baseline: mixed
         * }> $pendingItems
         */

        return view('livewire.manual-csv-backlog-queue', [
            'pendingItems' => $pendingItems,
            'stats' => [
                'pending_properties' => $pendingItems->count(),
                'pending_domains' => $pendingItems
                    ->pluck('primary_domain')
                    ->filter(fn (?string $domain): bool => is_string($domain) && $domain !== '')
                    ->unique()
                    ->count(),
            ],
        ]);
    }

    private function latestBaselineForProperty(WebProperty $property): ?DomainSeoBaseline
    {
        return DomainSeoBaseline::query()
            ->where('web_property_id', $property->id)
            ->latest('captured_at')
            ->latest('created_at')
            ->first();
    }
}
