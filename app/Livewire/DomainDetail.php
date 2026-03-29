<?php

namespace App\Livewire;

use App\Livewire\Concerns\ManagesDomainDnsRecords;
use App\Livewire\Concerns\ManagesDomainSubdomains;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\DomainDnsAutoFixService;
use App\Services\HostingDetector;
use App\Services\PlatformDetector;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class DomainDetail extends Component
{
    use ManagesDomainDnsRecords;
    use ManagesDomainSubdomains;

    public string $domainId;

    public ?Domain $domain = null;

    public bool $syncing = false;

    public string $syncMessage = '';

    public bool $showDeleteModal = false;

    public bool $showDnsRecordModal = false;

    public ?string $editingDnsRecordId = null;

    public string $dnsRecordHost = '';

    public string $dnsRecordType = 'A';

    public string $dnsRecordValue = '';

    public int $dnsRecordTtl = 300;

    public int $dnsRecordPriority = 0;

    public bool $showSubdomainModal = false;

    public ?string $editingSubdomainId = null;

    public string $subdomainName = '';

    public string $subdomainNotes = '';

    public bool $showDnsRecords = false;

    public bool $showSubdomains = false;

    public bool $showHealthChecks = false;

    public string $dkimSelectorsInput = '';

    #[Computed]
    /** @return \Illuminate\Database\Eloquent\Collection<int, DomainCheck>|\Illuminate\Support\Collection<int, never> */
    public function recentChecks(): mixed
    {
        return $this->domain?->checks()->latest()->limit(20)->get() ?? collect();
    }

    #[Computed]
    /** @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\UptimeIncident>|\Illuminate\Support\Collection<int, never> */
    public function uptimeIncidents(): mixed
    {
        return $this->domain?->uptimeIncidents()->latest('started_at')->limit(10)->get() ?? collect();
    }

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
            'dns-sync-complete' => 'loadDomain',
        ];
    }

    public function loadDomain(): void
    {
        $this->domain = Domain::with([
            'platform',
            'subdomains' => function ($query) {
                $query->where('is_active', true)->orderBy('subdomain');
            },
            'contacts' => function ($query) {
                $query->latest('synced_at')->limit(4); // Get latest contacts (one per type)
            },
            'dnsRecords' => function ($query) {
                $query->orderByRaw('LOWER(host)');
            },
            'uptimeIncidents' => function ($query) {
                $query->orderByDesc('started_at')->limit(10);
            },
            'alerts' => function ($query) {
                $query->whereNull('resolved_at')->orderByDesc('triggered_at');
            },
            'complianceChecks' => function ($query) {
                $query->orderByDesc('checked_at')->limit(10);
            },
            'seoBaselines' => function ($query) {
                $query->with(['webProperty', 'propertyAnalyticsSource'])
                    ->orderByDesc('captured_at')
                    ->limit(10);
            },
        ])->findOrFail($this->domainId);

        // Sync simple platform field with relationship if relationship exists but field is empty
        $platformModel = $this->domain->getRelation('platform');
        $platformString = $this->domain->getAttribute('platform');

        if ($platformModel instanceof \App\Models\WebsitePlatform && $platformModel->platform_type && empty($platformString)) {
            $this->domain->update(['platform' => $platformModel->platform_type]);
            // Manually update attribute to avoid refresh() destroying relations
            $this->domain->setAttribute('platform', $platformModel->platform_type);
        }

        /** @var array<int, string> $dkimSelectors */
        $dkimSelectors = $this->domain->dkim_selectors ?? [];
        $this->dkimSelectorsInput = implode(', ', $dkimSelectors);
    }

    public function syncFromSynergy(): void
    {
        if (! $this->isAustralianTld()) {
            $this->syncMessage = 'Only Australian TLD domains (.com.au, .net.au, etc.) can be synced.';
            $this->dispatch('sync-complete');

            return;
        }

        $this->syncing = true;
        $this->syncMessage = '';

        try {
            \App\Jobs\SyncDomainInfoJob::dispatch($this->domain->id);

            $this->syncMessage = 'Domain sync queued. Job will process in the background via Horizon.';
            session()->flash('message', 'Domain sync queued. Job will process in the background via Horizon.');
            $this->dispatch('sync-complete');
        } catch (\Exception $e) {
            $this->syncMessage = 'Error queueing sync: '.$e->getMessage();
            session()->flash('error', 'Error queueing domain sync: '.$e->getMessage());
            $this->dispatch('sync-complete');
        } finally {
            $this->syncing = false;
        }
    }

    public function runHealthCheck(string $type): void
    {
        if ($type !== 'reputation' && $this->domain?->isParked()) {
            session()->flash('info', 'This domain is marked as parked. Health checks are disabled.');

            return;
        }

        if ($this->domain?->isEmailOnly() && in_array($type, ['http', 'ssl'], true)) {
            session()->flash('info', 'This domain is marked as email-only. HTTP and SSL checks are disabled.');

            return;
        }

        try {
            $exitCode = Artisan::call('domains:health-check', [
                '--domain' => $this->domain->domain,
                '--type' => $type,
            ]);

            if ($exitCode !== 0) {
                $output = Artisan::output();
                Log::error("Health check failed for {$this->domain->domain} ({$type})", ['output' => $output]);
                session()->flash('error', 'Health check command failed. Please check logs.');
                $this->dispatch('health-check-error', message: 'Command failed');

                return;
            }

            $this->loadDomain();
            session()->flash('message', ucfirst($type).' health check completed successfully!');
            $this->dispatch('health-check-complete', type: $type);
        } catch (\Exception $e) {
            session()->flash('error', 'Health check failed: '.$e->getMessage());
            $this->dispatch('health-check-error', message: $e->getMessage());
        }
    }

    public function toggleParkedOverride(): void
    {
        if (! $this->domain) {
            return;
        }

        $enabled = ! $this->domain->parked_override;

        $this->domain->update([
            'parked_override' => $enabled,
            'parked_override_set_at' => $enabled ? now() : null,
        ]);

        $this->loadDomain();

        session()->flash(
            'message',
            $enabled ? 'Domain manually marked as parked. Health checks are now disabled.' : 'Domain unmarked as parked. Health checks are now enabled.'
        );
    }

    public function saveDkimSelectors(): void
    {
        if (! $this->domain) {
            return;
        }

        $selectors = array_filter(array_map('trim', explode(',', $this->dkimSelectorsInput)));
        $selectors = array_unique($selectors);

        $this->domain->update([
            'dkim_selectors' => $selectors,
        ]);

        $this->loadDomain();
        session()->flash('message', 'DKIM selectors updated successfully!');
        $this->runHealthCheck('email_security');
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
            $this->domain->applyHostingDetection($result);

            $this->loadDomain();
            session()->flash('message', "Hosting detected: {$result['provider']} ({$result['confidence']} confidence)");
        } catch (\Exception $e) {
            session()->flash('error', 'Hosting detection failed: '.$e->getMessage());
        }
    }

    private function isAustralianTld(): bool
    {
        return $this->domain instanceof Domain
            && \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain);
    }

    public function applyFix(string $checkType): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        $result = app(DomainDnsAutoFixService::class)->applyFix($this->domain, $checkType);

        if ($result['ok']) {
            session()->flash('message', $result['message']);
            $this->syncDnsRecords();
            $this->runHealthCheck('email_security');

            return;
        }

        session()->flash('error', $result['message']);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
