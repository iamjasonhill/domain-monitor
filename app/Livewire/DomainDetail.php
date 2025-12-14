<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Services\HostingDetector;
use App\Services\PlatformDetector;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class DomainDetail extends Component
{
    public string $domainId;

    public ?Domain $domain = null;

    public bool $syncing = false;

    public string $syncMessage = '';

    public bool $showDeleteModal = false;

    public function mount(): void
    {
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

        // Sync simple platform field with relationship
        if ($this->domain->platform && $this->domain->platform->platform_type && ! $this->domain->platform) {
            $this->domain->update(['platform' => $this->domain->platform->platform_type]);
            $this->domain->refresh();
        }
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
            session()->flash('message', 'Domain information synced successfully from Synergy Wholesale!');
            $this->dispatch('sync-complete');
        } catch (\Exception $e) {
            $this->syncMessage = 'Error syncing: '.$e->getMessage();
            session()->flash('error', 'Error syncing from Synergy Wholesale: '.$e->getMessage());
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
            session()->flash('message', ucfirst($type).' health check completed successfully!');
            $this->dispatch('health-check-complete', type: $type);
        } catch (\Exception $e) {
            session()->flash('error', 'Health check failed: '.$e->getMessage());
            $this->dispatch('health-check-error', message: $e->getMessage());
        }
    }

    public function confirmDelete(): void
    {
        $this->showDeleteModal = true;
    }

    public function deleteDomain(): void
    {
        if (! $this->domain) {
            return;
        }

        $domainName = $this->domain->domain;
        $this->domain->delete();

        session()->flash('message', "Domain '{$domainName}' has been deleted successfully.");
        $this->redirect(route('domains.index'), navigate: true);
    }

    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
    }

    public function detectPlatform(PlatformDetector $detector): void
    {
        if (! $this->domain) {
            return;
        }

        try {
            $result = $detector->detect($this->domain->domain);

            $platform = $this->domain->platform()->updateOrCreate(
                ['domain_id' => $this->domain->id],
                [
                    'platform_type' => $result['platform_type'],
                    'platform_version' => $result['platform_version'],
                    'admin_url' => $result['admin_url'],
                    'detection_confidence' => $result['detection_confidence'],
                    'last_detected' => now(),
                ]
            );

            // Sync the simple platform field
            $this->domain->update(['platform' => $platform->platform_type]);

            $this->loadDomain();
            session()->flash('message', "Platform detected: {$platform->platform_type} ({$platform->detection_confidence} confidence)");
        } catch (\Exception $e) {
            session()->flash('error', 'Platform detection failed: '.$e->getMessage());
        }
    }

    public function detectHosting(HostingDetector $detector): void
    {
        if (! $this->domain) {
            return;
        }

        try {
            $result = $detector->detect($this->domain->domain);

            $this->domain->update([
                'hosting_provider' => $result['provider'],
                'hosting_admin_url' => $result['admin_url'],
            ]);

            $this->loadDomain();
            session()->flash('message', "Hosting detected: {$result['provider']} ({$result['confidence']} confidence)");
        } catch (\Exception $e) {
            session()->flash('error', 'Hosting detection failed: '.$e->getMessage());
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
