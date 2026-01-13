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
use Illuminate\Support\Facades\Log;
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
                $query->orderByRaw('LOWER(host)');
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
            $this->syncMessage = 'Only Australian TLD domains (.com.au, .net.au, etc.) can be synced.';
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
            session()->flash('message', 'Domain information synced successfully!');
            $this->dispatch('sync-complete');
        } catch (\Exception $e) {
            $this->syncMessage = 'Error syncing: '.$e->getMessage();
            session()->flash('error', 'Error syncing domain information: '.$e->getMessage());
            $this->dispatch('sync-complete');
        } finally {
            $this->syncing = false;
        }
    }

    public function runHealthCheck(string $type): void
    {
        Log::info('Manual health check initiated', [
            'domain' => $this->domain->domain,
            'type' => $type,
            'is_parked' => $this->domain?->isParked(),
        ]);

        if ($this->domain?->isParked()) {
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
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can sync DNS records.');
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
        // Validate domain and TLD
        if (! $this->domain || ! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            $this->addError('dnsRecordHost', 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records.');
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records.');

            return;
        }

        // Normalize host field first (empty or @ means root domain)
        $host = trim($this->dnsRecordHost ?? '');
        if ($host === '' || $host === '@') {
            $host = '@';
            $this->dnsRecordHost = '@';
        }

        // Validate required fields
        $this->validate([
            'dnsRecordHost' => ['required', 'string', 'max:255'],
            'dnsRecordType' => ['required', 'string', 'in:A,AAAA,CNAME,MX,NS,TXT,SRV'],
            'dnsRecordValue' => ['required', 'string', 'max:65535'],
            'dnsRecordTtl' => ['required', 'integer', 'min:60', 'max:86400'],
            'dnsRecordPriority' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ], [
            'dnsRecordHost.required' => 'Host/Subdomain is required. Use @ for root domain or leave empty.',
            'dnsRecordValue.required' => 'Value is required.',
            'dnsRecordTtl.required' => 'TTL is required.',
            'dnsRecordTtl.min' => 'TTL must be at least 60 seconds.',
            'dnsRecordTtl.max' => 'TTL cannot exceed 86400 seconds (1 day).',
            'dnsRecordPriority.min' => 'Priority must be 0 or greater.',
            'dnsRecordPriority.max' => 'Priority cannot exceed 65535.',
        ]);

        // Validate host format (allow @ or valid subdomain)
        if ($host !== '@' && ! preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $host)) {
            $this->addError('dnsRecordHost', 'Host must be a valid subdomain name (letters, numbers, hyphens, underscores) or @ for root domain.');

            return;
        }

        // Additional validation for MX records
        if ($this->dnsRecordType === 'MX' && $this->dnsRecordPriority < 1) {
            $this->addError('dnsRecordPriority', 'Priority is required for MX records (typically 10-100).');

            return;
        }

        // Check for active credentials
        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            $this->addError('dnsRecordHost', 'No active domain registrar credentials found. Please configure Synergy Wholesale credentials in Settings.');
            session()->flash('error', 'No active domain registrar credentials found. Please configure Synergy Wholesale credentials in Settings.');

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
                    $errorMessage = $result['error_message'] ?? 'Failed to update DNS record.';
                    $this->addError('dnsRecordValue', $errorMessage);
                    session()->flash('error', $errorMessage);
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
                    $errorMessage = $result['error_message'] ?? 'Failed to add DNS record. Please check the values and try again.';
                    $this->addError('dnsRecordValue', $errorMessage);
                    session()->flash('error', $errorMessage);
                }
            }
        } catch (\Exception $e) {
            $errorMessage = 'Error saving DNS record: '.$e->getMessage();
            $this->addError('dnsRecordValue', $errorMessage);
            session()->flash('error', $errorMessage);
        }
    }

    public function deleteDnsRecord(string $recordId): void
    {
        if (! $this->domain || ! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            session()->flash('error', 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records.');

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
            session()->flash('error', 'No active domain registrar credentials found.');

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

    public function updateAllSubdomainsIp(): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        $subdomains = $this->domain->subdomains()->where('is_active', true)->get();

        if ($subdomains->isEmpty()) {
            session()->flash('info', 'No active subdomains to update.');

            return;
        }

        $updated = 0;
        $ipApiService = app(\App\Services\IpApiService::class);

        foreach ($subdomains as $subdomain) {
            try {
                // Get IP address first (from existing or resolve)
                $ipAddress = $subdomain->ip_address;

                if (! $ipAddress) {
                    $ipAddresses = $this->getIpAddresses($subdomain->full_domain);
                    $ipAddress = $ipAddresses[0] ?? null;
                }

                if (! $ipAddress) {
                    continue;
                }

                // Query IP-API.com
                $ipApiData = $ipApiService->query($ipAddress);

                if ($ipApiData) {
                    $updateData = [
                        'ip_address' => $ipAddress,
                        'ip_checked_at' => now(),
                        'ip_isp' => $ipApiData['isp'] ?? null,
                        'ip_organization' => $ipApiData['org'] ?? null,
                        'ip_as_number' => $ipApiData['as'] ?? null,
                        'ip_country' => $ipApiData['country'] ?? null,
                        'ip_city' => $ipApiData['city'] ?? null,
                        'ip_hosting_flag' => $ipApiData['hosting'] ?? null,
                    ];

                    $ipApiProvider = $ipApiService->extractHostingProvider($ipApiData);
                    if ($ipApiProvider && ! $subdomain->hosting_provider) {
                        $updateData['hosting_provider'] = $ipApiProvider;
                    }

                    $subdomain->update($updateData);
                    $updated++;

                    // Rate limiting: wait 2 seconds between requests
                    sleep(2);
                }
            } catch (\Exception $e) {
                // Continue with next subdomain
            }
        }

        $this->loadDomain();
        session()->flash('message', "Updated IP information for {$updated}/{$subdomains->count()} subdomain(s).");
    }

    public function updateSubdomainIp(string $subdomainId): void
    {
        $subdomain = Subdomain::find($subdomainId);
        if (! $subdomain || $subdomain->domain_id !== $this->domain->id) {
            session()->flash('error', 'Subdomain not found.');

            return;
        }

        try {
            // Use the UpdateIpInfo service directly for subdomains
            $ipApiService = app(\App\Services\IpApiService::class);

            // Get IP address first (from DNS or resolve)
            $ipAddresses = $this->getIpAddresses($subdomain->full_domain);

            if (empty($ipAddresses)) {
                session()->flash('error', 'Could not resolve IP address for subdomain.');

                return;
            }

            $primaryIp = $ipAddresses[0];

            // Query IP-API.com
            $ipApiData = $ipApiService->query($primaryIp);

            if ($ipApiData) {
                $updateData = [
                    'ip_address' => $primaryIp,
                    'ip_checked_at' => now(),
                    'ip_isp' => $ipApiData['isp'] ?? null,
                    'ip_organization' => $ipApiData['org'] ?? null,
                    'ip_as_number' => $ipApiData['as'] ?? null,
                    'ip_country' => $ipApiData['country'] ?? null,
                    'ip_city' => $ipApiData['city'] ?? null,
                    'ip_hosting_flag' => $ipApiData['hosting'] ?? null,
                ];

                // Also update hosting provider if IP-API suggests one
                $ipApiProvider = $ipApiService->extractHostingProvider($ipApiData);
                if ($ipApiProvider && ! $subdomain->hosting_provider) {
                    $updateData['hosting_provider'] = $ipApiProvider;
                    // Get suggested login URL for the provider
                    $suggestedUrl = \App\Services\HostingProviderUrls::getLoginUrl($ipApiProvider);
                    if ($suggestedUrl && ! $subdomain->hosting_admin_url) {
                        $updateData['hosting_admin_url'] = $suggestedUrl;
                    }
                }

                $subdomain->update($updateData);
                $this->loadDomain();
                session()->flash('message', 'Subdomain IP information updated!');
            } else {
                session()->flash('error', 'Could not retrieve IP information from IP-API.com.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update subdomain IP: '.$e->getMessage());
        }
    }

    /**
     * Get IP addresses for domain/subdomain
     *
     * @return array<int, string>
     */
    private function getIpAddresses(string $domain): array
    {
        $ipAddresses = [];

        try {
            // Get A records (IPv4)
            /** @var list<array{ip?: string}>|false $aRecords */
            $aRecords = @dns_get_record($domain, DNS_A);
            if (is_array($aRecords) && $aRecords !== []) {
                foreach ($aRecords as $record) {
                    if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP)) {
                        $ipAddresses[] = $record['ip'];
                    }
                }
            }

            // Also try gethostbyname as fallback
            $ip = @gethostbyname($domain);
            if ($ip && $ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (! in_array($ip, $ipAddresses)) {
                    $ipAddresses[] = $ip;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return array_unique($ipAddresses);
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

            // Logic to parse DNS records and find subdomains
            $discoveredSubdomains = [];
            $existingSubdomains = $this->domain->subdomains->pluck('subdomain')->toArray();

            foreach ($dnsRecords as $record) {
                // Skip if not A or CNAME
                if (! in_array($record->type, ['A', 'AAAA', 'CNAME'])) {
                    continue;
                }

                $host = strtolower($record->host);

                // Skip root, www, mail, webmail, ftp, cpanel, whm, localhost
                if (in_array($host, ['@', 'www', 'mail', 'webmail', 'ftp', 'cpanel', 'whm', 'localhost', '*'])) {
                    continue;
                }

                // Also skip wildcard
                if (str_starts_with($host, '*')) {
                    continue;
                }

                if (! in_array($host, $discoveredSubdomains) && ! in_array($host, $existingSubdomains)) {
                    $discoveredSubdomains[] = $host;
                }
            }

            if (empty($discoveredSubdomains)) {
                session()->flash('info', 'No new subdomains found in DNS records.');

                return;
            }

            $count = 0;
            foreach ($discoveredSubdomains as $subdomainName) {
                $this->domain->subdomains()->create([
                    'subdomain' => $subdomainName,
                    'full_domain' => "$subdomainName.{$this->domain->domain}",
                    'is_active' => true,
                ]);
                $count++;
            }

            $this->loadDomain();
            session()->flash('message', "Discovered {$count} new subdomains from DNS records.");
        } catch (\Exception $e) {
            session()->flash('error', 'Error discovering subdomains: '.$e->getMessage());
        }
    }

    public function applyFix(string $checkType): void
    {
        Log::info('Automated DNS fix initiated', [
            'domain' => $this->domain->domain,
            'type' => $checkType,
        ]);

        // 1. Validate Eligibility
        if (! $this->domain || ! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
            Log::warning('Automated DNS fix skipped: not eligible', ['domain' => $this->domain->domain ?? 'unknown']);
            session()->flash('error', 'Automated fixes are only available for Australian TLD domains.');

            return;
        }

        // 2. Get Credentials
        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            Log::error('Automated DNS fix failed: no credentials');
            session()->flash('error', 'No active Synergy credentials found.');

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        try {
            // 3. Fetch current live records to ensure we aren't duplicating
            $liveRecords = $client->getDnsRecords($this->domain->domain);
            if (! $liveRecords) {
                throw new \Exception('Could not fetch current DNS records from Synergy.');
            }

            $success = false;
            $message = '';

            switch ($checkType) {
                case 'spf':
                    $success = $this->fixSpf($client, $liveRecords, $message);
                    break;
                case 'dmarc':
                    $success = $this->fixDmarc($client, $liveRecords, $message);
                    break;
                case 'caa':
                    $success = $this->fixCaa($client, $liveRecords, $message);
                    break;
                default:
                    throw new \Exception("Unknown fix type: {$checkType}");
            }

            if ($success) {
                session()->flash('message', $message);
                $this->syncDnsRecords(); // Sync back to local DB
                // Trigger health check to verify (might be too fast for propagation, but good UX)
                $this->runHealthCheck('email_security');
            } else {
                session()->flash('error', $message);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to apply fix: '.$e->getMessage());
        }
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     */
    private function fixSpf(SynergyWholesaleClient $client, array $records, string &$message): bool
    {
        // Look for existing SPF
        $existingSpf = null;
        foreach ($records as $record) {
            if ($record['type'] === 'TXT' && ($record['host'] === '@' || $record['host'] === $this->domain->domain) && str_starts_with($record['value'], 'v=spf1')) {
                $existingSpf = $record;
                break;
            }
        }

        $defaultValue = 'v=spf1 a mx ~all';

        Log::info('Checking for existing SPF record', ['found' => $existingSpf ? true : false]);

        if ($existingSpf && ! empty($existingSpf['id'])) {
            // Update
            $result = $client->updateDnsRecord(
                $this->domain->domain,
                (string) $existingSpf['id'],
                '@',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Updated existing SPF record to safe default.';
        } else {
            // Create
            $result = $client->addDnsRecord(
                $this->domain->domain,
                '@',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Created new SPF record.';
        }

        if (isset($result['status']) && $result['status'] === 'OK') {
            return true;
        }

        $message = $result['error_message'] ?? 'Unknown API error.';

        return false;
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     */
    private function fixDmarc(SynergyWholesaleClient $client, array $records, string &$message): bool
    {
        // Look for existing DMARC at _dmarc
        $existingDmarc = null;
        foreach ($records as $record) {
            if ($record['type'] === 'TXT' && $record['host'] === '_dmarc') {
                $existingDmarc = $record;
                break;
            }
        }

        $defaultValue = 'v=DMARC1; p=none;';

        Log::info('Checking for existing DMARC record', ['found' => $existingDmarc ? true : false]);

        if ($existingDmarc && ! empty($existingDmarc['id'])) {
            // Update
            $result = $client->updateDnsRecord(
                $this->domain->domain,
                (string) $existingDmarc['id'],
                '_dmarc',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Updated existing DMARC record to p=none.';
        } else {
            // Create
            $result = $client->addDnsRecord(
                $this->domain->domain,
                '_dmarc',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Created new DMARC record.';
        }

        if (isset($result['status']) && $result['status'] === 'OK') {
            return true;
        }

        $message = $result['error_message'] ?? 'Unknown API error.';

        return false;
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     */
    private function fixCaa(SynergyWholesaleClient $client, array $records, string &$message): bool
    {
        // Look for any existing CAA
        $hasCaa = false;
        foreach ($records as $record) {
            if ($record['type'] === 'CAA') {
                $hasCaa = true;
                break;
            }
        }

        if ($hasCaa) {
            $message = 'CAA records already exist. Automatic fix skipped to avoid breaking existing authorization.';

            return false;
        }

        // Create for Let's Encrypt
        $result = $client->addDnsRecord(
            $this->domain->domain,
            '@',
            'CAA',
            '0 issue "letsencrypt.org"',
            300
        );

        if (isset($result['status']) && $result['status'] === 'OK') {
            $message = "Created CAA record for Let's Encrypt.";

            return true;
        }

        $message = $result['error_message'] ?? 'Unknown API error.';

        return false;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.domain-detail');
    }
}
