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
            $exitCode = Artisan::call('domains:sync-synergy-expiry', ['--all' => true]);
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domain information synced successfully from Synergy Wholesale!', type: 'success');
            } else {
                $this->dispatch('flash-message', message: 'Sync completed with warnings. Check logs for details.', type: 'warning');
            }
        } catch (\Exception $e) {
            \Log::error('Sync Synergy Expiry Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error syncing domain information: '.$e->getMessage(), type: 'error');
        } finally {
            $this->syncingExpiry = false;
            $this->resetPage();
        }
    }

    public function syncDnsRecords(): void
    {
        $this->syncingDns = true;

        try {
            $exitCode = Artisan::call('domains:sync-dns-records', ['--all' => true]);
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'DNS records synced successfully from Synergy Wholesale!', type: 'success');
            } else {
                $this->dispatch('flash-message', message: 'DNS sync completed with warnings. Check logs for details.', type: 'warning');
            }
        } catch (\Exception $e) {
            \Log::error('Sync DNS Records Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error syncing DNS records: '.$e->getMessage(), type: 'error');
        } finally {
            $this->syncingDns = false;
            $this->resetPage();
        }
    }

    public function importSynergyDomains(): void
    {
        $this->importingDomains = true;

        try {
            $exitCode = Artisan::call('domains:import-synergy');
            $output = Artisan::output();

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domains imported successfully from Synergy Wholesale!', type: 'success');
            } else {
                $this->dispatch('flash-message', message: 'Import completed with warnings. Check logs for details.', type: 'warning');
            }
        } catch (\Exception $e) {
            \Log::error('Import Synergy Domains Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->dispatch('flash-message', message: 'Error importing domains: '.$e->getMessage(), type: 'error');
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
