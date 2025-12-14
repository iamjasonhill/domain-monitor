<?php

namespace App\Livewire;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\HostingDetector;
use App\Services\PlatformDetector;
use App\Services\SynergyWholesaleClient;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;

class DomainDetail extends Component
{
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
            'checks' => function ($query) {
                $query->latest()->limit(20);
            },
            'dnsRecords' => function ($query) {
                $query->orderBy('type')->orderBy('host');
            },
        ])->findOrFail($this->domainId);

        // Sync simple platform field with relationship if relationship exists but field is empty
        $platformModel = $this->domain->getRelation('platform');
        $platformString = $this->domain->getAttribute('platform');

        if ($platformModel instanceof \App\Models\WebsitePlatform && $platformModel->platform_type && empty($platformString)) {
            $this->domain->update(['platform' => $platformModel->platform_type]);
            $this->domain->refresh();
        }
    }

    public function syncFromSynergy(): void
    {
        if (! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            $this->syncMessage = 'Only Australian TLD domains (.com.au, .net.au, etc.) can be synced from Synergy Wholesale.';
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

    public function syncDnsRecords(): void
    {
        if (! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can sync DNS records from Synergy Wholesale.');
            $this->dispatch('dns-sync-complete');

            return;
        }

        try {
            Artisan::call('domains:sync-dns-records', [
                '--domain' => $this->domain->domain,
            ]);

            $this->loadDomain();
            session()->flash('message', 'DNS records synced successfully!');
            $this->dispatch('dns-sync-complete');
        } catch (\Exception $e) {
            session()->flash('error', 'Error syncing DNS records: '.$e->getMessage());
            $this->dispatch('dns-sync-complete');
        }
    }

    public function openAddDnsRecordModal(): void
    {
        $this->editingDnsRecordId = null;
        $this->dnsRecordHost = '';
        $this->dnsRecordType = 'A';
        $this->dnsRecordValue = '';
        $this->dnsRecordTtl = 300;
        $this->dnsRecordPriority = 0;
        $this->showDnsRecordModal = true;
    }

    public function openEditDnsRecordModal(string $recordId): void
    {
        if (! $this->domain) {
            return;
        }

        $record = DnsRecord::where('id', $recordId)
            ->where('domain_id', $this->domain->id)
            ->first();

        if (! $record) {
            session()->flash('error', 'DNS record not found.');

            return;
        }

        $this->editingDnsRecordId = $record->id;
        $this->dnsRecordHost = $record->host;
        $this->dnsRecordType = $record->type;
        $this->dnsRecordValue = $record->value;
        $this->dnsRecordTtl = $record->ttl ?? 300;
        $this->dnsRecordPriority = $record->priority ?? 0;
        $this->showDnsRecordModal = true;
    }

    public function closeDnsRecordModal(): void
    {
        $this->showDnsRecordModal = false;
        $this->editingDnsRecordId = null;
        $this->dnsRecordHost = '';
        $this->dnsRecordType = 'A';
        $this->dnsRecordValue = '';
        $this->dnsRecordTtl = 300;
        $this->dnsRecordPriority = 0;
    }

    public function saveDnsRecord(): void
    {
        if (! $this->domain || ! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records via Synergy Wholesale.');

            return;
        }

        // Validation
        if (empty($this->dnsRecordHost) || empty($this->dnsRecordValue)) {
            session()->flash('error', 'Host and Value are required.');

            return;
        }

        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            session()->flash('error', 'No active Synergy Wholesale credentials found.');

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        try {
            if ($this->editingDnsRecordId) {
                // Update existing record
                $record = DnsRecord::where('id', $this->editingDnsRecordId)
                    ->where('domain_id', $this->domain->id)
                    ->first();

                if (! $record || ! $record->record_id) {
                    session()->flash('error', 'DNS record not found or cannot be updated.');

                    return;
                }

                $result = $client->updateDnsRecord(
                    $this->domain->domain,
                    $record->record_id,
                    $this->dnsRecordHost,
                    $this->dnsRecordType,
                    $this->dnsRecordValue,
                    $this->dnsRecordTtl,
                    $this->dnsRecordPriority
                );

                if ($result && $result['status'] === 'OK') {
                    // Update local record
                    $record->update([
                        'host' => $this->dnsRecordHost,
                        'type' => strtoupper($this->dnsRecordType),
                        'value' => $this->dnsRecordValue,
                        'ttl' => $this->dnsRecordTtl,
                        'priority' => $this->dnsRecordPriority,
                    ]);

                    session()->flash('message', 'DNS record updated successfully!');
                    $this->closeDnsRecordModal();
                    $this->loadDomain();
                } else {
                    session()->flash('error', $result['error_message'] ?? 'Failed to update DNS record.');
                }
            } else {
                // Add new record
                $result = $client->addDnsRecord(
                    $this->domain->domain,
                    $this->dnsRecordHost,
                    $this->dnsRecordType,
                    $this->dnsRecordValue,
                    $this->dnsRecordTtl,
                    $this->dnsRecordPriority
                );

                if ($result && $result['status'] === 'OK' && $result['record_id']) {
                    // Create local record
                    DnsRecord::create([
                        'domain_id' => $this->domain->id,
                        'host' => $this->dnsRecordHost,
                        'type' => strtoupper($this->dnsRecordType),
                        'value' => $this->dnsRecordValue,
                        'ttl' => $this->dnsRecordTtl,
                        'priority' => $this->dnsRecordPriority,
                        'record_id' => $result['record_id'],
                        'synced_at' => now(),
                    ]);

                    session()->flash('message', 'DNS record added successfully!');
                    $this->closeDnsRecordModal();
                    $this->loadDomain();
                } else {
                    session()->flash('error', $result['error_message'] ?? 'Failed to add DNS record.');
                }
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error saving DNS record: '.$e->getMessage());
        }
    }

    public function deleteDnsRecord(string $recordId): void
    {
        if (! $this->domain || ! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records via Synergy Wholesale.');

            return;
        }

        $record = DnsRecord::where('id', $recordId)
            ->where('domain_id', $this->domain->id)
            ->first();

        if (! $record || ! $record->record_id) {
            session()->flash('error', 'DNS record not found or cannot be deleted.');

            return;
        }

        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            session()->flash('error', 'No active Synergy Wholesale credentials found.');

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        try {
            $result = $client->deleteDnsRecord($this->domain->domain, $record->record_id);

            if ($result && $result['status'] === 'OK') {
                $record->delete();
                session()->flash('message', 'DNS record deleted successfully!');
                $this->loadDomain();
            } else {
                session()->flash('error', $result['error_message'] ?? 'Failed to delete DNS record.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting DNS record: '.$e->getMessage());
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
