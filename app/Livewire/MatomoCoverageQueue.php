<?php

namespace App\Livewire;

use App\Models\AnalyticsSourceObservation;
use App\Models\WebProperty;
use Illuminate\Support\Collection;
use Livewire\Component;

class MatomoCoverageQueue extends Component
{
    public function render(): \Illuminate\View\View
    {
        $properties = WebProperty::query()
            ->with([
                'primaryDomain',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'propertyDomains.domain',
            ])
            ->orderBy('name')
            ->get();

        $observations = AnalyticsSourceObservation::query()
            ->where('provider', 'matomo')
            ->latest('checked_at')
            ->get();

        $domainPropertyMap = $this->buildDomainPropertyMap($properties);

        $needsBinding = [];
        $boundAttention = [];
        $covered = [];
        $excluded = [];

        foreach ($properties as $property) {
            $coverage = $property->matomoCoverageSummary();

            $row = [
                'property' => $property,
                'coverage' => $coverage,
                'primary_domain' => $property->primaryDomainName(),
                'matomo_source' => $property->primaryAnalyticsSource('matomo'),
            ];

            match ($coverage['status']) {
                'needs_binding' => $needsBinding[] = $row,
                'bound_unverified', 'bound_attention' => $boundAttention[] = $row,
                'covered' => $covered[] = $row,
                default => $excluded[] = $row,
            };
        }

        $unmappedObservations = $observations
            ->whereNull('matched_web_property_id')
            ->map(function (AnalyticsSourceObservation $observation) use ($domainPropertyMap): array {
                return [
                    'observation' => $observation,
                    'suggested_property' => $this->suggestPropertyForObservation($observation, $domainPropertyMap),
                ];
            })
            ->values();

        return view('livewire.matomo-coverage-queue', [
            'needsBinding' => collect($needsBinding),
            'boundAttention' => collect($boundAttention),
            'covered' => collect($covered),
            'excluded' => collect($excluded),
            'unmappedObservations' => $unmappedObservations,
            'stats' => [
                'eligible' => count($needsBinding) + count($boundAttention) + count($covered),
                'needs_binding' => count($needsBinding),
                'bound_attention' => count($boundAttention),
                'covered' => count($covered),
                'excluded' => count($excluded),
                'unmapped' => $unmappedObservations->count(),
            ],
        ]);
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     * @return array<string, WebProperty>
     */
    private function buildDomainPropertyMap(Collection $properties): array
    {
        $map = [];

        foreach ($properties as $property) {
            foreach ($property->orderedDomainLinks() as $link) {
                $domain = $link->domain?->domain;
                if (! $domain) {
                    continue;
                }

                $map[mb_strtolower($domain)] = $property;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, WebProperty>  $domainPropertyMap
     */
    private function suggestPropertyForObservation(AnalyticsSourceObservation $observation, array $domainPropertyMap): ?WebProperty
    {
        $urls = $observation->raw_payload['urls'] ?? [];

        if (is_array($urls)) {
            foreach ($urls as $url) {
                $host = parse_url((string) $url, PHP_URL_HOST);
                if (! is_string($host)) {
                    continue;
                }

                $host = mb_strtolower($host);
                if (isset($domainPropertyMap[$host])) {
                    return $domainPropertyMap[$host];
                }
            }
        }

        $bestUrlHost = parse_url((string) $observation->best_url, PHP_URL_HOST);
        if (is_string($bestUrlHost)) {
            $bestUrlHost = mb_strtolower($bestUrlHost);
            if (isset($domainPropertyMap[$bestUrlHost])) {
                return $domainPropertyMap[$bestUrlHost];
            }
        }

        return null;
    }
}
