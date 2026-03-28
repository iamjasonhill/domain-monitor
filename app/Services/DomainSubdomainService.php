<?php

namespace App\Services;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\Subdomain;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class DomainSubdomainService
{
    public function findForDomain(Domain $domain, string $subdomainId): ?Subdomain
    {
        $subdomain = Subdomain::find($subdomainId);

        if (! $subdomain || $subdomain->domain_id !== $domain->id) {
            return null;
        }

        return $subdomain;
    }

    /**
     * Reconcile the subdomain inventory from the synced DNS zone and refresh DNS resolution state.
     *
     * @return array{ok: bool, message?: string, error?: string, created?: int, refreshed?: int}
     */
    public function syncFromDnsRecords(Domain $domain): array
    {
        /** @var EloquentCollection<int, DnsRecord> $dnsRecords */
        $dnsRecords = $domain->dnsRecords()->orderByRaw('LOWER(host)')->get();

        if ($dnsRecords->isEmpty()) {
            return ['ok' => false, 'error' => 'No DNS records found. Please sync DNS records first.'];
        }

        $discoveredSubdomains = [];
        foreach ($dnsRecords as $record) {
            if (! in_array($record->type, ['A', 'AAAA', 'CNAME'], true)) {
                continue;
            }

            $subdomainName = $this->extractSubdomainName((string) $record->host, $domain->domain);

            if ($subdomainName === null || in_array($subdomainName, $discoveredSubdomains, true)) {
                continue;
            }

            $discoveredSubdomains[] = $subdomainName;
        }

        $created = 0;
        foreach ($discoveredSubdomains as $subdomainName) {
            $subdomain = Subdomain::withTrashed()->firstOrNew([
                'domain_id' => $domain->id,
                'subdomain' => $subdomainName,
            ]);

            $wasNew = ! $subdomain->exists;

            $subdomain->fill([
                'full_domain' => "{$subdomainName}.{$domain->domain}",
                'is_active' => true,
            ]);

            if ($subdomain->trashed()) {
                $subdomain->restore();
            } else {
                $subdomain->save();
            }

            if ($wasNew) {
                $created++;
            }
        }

        $refreshed = 0;
        $activeSubdomains = $domain->subdomains()->where('is_active', true)->get();
        foreach ($activeSubdomains as $subdomain) {
            $this->refreshDnsResolution($subdomain, $dnsRecords);
            $refreshed++;
        }

        return [
            'ok' => true,
            'created' => $created,
            'refreshed' => $refreshed,
            'message' => "Synced {$created} new subdomain(s) and refreshed DNS resolution for {$refreshed} active subdomain(s).",
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function saveSubdomain(Domain $domain, string $subdomainName, string $subdomainNotes = '', ?string $editingSubdomainId = null): array
    {
        if ($subdomainName === '') {
            return ['ok' => false, 'error' => 'Subdomain name is required.'];
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $subdomainName)) {
            return ['ok' => false, 'error' => 'Invalid subdomain format. Use only letters, numbers, hyphens, and underscores.'];
        }

        $fullDomain = "{$subdomainName}.{$domain->domain}";

        if ($editingSubdomainId) {
            $subdomain = $this->findForDomain($domain, $editingSubdomainId);
            if (! $subdomain) {
                return ['ok' => false, 'error' => 'Subdomain not found.'];
            }

            $existing = Subdomain::where('domain_id', $domain->id)
                ->where('subdomain', $subdomainName)
                ->where('id', '!=', $editingSubdomainId)
                ->first();

            if ($existing) {
                return ['ok' => false, 'error' => 'A subdomain with this name already exists.'];
            }

            $subdomain->update([
                'subdomain' => $subdomainName,
                'full_domain' => $fullDomain,
                'notes' => $subdomainNotes !== '' ? $subdomainNotes : null,
            ]);

            return ['ok' => true, 'message' => 'Subdomain updated successfully!'];
        }

        try {
            $subdomain = Subdomain::firstOrCreate(
                [
                    'domain_id' => $domain->id,
                    'subdomain' => $subdomainName,
                ],
                [
                    'full_domain' => $fullDomain,
                    'notes' => $subdomainNotes !== '' ? $subdomainNotes : null,
                    'is_active' => true,
                ]
            );

            if ($subdomain->wasRecentlyCreated) {
                return ['ok' => true, 'message' => 'Subdomain added successfully!'];
            }

            return ['ok' => false, 'error' => 'A subdomain with this name already exists.'];
        } catch (QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                return ['ok' => false, 'error' => 'A subdomain with this name already exists.'];
            }

            Log::error('Subdomain creation failed', [
                'domain_id' => $domain->id,
                'subdomain' => $subdomainName,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'error' => 'Failed to create subdomain: '.$e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function deleteSubdomain(Domain $domain, string $subdomainId): array
    {
        $subdomain = $this->findForDomain($domain, $subdomainId);
        if (! $subdomain) {
            return ['ok' => false, 'error' => 'Subdomain not found.'];
        }

        $subdomain->delete();

        return ['ok' => true, 'message' => 'Subdomain deleted successfully!'];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string, info?: string}
     */
    public function updateAllSubdomainsIp(Domain $domain): array
    {
        $subdomains = $domain->subdomains()->where('is_active', true)->get();

        if ($subdomains->isEmpty()) {
            return ['ok' => true, 'info' => 'No active subdomains to update.'];
        }

        $updated = 0;
        $ipApiService = app(IpApiService::class);

        foreach ($subdomains as $subdomain) {
            try {
                $resolution = $this->refreshDnsResolution($subdomain);
                $ipAddress = $resolution['primary_ip'];

                if (! $ipAddress) {
                    continue;
                }

                $ipApiData = $ipApiService->query($ipAddress);

                if (! $ipApiData) {
                    continue;
                }

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

                sleep(2);
            } catch (\Exception $e) {
                // Continue with next subdomain
            }
        }

        return [
            'ok' => true,
            'message' => "Updated IP information for {$updated}/{$subdomains->count()} subdomain(s).",
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function updateSubdomainIp(Domain $domain, string $subdomainId): array
    {
        $subdomain = $this->findForDomain($domain, $subdomainId);
        if (! $subdomain) {
            return ['ok' => false, 'error' => 'Subdomain not found.'];
        }

        $resolution = $this->refreshDnsResolution($subdomain);
        if (! $resolution['resolves']) {
            return ['ok' => false, 'error' => 'Subdomain does not currently resolve in DNS.'];
        }

        $ipApiService = app(IpApiService::class);
        $primaryIp = $resolution['primary_ip'];
        $ipApiData = $ipApiService->query($primaryIp);

        if (! $ipApiData) {
            return ['ok' => false, 'error' => 'Could not retrieve IP information from IP-API.com.'];
        }

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

        $ipApiProvider = $ipApiService->extractHostingProvider($ipApiData);
        if ($ipApiProvider && ! $subdomain->hosting_provider) {
            $updateData['hosting_provider'] = $ipApiProvider;

            $suggestedUrl = HostingProviderUrls::getLoginUrl($ipApiProvider);
            if ($suggestedUrl && ! $subdomain->hosting_admin_url) {
                $updateData['hosting_admin_url'] = $suggestedUrl;
            }
        }

        $subdomain->update($updateData);

        return ['ok' => true, 'message' => 'Subdomain IP information updated!'];
    }

    /**
     * @param  iterable<DnsRecord>|null  $dnsRecords
     * @return array{resolves: bool, primary_ip: string|null, ips: array<int, string>}
     */
    public function refreshDnsResolution(Subdomain $subdomain, ?iterable $dnsRecords = null): array
    {
        $ipAddresses = $this->getIpAddresses($subdomain->full_domain, $dnsRecords);
        $primaryIp = $ipAddresses !== [] ? $ipAddresses[0] : null;

        $subdomain->update([
            'ip_address' => $primaryIp,
            'ip_checked_at' => now(),
            'ip_isp' => $primaryIp ? $subdomain->ip_isp : null,
            'ip_organization' => $primaryIp ? $subdomain->ip_organization : null,
            'ip_as_number' => $primaryIp ? $subdomain->ip_as_number : null,
            'ip_country' => $primaryIp ? $subdomain->ip_country : null,
            'ip_city' => $primaryIp ? $subdomain->ip_city : null,
            'ip_hosting_flag' => $primaryIp ? $subdomain->ip_hosting_flag : null,
            'hosting_provider' => $primaryIp ? $subdomain->hosting_provider : null,
            'hosting_admin_url' => $primaryIp ? $subdomain->hosting_admin_url : null,
        ]);

        return [
            'resolves' => $primaryIp !== null,
            'primary_ip' => $primaryIp,
            'ips' => $ipAddresses,
        ];
    }

    /**
     * Extract subdomain name from a DNS record host.
     */
    public function extractSubdomainName(string $host, string $domain): ?string
    {
        $host = strtolower(rtrim(trim($host), '.'));
        $domain = strtolower($domain);

        if ($host === '' || $host === '@' || $host === '*' || str_starts_with($host, '*.')) {
            return null;
        }

        if ($host === $domain) {
            return null;
        }

        if (str_ends_with($host, '.'.$domain)) {
            $host = substr($host, 0, -(strlen($domain) + 1));
        }

        if (preg_match('/^[a-z0-9]([a-z0-9\-_\.]*[a-z0-9])?$/i', $host) !== 1) {
            return null;
        }

        return $host;
    }

    /**
     * @param  iterable<int, DnsRecord>|null  $dnsRecords
     * @return array<int, string>
     */
    private function getIpAddresses(string $domain, ?iterable $dnsRecords = null): array
    {
        $ipAddresses = [];
        $normalizedDomain = strtolower(rtrim($domain, '.'));

        if ($dnsRecords !== null) {
            foreach ($dnsRecords as $record) {
                $recordHost = strtolower(rtrim(trim((string) $record->host), '.'));

                if (! $this->hostMatchesDomain($recordHost, $normalizedDomain)) {
                    continue;
                }

                if (in_array($record->type, ['A', 'AAAA'], true)) {
                    $value = trim((string) $record->value);
                    if (filter_var($value, FILTER_VALIDATE_IP)) {
                        $ipAddresses[] = $value;
                    }
                }

                if ($record->type === 'CNAME') {
                    $cnameTarget = strtolower(rtrim(trim((string) $record->value), '.'));

                    foreach ($dnsRecords as $targetRecord) {
                        $targetHost = strtolower(rtrim(trim((string) $targetRecord->host), '.'));

                        if (! $this->hostMatchesDomain($targetHost, $cnameTarget)) {
                            continue;
                        }

                        if (in_array($targetRecord->type, ['A', 'AAAA'], true)) {
                            $value = trim((string) $targetRecord->value);
                            if (filter_var($value, FILTER_VALIDATE_IP)) {
                                $ipAddresses[] = $value;
                            }
                        }
                    }
                }
            }
        }

        try {
            /** @var list<array{ip?: string}>|false $aRecords */
            $aRecords = @dns_get_record($domain, DNS_A);
            if (is_array($aRecords) && $aRecords !== []) {
                foreach ($aRecords as $record) {
                    if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP)) {
                        $ipAddresses[] = $record['ip'];
                    }
                }
            }

            $ip = @gethostbyname($domain);
            if ($ip && $ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP) && ! in_array($ip, $ipAddresses, true)) {
                $ipAddresses[] = $ip;
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        /** @var array<int, string> $uniqueIps */
        $uniqueIps = array_values(array_unique($ipAddresses));

        return $uniqueIps;
    }

    private function hostMatchesDomain(string $recordHost, string $domain): bool
    {
        return $recordHost === $domain
            || $recordHost === $this->shortHost($domain)
            || $recordHost === rtrim($domain, '.');
    }

    private function shortHost(string $domain): string
    {
        $parts = explode('.', $domain);

        return $parts[0];
    }
}
