<?php

namespace App\Livewire;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\Subdomain;
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

    public bool $showSubdomainModal = false;

    public ?string $editingSubdomainId = null;

    public string $subdomainName = '';

    public string $subdomainNotes = '';

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

    public function openAddSubdomainModal(): void
    {
        $this->editingSubdomainId = null;
        $this->subdomainName = '';
        $this->subdomainNotes = '';
        $this->showSubdomainModal = true;
    }

    public function openEditSubdomainModal(string $subdomainId): void
    {
        $subdomain = Subdomain::find($subdomainId);
        if (! $subdomain || $subdomain->domain_id !== $this->domain->id) {
            session()->flash('error', 'Subdomain not found.');

            return;
        }

        $this->editingSubdomainId = $subdomainId;
        $this->subdomainName = $subdomain->subdomain;
        $this->subdomainNotes = $subdomain->notes ?? '';
        $this->showSubdomainModal = true;
    }

    public function closeSubdomainModal(): void
    {
        $this->showSubdomainModal = false;
        $this->editingSubdomainId = null;
        $this->subdomainName = '';
        $this->subdomainNotes = '';
    }

    public function saveSubdomain(): void
    {
        if (empty($this->subdomainName)) {
            session()->flash('error', 'Subdomain name is required.');

            return;
        }

        // Validate subdomain format (alphanumeric, hyphens, underscores)
        if (! preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $this->subdomainName)) {
            session()->flash('error', 'Invalid subdomain format. Use only letters, numbers, hyphens, and underscores.');

            return;
        }

        $fullDomain = "{$this->subdomainName}.{$this->domain->domain}";

        if ($this->editingSubdomainId) {
            // Update existing subdomain
            $subdomain = Subdomain::find($this->editingSubdomainId);
            if (! $subdomain || $subdomain->domain_id !== $this->domain->id) {
                session()->flash('error', 'Subdomain not found.');

                return;
            }

            // Check if another subdomain with this name exists
            $existing = Subdomain::where('domain_id', $this->domain->id)
                ->where('subdomain', $this->subdomainName)
                ->where('id', '!=', $this->editingSubdomainId)
                ->first();

            if ($existing) {
                session()->flash('error', 'A subdomain with this name already exists.');

                return;
            }

            $subdomain->update([
                'subdomain' => $this->subdomainName,
                'full_domain' => $fullDomain,
                'notes' => $this->subdomainNotes ?: null,
            ]);

            session()->flash('message', 'Subdomain updated successfully!');
        } else {
            // Create new subdomain
            $existing = Subdomain::where('domain_id', $this->domain->id)
                ->where('subdomain', $this->subdomainName)
                ->first();

            if ($existing) {
                session()->flash('error', 'A subdomain with this name already exists.');

                return;
            }

            Subdomain::create([
                'domain_id' => $this->domain->id,
                'subdomain' => $this->subdomainName,
                'full_domain' => $fullDomain,
                'notes' => $this->subdomainNotes ?: null,
                'is_active' => true,
            ]);

            session()->flash('message', 'Subdomain added successfully!');
        }

        $this->closeSubdomainModal();
        $this->loadDomain();
    }

    public function deleteSubdomain(string $subdomainId): void
    {
        $subdomain = Subdomain::find($subdomainId);
        if (! $subdomain || $subdomain->domain_id !== $this->domain->id) {
            session()->flash('error', 'Subdomain not found.');

            return;
        }

        $subdomain->delete();
        session()->flash('message', 'Subdomain deleted successfully!');
        $this->loadDomain();
    }

    public function updateSubdomainIp(string $subdomainId): void
    {
        $subdomain = Subdomain::find($subdomainId);
        if (! $subdomain || $subdomain->domain_id !== $this->domain->id) {
            session()->flash('error', 'Subdomain not found.');

            return;
        }

        try {
            Artisan::call('domains:update-ip-info', [
                '--domain' => $subdomain->full_domain,
            ]);

            $this->loadDomain();
            session()->flash('message', 'Subdomain IP information updated!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update subdomain IP: '.$e->getMessage());
        }
    }

    public function discoverSubdomainsFromDns(): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        try {
            // Get all DNS records for this domain
            $dnsRecords = $this->domain->dnsRecords;

            if ($dnsRecords->isEmpty()) {
                session()->flash('error', 'No DNS records found. Please sync DNS records first.');

                return;
            }

            $discoveredSubdomains = [];
            $existingSubdomains = $this->domain->subdomains->pluck('subdomain')->toArray();

            // Extract subdomain names from DNS records
            foreach ($dnsRecords as $record) {
                $host = trim($record->host ?? '');

                // Skip empty, root (@), or wildcard records
                if (empty($host) || $host === '@' || $host === '*' || str_starts_with($host, '*.')) {
                    continue;
                }

                // Extract subdomain name
                // Host could be: "www", "www.again.com.au", "api.again.com.au", etc.
                $subdomainName = $this->extractSubdomainName($host, $this->domain->domain);

                if ($subdomainName && ! in_array($subdomainName, $discoveredSubdomains) && ! in_array($subdomainName, $existingSubdomains)) {
                    $discoveredSubdomains[] = $subdomainName;
                }
            }

            if (empty($discoveredSubdomains)) {
                session()->flash('info', 'No new subdomains found in DNS records.');

                return;
            }

            // Create subdomain entries
            $created = 0;
            foreach ($discoveredSubdomains as $subdomainName) {
                $fullDomain = "{$subdomainName}.{$this->domain->domain}";

                // Check if it already exists (double-check)
                $exists = Subdomain::where('domain_id', $this->domain->id)
                    ->where('subdomain', $subdomainName)
                    ->exists();

                if (! $exists) {
                    Subdomain::create([
                        'domain_id' => $this->domain->id,
                        'subdomain' => $subdomainName,
                        'full_domain' => $fullDomain,
                        'is_active' => true,
                    ]);
                    $created++;
                }
            }

            $this->loadDomain();
            session()->flash('message', "Discovered and created {$created} subdomain(s) from DNS records: ".implode(', ', $discoveredSubdomains));
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to discover subdomains: '.$e->getMessage());
        }
    }

    /**
     * Extract subdomain name from DNS record host
     *
     * @param  string  $host  DNS record host (e.g., "www", "www.again.com.au", "api.again.com.au")
     * @param  string  $domain  Main domain (e.g., "again.com.au")
     * @return string|null Subdomain name (e.g., "www", "api") or null if not a subdomain
     */
    private function extractSubdomainName(string $host, string $domain): ?string
    {
        // Remove trailing dot if present
        $host = rtrim($host, '.');

        // If host is exactly the domain, it's not a subdomain
        if ($host === $domain) {
            return null;
        }

        // If host ends with the domain, extract the subdomain part
        if (str_ends_with($host, '.'.$domain)) {
            $subdomain = substr($host, 0, -(strlen($domain) + 1)); // +1 for the dot

            // Validate subdomain name (alphanumeric, hyphens, underscores)
            if (preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $subdomain)) {
                return $subdomain;
            }
        }

        // If host doesn't contain the domain, it might be just the subdomain name
        if (! str_contains($host, '.')) {
            // Validate it's a valid subdomain name
            if (preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $host)) {
                return $host;
            }
        }

        return null;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
