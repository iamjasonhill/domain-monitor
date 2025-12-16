<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainEligibilityCheck;
use App\Services\DomainMonitorSettings;
use Livewire\Component;
use Livewire\WithPagination;

class EligibilityChecksList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterDomain = null;

    public ?string $filterValid = null;

    public bool $filterRecentFailures = false;

    public int $recentFailuresHours = 24;

    public function mount(): void
    {
        $this->recentFailuresHours = app(DomainMonitorSettings::class)->recentFailuresHours();
        $this->filterRecentFailures = true;

        if (request()->boolean('failed')) {
            $this->filterValid = '0';
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterDomain(): void
    {
        $this->resetPage();
    }

    public function updatingFilterValid(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRecentFailures(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterDomain = null;
        $this->filterValid = null;
        $this->filterRecentFailures = false;
        $this->resetPage();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $checks = DomainEligibilityCheck::with('domain')
            ->when($this->search, function ($query) {
                $query->whereHas('domain', function ($q) {
                    $q->where('domain', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterDomain, function ($query) {
                $query->where('domain_id', $this->filterDomain);
            })
            ->when($this->filterValid !== null && $this->filterValid !== '', function ($query) {
                $query->where('is_valid', $this->filterValid === '1');
            })
            ->when($this->filterRecentFailures, function ($query) {
                $query->where('checked_at', '>=', now()->subHours($this->recentFailuresHours));
            })
            ->orderBy('checked_at', 'desc')
            ->paginate(20);

        $domains = Domain::where('is_active', true)
            ->orderBy('domain')
            ->get();

        return view('livewire.eligibility-checks-list', [
            'checks' => $checks,
            'domains' => $domains,
        ]);
    }
}
