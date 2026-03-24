<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\UptimeIncident;
use App\Services\HostingDetector;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HostingReliability extends Component
{
    public ?string $selectedHost = null;

    public ?string $reviewFilter = 'all';

    /** @return Collection<int, mixed> */
    #[Computed]
    public function hostStats(): Collection
    {
        return Domain::query()
            ->whereNotNull('hosting_provider')
            ->where('hosting_provider', '!=', '')
            ->with('platform')
            ->get()
            ->reject(fn (Domain $domain) => $domain->isParkedForHosting())
            ->groupBy('hosting_provider')
            ->map(function ($domains, $host) {
                $domainIds = $domains->pluck('id');

                $incidents = UptimeIncident::whereIn('domain_id', $domainIds)->get();

                $totalDowntimeMinutes = $incidents->sum(function ($incident) {
                    if (! $incident->ended_at) {
                        return (int) $incident->started_at->diffInMinutes(now());
                    }

                    return (int) $incident->started_at->diffInMinutes($incident->ended_at);
                });

                return [
                    'host' => (string) $host,
                    'domain_count' => $domains->count(),
                    'incident_count' => $incidents->count(),
                    'ongoing_count' => $incidents->whereNull('ended_at')->count(),
                    'total_downtime_minutes' => $totalDowntimeMinutes,
                    'last_incident' => $incidents->max('started_at'),
                ];
            })
            ->sortByDesc('total_downtime_minutes')
            ->values();
    }

    /** @return Collection<int, mixed> */
    #[Computed]
    public function parkingStats(): Collection
    {
        return Domain::query()
            ->whereNotNull('hosting_provider')
            ->where('hosting_provider', '!=', '')
            ->with('platform')
            ->get()
            ->filter(fn (Domain $domain) => $domain->isParkedForHosting())
            ->groupBy('hosting_provider')
            ->map(function ($domains, $provider) {
                return [
                    'provider' => (string) $provider,
                    'domain_count' => $domains->count(),
                    'domains' => $domains->pluck('domain')->sort()->values(),
                ];
            })
            ->sortByDesc('domain_count')
            ->values();
    }

    /** @return Collection<int, mixed> */
    #[Computed]
    public function reviewQueue(): Collection
    {
        $query = Domain::query()
            ->where('is_active', true)
            ->with(['webProperties', 'platform'])
            ->where(function ($builder) {
                $builder->whereNull('hosting_provider')
                    ->orWhere('hosting_provider', '')
                    ->orWhereNull('hosting_review_status')
                    ->orWhere('hosting_review_status', 'pending');
            });

        if ($this->reviewFilter === 'missing') {
            $query->where(function ($builder) {
                $builder->whereNull('hosting_provider')
                    ->orWhere('hosting_provider', '');
            });
        }

        if ($this->reviewFilter === 'pending') {
            $query->whereNotNull('hosting_provider')
                ->where('hosting_provider', '!=', '')
                ->where(function ($builder) {
                    $builder->whereNull('hosting_review_status')
                        ->orWhere('hosting_review_status', 'pending');
                });
        }

        return $query
            ->orderByRaw("CASE WHEN hosting_provider IS NULL OR hosting_provider = '' THEN 0 ELSE 1 END")
            ->orderBy('domain')
            ->get()
            ->map(function (Domain $domain): array {
                $linkedProperties = $domain->webProperties
                    ->map(fn ($property) => [
                        'slug' => $property->slug,
                        'name' => $property->name,
                    ])
                    ->values()
                    ->all();

                return [
                    'id' => $domain->id,
                    'domain' => $domain->domain,
                    'hosting_provider' => $domain->hosting_provider,
                    'hosting_admin_url' => $domain->hosting_admin_url,
                    'hosting_detection_confidence' => $domain->hosting_detection_confidence,
                    'hosting_detection_source' => $domain->hosting_detection_source,
                    'hosting_detected_at' => $domain->hosting_detected_at,
                    'hosting_review_status' => $domain->hosting_review_status,
                    'hosting_reviewed_at' => $domain->hosting_reviewed_at,
                    'hosting_usage_type' => $domain->hostingUsageType(),
                    'is_parked_for_hosting' => $domain->isParkedForHosting(),
                    'dns_config_name' => $domain->dns_config_name,
                    'linked_properties' => $linkedProperties,
                ];
            });
    }

    /** @return array<string, int> */
    #[Computed]
    public function reviewStats(): array
    {
        $baseQuery = Domain::query()->where('is_active', true);

        return [
            'missing' => (clone $baseQuery)->where(function ($builder) {
                $builder->whereNull('hosting_provider')
                    ->orWhere('hosting_provider', '');
            })->count(),
            'pending' => (clone $baseQuery)->whereNotNull('hosting_provider')
                ->where('hosting_provider', '!=', '')
                ->where(function ($builder) {
                    $builder->whereNull('hosting_review_status')
                        ->orWhere('hosting_review_status', 'pending');
                })->count(),
            'reviewed' => (clone $baseQuery)->whereIn('hosting_review_status', ['confirmed', 'manual'])->count(),
            'parked' => (clone $baseQuery)->where(function ($builder) {
                $builder->where('dns_config_name', 'Parked')
                    ->orWhere('platform', 'Parked')
                    ->orWhere('parked_override', true);
            })->count(),
        ];
    }

    /** @return Collection<int, mixed>|null */
    #[Computed]
    public function selectedHostDetails(): ?Collection
    {
        if (! $this->selectedHost) {
            return null;
        }

        return Domain::where('hosting_provider', $this->selectedHost)
            ->with(['uptimeIncidents' => function ($query) {
                $query->latest('started_at')->limit(20);
            }])
            ->get()
            ->map(function ($domain) {
                $totalDowntime = $domain->uptimeIncidents->sum(function ($incident) {
                    if (! $incident->ended_at) {
                        return (int) $incident->started_at->diffInMinutes(now());
                    }

                    return (int) $incident->started_at->diffInMinutes($incident->ended_at);
                });

                return [
                    'domain' => $domain->domain,
                    'domain_id' => $domain->id,
                    'hosting_review_status' => $domain->hosting_review_status,
                    'hosting_detection_confidence' => $domain->hosting_detection_confidence,
                    'incident_count' => $domain->uptimeIncidents->count(),
                    'total_downtime' => $totalDowntime,
                    'incidents' => $domain->uptimeIncidents,
                ];
            })
            ->sortByDesc('total_downtime');
    }

    public function selectHost(?string $host): void
    {
        $this->selectedHost = $host;
    }

    public function setReviewFilter(?string $filter): void
    {
        $this->reviewFilter = in_array($filter, ['all', 'missing', 'pending'], true) ? $filter : 'all';
    }

    public function detectHostingForDomain(string $domainId, HostingDetector $detector): void
    {
        $domain = Domain::findOrFail($domainId);
        $result = $detector->detect($domain->domain);

        $domain->applyHostingDetection($result);

        session()->flash('message', "Hosting detected for {$domain->domain}: {$result['provider']} ({$result['confidence']} confidence)");
    }

    public function confirmHostingForDomain(string $domainId): void
    {
        $domain = Domain::findOrFail($domainId);

        if (blank($domain->hosting_provider)) {
            session()->flash('error', "No hosting provider set for {$domain->domain} yet.");

            return;
        }

        $domain->markHostingReviewed('confirmed');

        session()->flash('message', "Hosting confirmed for {$domain->domain}.");
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.hosting-reliability')
            ->layout('layouts.app');
    }
}
