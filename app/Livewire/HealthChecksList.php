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

    public function updatingSearch()
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

    public function clearFilters()
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
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $domains = Domain::where('is_active', true)
            ->orderBy('domain')
            ->get();

        return view('livewire.health-checks-list', [
            'checks' => $checks,
            'domains' => $domains,
        ]);
    }
}
