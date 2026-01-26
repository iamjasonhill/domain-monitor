<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainAlert;
use Livewire\Component;
use Livewire\WithPagination;

class AlertsList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterDomain = null;

    public ?string $filterType = null;

    public ?string $filterSeverity = null;

    public bool $filterUnresolved = true;

    public function mount(): void
    {
        $this->filterUnresolved = request()->boolean('unresolved', true);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUnresolved(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterDomain = null;
        $this->filterType = null;
        $this->filterSeverity = null;
        $this->filterUnresolved = true;
        $this->resetPage();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $alerts = DomainAlert::with('domain')
            ->when($this->search, function ($query) {
                $query->whereHas('domain', function ($q) {
                    $q->where('domain', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->filterDomain, function ($query) {
                $query->where('domain_id', $this->filterDomain);
            })
            ->when($this->filterType, function ($query) {
                $query->where('alert_type', $this->filterType);
            })
            ->when($this->filterSeverity, function ($query) {
                $query->where('severity', $this->filterSeverity);
            })
            ->when($this->filterUnresolved, function ($query) {
                $query->whereNull('resolved_at');
            })
            ->orderByDesc('triggered_at')
            ->paginate(20);

        $domains = Domain::where('is_active', true)
            ->orderBy('domain')
            ->get();

        return view('livewire.alerts-list', [
            'alerts' => $alerts,
            'domains' => $domains,
        ]);
    }
}
