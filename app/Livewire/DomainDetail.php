<?php

namespace App\Livewire;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\SynergyCredential;
use App\Services\DomainDnsRecordService;
use App\Services\DomainSubdomainService;
use App\Services\HostingDetector;
use App\Services\PlatformDetector;
use App\Services\SynergyWholesaleClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Dns\Dns;

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
        if (! \App\Services\SynergyWholesaleClient::isAustralianTld($this->domain->domain)) {
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
            \App\Jobs\SyncDnsRecordsJob::dispatch($this->domain->id);

            session()->flash('message', 'DNS sync queued. Job will process in the background via Horizon.');
            $this->dispatch('dns-sync-complete');
        } catch (\Exception $e) {
            session()->flash('error', 'Error queueing DNS sync: '.$e->getMessage());
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

        $record = app(DomainDnsRecordService::class)->findDomainRecord($this->domain, $recordId);

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
        if (! $this->domain) {
            $this->addError('dnsRecordHost', 'Domain not found.');

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

        $service = app(DomainDnsRecordService::class);
        $recordData = [
            'host' => $this->dnsRecordHost,
            'type' => $this->dnsRecordType,
            'value' => $this->dnsRecordValue,
            'ttl' => $this->dnsRecordTtl,
            'priority' => $this->dnsRecordPriority,
        ];

        try {
            $result = $service->saveRecord($this->domain, $recordData, $this->editingDnsRecordId);

            if ($result['ok']) {
                session()->flash('message', $result['message'] ?? 'DNS record saved successfully!');
                $this->closeDnsRecordModal();
                $this->loadDomain();
            } else {
                $errorField = $result['error_field'] ?? 'dnsRecordValue';
                $this->addError($errorField, $result['error'] ?? 'Failed to save DNS record.');
            }
        } catch (\Exception $e) {
            $errorMessage = 'Error saving DNS record: '.$e->getMessage();
            $this->addError('dnsRecordValue', $errorMessage);
        }
    }

    public function deleteDnsRecord(string $recordId): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        try {
            $result = app(DomainDnsRecordService::class)->deleteRecord($this->domain, $recordId);

            if ($result['ok']) {
                session()->flash('message', $result['message'] ?? 'DNS record deleted successfully!');
                $this->loadDomain();
            } else {
                session()->flash('error', $result['error'] ?? 'Failed to delete DNS record.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error deleting DNS record: '.$e->getMessage());
            Log::error('DNS record deletion failed', [
                'domain_id' => $this->domain->id,
                'record_id' => $recordId,
                'error' => $e->getMessage(),
            ]);
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
        $subdomain = app(DomainSubdomainService::class)->findForDomain($this->domain, $subdomainId);
        if (! $subdomain) {
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
        $result = app(DomainSubdomainService::class)->saveSubdomain(
            $this->domain,
            $this->subdomainName,
            $this->subdomainNotes,
            $this->editingSubdomainId
        );

        if ($result['ok']) {
            session()->flash('message', $result['message'] ?? 'Subdomain saved successfully!');
        } else {
            session()->flash('error', $result['error'] ?? 'Failed to save subdomain.');

            return;
        }

        $this->closeSubdomainModal();
        $this->loadDomain();
    }

    public function deleteSubdomain(string $subdomainId): void
    {
        $result = app(DomainSubdomainService::class)->deleteSubdomain($this->domain, $subdomainId);

        if (! $result['ok']) {
            session()->flash('error', $result['error'] ?? 'Failed to delete subdomain.');

            return;
        }

        session()->flash('message', $result['message'] ?? 'Subdomain deleted successfully!');
        $this->loadDomain();
    }

    public function updateAllSubdomainsIp(): void
    {
        if (! $this->domain) {
            session()->flash('error', 'Domain not found.');

            return;
        }

        $result = app(DomainSubdomainService::class)->updateAllSubdomainsIp($this->domain);

        if (! $result['ok']) {
            session()->flash('error', $result['error'] ?? 'Failed to update subdomains.');

            return;
        }

        if (isset($result['info'])) {
            session()->flash('info', $result['info']);
        } elseif (isset($result['message'])) {
            session()->flash('message', $result['message']);
        }

        $this->loadDomain();
    }

    public function updateSubdomainIp(string $subdomainId): void
    {
        try {
            $result = app(DomainSubdomainService::class)->updateSubdomainIp($this->domain, $subdomainId);

            if ($result['ok']) {
                $this->loadDomain();
                session()->flash('message', $result['message'] ?? 'Subdomain IP information updated!');
            } else {
                session()->flash('error', $result['error'] ?? 'Failed to update subdomain IP.');
            }
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
        // First, check Synergy API records
        $hasCaaInApi = false;
        foreach ($records as $record) {
            if ($record['type'] === 'CAA') {
                $hasCaaInApi = true;
                break;
            }
        }

        // Also verify via actual DNS lookup to be extra safe
        $hasCaaInDns = false;
        try {
            $dns = new Dns;
            $caaRecords = $dns->getRecords($this->domain->domain, 'CAA');
            $hasCaaInDns = ! empty($caaRecords);
        } catch (\Exception $e) {
            Log::warning('CAA DNS lookup failed during fix check', [
                'domain' => $this->domain->domain,
                'error' => $e->getMessage(),
            ]);
            // If DNS lookup fails, we'll be conservative and skip creation
            $message = 'Could not verify CAA records via DNS lookup. Skipping automatic creation to avoid conflicts.';

            return false;
        }

        if ($hasCaaInApi || $hasCaaInDns) {
            $message = 'CAA records already exist (verified via API and DNS lookup). Automatic fix skipped to avoid breaking existing authorization.';

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
