<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use Livewire\Component;

class SearchConsoleCoverageQueue extends Component
{
    public function render(): \Illuminate\View\View
    {
        $properties = WebProperty::query()
            ->with([
                'primaryDomain',
                'analyticsSources',
                'analyticsSources.latestSearchConsoleCoverage',
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
            $matomoSource = $property->primaryAnalyticsSource('matomo');
            $coverage = $matomoSource?->latestSearchConsoleCoverage;

            $row = [
                'property' => $property,
                'primary_domain' => $primaryDomain?->domain,
                'matomo_source' => $matomoSource,
                'coverage' => $coverage,
                'latest_baseline' => $primaryDomain?->latestSeoBaseline,
            ];

            if (! $this->isEligible($primaryDomain)) {
                $excluded[] = $row;

                continue;
            }

            if (! $matomoSource instanceof PropertyAnalyticsSource) {
                $needsSearchConsole[] = $row;

                continue;
            }

            if (! $coverage instanceof SearchConsoleCoverageStatus || $coverage->mapping_state === 'not_mapped') {
                $needsSearchConsole[] = $row;

                continue;
            }

            if ($coverage->freshnessState() === 'stale' || $coverage->freshnessState() === 'never_imported') {
                $staleImports[] = $row;

                continue;
            }

            if ($coverage->mapping_state === 'url_prefix') {
                $urlPrefixOnly[] = $row;

                continue;
            }

            if (! $primaryDomain?->latestSeoBaseline) {
                $needsBaseline[] = $row;

                continue;
            }

            $domainPropertyReady[] = $row;
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
