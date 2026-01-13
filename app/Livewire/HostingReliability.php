<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\UptimeIncident;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HostingReliability extends Component
{
    public ?string $selectedHost = null;

    /** @return Collection<int, mixed> */
    #[Computed]
    public function hostStats(): Collection
    {
        return Domain::query()
            ->whereNotNull('hosting_provider')
            ->where('hosting_provider', '!=', '')
            ->get()
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

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.hosting-reliability')
            ->layout('layouts.app');
    }
}
