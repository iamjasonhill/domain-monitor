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
                'primaryDomain.tags',
                'primaryDomain.latestSeoBaseline',
                'primaryDomain.latestSearchConsoleCoverageStatus',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'monitoringFindings',
                'propertyDomains.domain.tags',
            ])
            ->orderBy('name')
            ->get();

        $queues = [
            'needsController' => [],
            'needsGa4Sync' => [],
            'ga4Provisioning' => [],
            'ga4Attention' => [],
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
                'ga4_source' => $property->primaryAnalyticsSource('ga4'),
                'ga4_lookup' => $property->analyticsSummary()['ga4'],
                'search_console_coverage' => $primaryDomain->latestSearchConsoleCoverageStatus
                    ?? $property->primaryAnalyticsSource('ga4')?->latestSearchConsoleCoverage,
                'latest_baseline' => $latestBaseline,
            ];

            match ($automation['status']) {
                'needs_controller' => $queues['needsController'][] = $row,
                'needs_ga4_sync' => $queues['needsGa4Sync'][] = $row,
                'ga4_provisioning' => $queues['ga4Provisioning'][] = $row,
                'ga4_attention' => $queues['ga4Attention'][] = $row,
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
            'needsGa4Sync' => collect($queues['needsGa4Sync']),
            'ga4Provisioning' => collect($queues['ga4Provisioning']),
            'ga4Attention' => collect($queues['ga4Attention']),
            'needsSearchConsoleMapping' => collect($queues['needsSearchConsoleMapping']),
            'needsOnboarding' => collect($queues['needsOnboarding']),
            'importStale' => collect($queues['importStale']),
            'needsBaselineSync' => collect($queues['needsBaselineSync']),
            'manualCsvPending' => collect($queues['manualCsvPending']),
            'complete' => collect($queues['complete']),
            'excluded' => collect($queues['excluded']),
            'stats' => [
                'required' => count($queues['needsController'])
                    + count($queues['needsGa4Sync'])
                    + count($queues['ga4Provisioning'])
                    + count($queues['ga4Attention'])
                    + count($queues['needsSearchConsoleMapping'])
                    + count($queues['needsOnboarding'])
                    + count($queues['importStale'])
                    + count($queues['needsBaselineSync'])
                    + count($queues['manualCsvPending'])
                    + count($queues['complete']),
                'needs_controller' => count($queues['needsController']),
                'needs_ga4_sync' => count($queues['needsGa4Sync']),
                'ga4_provisioning' => count($queues['ga4Provisioning']),
                'ga4_attention' => count($queues['ga4Attention']),
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
