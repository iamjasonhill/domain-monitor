<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use Livewire\Component;
use Livewire\WithPagination;

class HealthChecksList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?string $filterDomain = null;

    public ?string $filterType = null;

    public ?string $filterStatus = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->filterDomain = null;
        $this->filterType = null;
        $this->filterStatus = null;
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
