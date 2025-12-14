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
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active Synergy Wholesale credentials found. Please configure credentials first.', type: 'error');
                $this->syncingExpiry = false;

                return;
            }

            $exitCode = Artisan::call('domains:sync-synergy-expiry', ['--all' => true]);
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domain information synced successfully from Synergy Wholesale!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'Sync failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
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
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active Synergy Wholesale credentials found. Please configure credentials first.', type: 'error');
                $this->syncingDns = false;

                return;
            }

            $exitCode = Artisan::call('domains:sync-dns-records', ['--all' => true]);
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'DNS records synced successfully from Synergy Wholesale!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'DNS sync failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
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
            // Check if credentials exist
            $credential = \App\Models\SynergyCredential::where('is_active', true)->first();
            if (! $credential) {
                $this->dispatch('flash-message', message: 'No active Synergy Wholesale credentials found. Please configure credentials first.', type: 'error');
                $this->importingDomains = false;

                return;
            }

            $exitCode = Artisan::call('domains:import-synergy');
            $output = Artisan::output();

            // Extract error message from output if command failed
            $outputLines = explode("\n", trim($output));
            $errorLine = null;
            foreach ($outputLines as $line) {
                if (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, 'exception') !== false) {
                    $errorLine = trim($line);
                    break;
                }
            }

            if ($exitCode === 0) {
                $this->dispatch('flash-message', message: 'Domains imported successfully from Synergy Wholesale!', type: 'success');
            } else {
                $errorMessage = $errorLine ?: (trim($output) ?: 'Import failed. Check logs for details.');
                // Truncate long error messages
                if (strlen($errorMessage) > 200) {
                    $errorMessage = substr($errorMessage, 0, 197).'...';
                }
                $this->dispatch('flash-message', message: $errorMessage, type: 'error');
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
