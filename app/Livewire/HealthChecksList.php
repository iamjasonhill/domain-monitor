<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\DomainMonitorSettings;
use Livewire\Component;
use Livewire\WithPagination;

class HealthChecksList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterDomain = null;

    public ?string $filterType = null;

    public ?string $filterStatus = null;

    public bool $filterRecentFailures = false;

    public int $recentFailuresHours = 24;

    public function mount(): void
    {
        $this->recentFailuresHours = app(DomainMonitorSettings::class)->recentFailuresHours();
        $this->filterRecentFailures = request()->boolean('recentFailures');

        if ($this->filterRecentFailures && empty($this->filterStatus)) {
            $this->filterStatus = 'fail';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRecentFailures(): void
    {
        if ($this->filterRecentFailures) {
            $this->filterStatus = 'fail';
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterDomain = null;
        $this->filterType = null;
        $this->filterStatus = null;
        $this->filterRecentFailures = false;
        $this->resetPage();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $checks = DomainCheck::with('domain')
            ->whereHas('domain', function ($query) {
                $query->where('is_active', true);
            })
            ->when($this->search, function ($query) {
                $query->whereHas('domain', function ($q) {
                    $q->where('domain', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterDomain, function ($query) {
                $query->where('domain_id', $this->filterDomain);
            })
            ->when($this->filterType, function ($query) {
                $query->where('check_type', $this->filterType);
            })
            ->when($this->filterStatus, function ($query) {
                $query->where('status', $this->filterStatus);
            })
            ->when($this->filterRecentFailures, function ($query) {
                $query->where('created_at', '>=', now()->subHours($this->recentFailuresHours));
                $query->whereHas('domain', function (\Illuminate\Database\Eloquent\Builder $domainQuery) {
                    $domainQuery->where(function ($parkedQuery) {
                        $parkedQuery->where('parked_override', false)
                            ->orWhereNull('parked_override');
                    })
                        ->where(function ($dnsConfigQuery) {
                            $dnsConfigQuery->where('dns_config_name', '!=', 'Parked')
                                ->orWhereNull('dns_config_name');
                        })
                        ->where(function ($q) {
                            $q->where('platform', '!=', 'Parked')
                                ->orWhereNull('platform');
                        })
                        ->whereDoesntHave('platform', function ($platformQ) {
                            $platformQ->where('platform_type', 'Parked');
                        });
                });
                $query->where(function ($checkQuery) {
                    $checkQuery->whereNotIn('check_type', ['http', 'ssl', 'security_headers', 'seo', 'uptime', 'broken_links'])
                        ->orWhereHas('domain', function (\Illuminate\Database\Eloquent\Builder $domainQuery) {
                            $domainQuery->where(function ($q) {
                                $q->where('platform', '!=', 'Email Only')
                                    ->orWhereNull('platform');
                            })
                                ->whereDoesntHave('platform', function ($platformQ) {
                                    $platformQ->where('platform_type', 'Email Only');
                                });
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $domains = Domain::where('is_active', true)
            ->when($this->filterRecentFailures, function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->where(function ($parkedQuery) {
                    $parkedQuery->where('parked_override', false)
                        ->orWhereNull('parked_override');
                })
                    ->where(function ($dnsConfigQuery) {
                        $dnsConfigQuery->where('dns_config_name', '!=', 'Parked')
                            ->orWhereNull('dns_config_name');
                    })
                    ->where(function ($platformQuery) {
                        $platformQuery->where('platform', '!=', 'Parked')
                            ->orWhereNull('platform');
                    })
                    ->whereDoesntHave('platform', function ($platformQ) {
                        $platformQ->where('platform_type', 'Parked');
                    });
            })
            ->orderBy('domain')
            ->get();

        return view('livewire.health-checks-list', [
            'checks' => $checks,
            'domains' => $domains,
        ]);
    }
}
