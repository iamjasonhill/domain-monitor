<?php

namespace App\Livewire;

use App\Models\Domain;
use Livewire\Component;
use Livewire\WithPagination;

class DomainsList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?bool $filterActive = null;

    public bool $filterExpiring = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->filterActive = null;
        $this->filterExpiring = false;
        $this->resetPage();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $domains = Domain::query()
            ->when($this->search, function ($query) {
                $query->where('domain', 'like', '%'.$this->search.'%')
                    ->orWhere('project_key', 'like', '%'.$this->search.'%')
                    ->orWhere('registrar', 'like', '%'.$this->search.'%');
            })
            ->when($this->filterActive !== null, function ($query) {
                $query->where('is_active', $this->filterActive);
            })
            ->when($this->filterExpiring, function ($query) {
                $query->where('is_active', true)
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now()->addDays(30))
                    ->where('expires_at', '>', now());
            })
            ->orderBy('updated_at', 'desc')
            ->paginate(20);

        return view('livewire.domains-list', [
            'domains' => $domains,
        ]);
    }
}
