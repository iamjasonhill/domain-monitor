<?php

namespace App\Livewire;

use App\Models\WebProperty;
use Livewire\Component;

class AutomationCoverageQueue extends Component
{
    public function render(): \Illuminate\View\View
    {
        $properties = WebProperty::query()
            ->with([
                'primaryDomain',
                'primaryDomain.latestSeoBaseline',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain.tags',
            ])
            ->orderBy('name')
            ->get();

        $queues = [
            'needsController' => [],
            'needsMatomoBinding' => [],
            'needsSearchConsoleMapping' => [],
            'needsOnboarding' => [],
            'importStale' => [],
            'needsBaselineSync' => [],
            'manualCsvPending' => [],
            'complete' => [],
            'excluded' => [],
        ];

        foreach ($properties as $property) {
            $automation = $property->automationCoverageSummary();
            $primaryDomain = $property->primaryDomainModel();
            $latestBaseline = $primaryDomain && $primaryDomain->relationLoaded('latestSeoBaseline')
                ? $primaryDomain->latestSeoBaseline
                : $primaryDomain?->latestSeoBaseline()->first();

            $row = [
                'property' => $property,
                'automation' => $automation,
                'primary_domain' => $property->primaryDomainName(),
                'repository' => $property->repositories->first(),
                'matomo_source' => $property->primaryAnalyticsSource('matomo'),
                'search_console_coverage' => $property->primaryAnalyticsSource('matomo')?->latestSearchConsoleCoverage,
                'latest_baseline' => $latestBaseline,
            ];

            match ($automation['status']) {
                'needs_controller' => $queues['needsController'][] = $row,
                'needs_matomo_binding' => $queues['needsMatomoBinding'][] = $row,
                'needs_search_console_mapping' => $queues['needsSearchConsoleMapping'][] = $row,
                'needs_onboarding' => $queues['needsOnboarding'][] = $row,
                'import_stale' => $queues['importStale'][] = $row,
                'needs_baseline_sync' => $queues['needsBaselineSync'][] = $row,
                'manual_csv_pending' => $queues['manualCsvPending'][] = $row,
                'complete' => $queues['complete'][] = $row,
                default => $queues['excluded'][] = $row,
            };
        }

        return view('livewire.automation-coverage-queue', [
            'needsController' => collect($queues['needsController']),
            'needsMatomoBinding' => collect($queues['needsMatomoBinding']),
            'needsSearchConsoleMapping' => collect($queues['needsSearchConsoleMapping']),
            'needsOnboarding' => collect($queues['needsOnboarding']),
            'importStale' => collect($queues['importStale']),
            'needsBaselineSync' => collect($queues['needsBaselineSync']),
            'manualCsvPending' => collect($queues['manualCsvPending']),
            'complete' => collect($queues['complete']),
            'excluded' => collect($queues['excluded']),
            'stats' => [
                'required' => count($queues['needsController'])
                    + count($queues['needsMatomoBinding'])
                    + count($queues['needsSearchConsoleMapping'])
                    + count($queues['needsOnboarding'])
                    + count($queues['importStale'])
                    + count($queues['needsBaselineSync'])
                    + count($queues['manualCsvPending'])
                    + count($queues['complete']),
                'needs_controller' => count($queues['needsController']),
                'needs_matomo_binding' => count($queues['needsMatomoBinding']),
                'needs_search_console_mapping' => count($queues['needsSearchConsoleMapping']),
                'needs_onboarding' => count($queues['needsOnboarding']),
                'import_stale' => count($queues['importStale']),
                'needs_baseline_sync' => count($queues['needsBaselineSync']),
                'manual_csv_pending' => count($queues['manualCsvPending']),
                'complete' => count($queues['complete']),
                'excluded' => count($queues['excluded']),
            ],
        ]);
    }
}
