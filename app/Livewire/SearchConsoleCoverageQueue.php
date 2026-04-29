<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\WebProperty;
use Livewire\Component;

class SearchConsoleCoverageQueue extends Component
{
    public function render(): \Illuminate\View\View
    {
        $properties = WebProperty::query()
            ->with([
                'primaryDomain',
                'primaryDomain.latestSeoBaseline',
                'primaryDomain.latestSearchConsoleCoverageStatus',
                'analyticsSources',
                'analyticsSources.latestSearchConsoleCoverage',
                'monitoringFindings',
                'propertyDomains.domain',
            ])
            ->orderBy('name')
            ->get();

        $needsSearchConsole = [];
        $urlPrefixOnly = [];
        $domainPropertyReady = [];
        $needsBaseline = [];
        $staleImports = [];
        $excluded = [];

        foreach ($properties as $property) {
            $primaryDomain = $property->primaryDomainModel();
            $ga4Source = $property->primaryAnalyticsSource('ga4');
            $coverage = $primaryDomain->latestSearchConsoleCoverageStatus
                ?? $ga4Source?->latestSearchConsoleCoverage;
            $summary = $property->searchConsoleCoverageSummary();

            $row = [
                'property' => $property,
                'primary_domain' => $primaryDomain?->domain,
                'ga4_source' => $ga4Source,
                'ga4_lookup' => $property->analyticsSummary()['ga4'],
                'summary' => $summary,
                'coverage' => $coverage,
                'latest_baseline' => $primaryDomain?->latestSeoBaseline,
            ];

            if (! $this->isEligible($primaryDomain)) {
                $excluded[] = $row;

                continue;
            }

            match ($summary['status']) {
                'needs_ga4', 'needs_property' => $needsSearchConsole[] = $row,
                'url_prefix_only' => $urlPrefixOnly[] = $row,
                'needs_import', 'stale_import', 'blocked' => $staleImports[] = $row,
                'covered' => $primaryDomain?->latestSeoBaseline
                    ? $domainPropertyReady[] = $row
                    : $needsBaseline[] = $row,
                default => $excluded[] = $row,
            };
        }

        return view('livewire.search-console-coverage-queue', [
            'needsSearchConsole' => collect($needsSearchConsole),
            'urlPrefixOnly' => collect($urlPrefixOnly),
            'domainPropertyReady' => collect($domainPropertyReady),
            'needsBaseline' => collect($needsBaseline),
            'staleImports' => collect($staleImports),
            'excluded' => collect($excluded),
            'stats' => [
                'eligible' => count($needsSearchConsole) + count($urlPrefixOnly) + count($domainPropertyReady) + count($needsBaseline) + count($staleImports),
                'needs_search_console' => count($needsSearchConsole),
                'url_prefix_only' => count($urlPrefixOnly),
                'needs_baseline' => count($needsBaseline),
                'stale_imports' => count($staleImports),
                'domain_property_ready' => count($domainPropertyReady),
                'excluded' => count($excluded),
            ],
        ]);
    }

    private function isEligible(?Domain $domain): bool
    {
        if (! $domain) {
            return false;
        }

        return ! $domain->isParkedForHosting() && ! $domain->isEmailOnly();
    }
}
