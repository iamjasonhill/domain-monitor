<?php

namespace App\Livewire;

use App\Models\Domain;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class DomainDetail extends Component
{
    public string $domainId;

    public ?Domain $domain = null;

    public bool $syncing = false;

    public string $syncMessage = '';

    public function mount(string $domain): void
    {
        $this->domainId = $domain;
        $this->loadDomain();
    }

    /**
     * @return array<string, string>
     */
    protected function getListeners(): array
    {
        return [
            'sync-complete' => 'loadDomain',
            'health-check-complete' => 'loadDomain',
        ];
    }

    public function loadDomain(): void
    {
        $this->domain = Domain::with(['platform', 'checks' => function ($query) {
            $query->latest()->limit(20);
        }])->findOrFail($this->domainId);
    }

    public function syncFromSynergy(): void
    {
        if (! str_ends_with($this->domain->domain, '.com.au')) {
            $this->syncMessage = 'Only .com.au domains can be synced from Synergy Wholesale.';
            $this->dispatch('sync-complete');

            return;
        }

        $this->syncing = true;
        $this->syncMessage = '';

        try {
            Artisan::call('domains:sync-synergy-expiry', [
                '--domain' => $this->domain->domain,
            ]);

            $this->loadDomain();
            $this->syncMessage = 'Domain information synced successfully!';
            $this->dispatch('sync-complete');
        } catch (\Exception $e) {
            $this->syncMessage = 'Error syncing: '.$e->getMessage();
            $this->dispatch('sync-complete');
        } finally {
            $this->syncing = false;
        }
    }

    public function runHealthCheck(string $type): void
    {
        try {
            Artisan::call('domains:health-check', [
                '--domain' => $this->domain->domain,
                '--type' => $type,
            ]);

            $this->loadDomain();
            $this->dispatch('health-check-complete', type: $type);
        } catch (\Exception $e) {
            $this->dispatch('health-check-error', message: $e->getMessage());
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
