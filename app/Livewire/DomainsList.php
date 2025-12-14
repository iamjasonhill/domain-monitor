<?php

namespace App\Livewire;

use App\Models\Domain;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\WithPagination;

class DomainsList extends Component
{
    use WithPagination;

    public string $search = '';

    public ?bool $filterActive = null;

    public bool $filterExpiring = false;

    public bool $syncingExpiry = false;

    public bool $syncingDns = false;

    public bool $importingDomains = false;

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

    public function syncSynergyExpiry(): void
    {
        $this->syncingExpiry = true;

        try {
            Artisan::call('domains:sync-synergy-expiry', ['--all' => true]);
            session()->flash('message', 'Domain information synced successfully from Synergy Wholesale!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error syncing domain information: '.$e->getMessage());
        } finally {
            $this->syncingExpiry = false;
            $this->resetPage();
        }
    }

    public function syncDnsRecords(): void
    {
        $this->syncingDns = true;

        try {
            Artisan::call('domains:sync-dns-records', ['--all' => true]);
            session()->flash('message', 'DNS records synced successfully from Synergy Wholesale!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error syncing DNS records: '.$e->getMessage());
        } finally {
            $this->syncingDns = false;
            $this->resetPage();
        }
    }

    public function importSynergyDomains(): void
    {
        $this->importingDomains = true;

        try {
            Artisan::call('domains:import-synergy');
            session()->flash('message', 'Domains imported successfully from Synergy Wholesale!');
        } catch (\Exception $e) {
            session()->flash('error', 'Error importing domains: '.$e->getMessage());
        } finally {
            $this->importingDomains = false;
            $this->resetPage();
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $domains = Domain::with(['checks' => function ($query) {
            $query->latest()->limit(1);
        }])
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
