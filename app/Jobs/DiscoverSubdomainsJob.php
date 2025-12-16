<?php

namespace App\Jobs;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\IpApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DiscoverSubdomainsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $domainId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(IpApiService $ipApiService): void
    {
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('DiscoverSubdomainsJob: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        try {
            // Get all DNS records for this domain
            $dnsRecords = $domain->dnsRecords;

            if ($dnsRecords->isEmpty()) {
                Log::info('DiscoverSubdomainsJob: No DNS records found', [
                    'domain' => $domain->domain,
                ]);

                return;
            }

            $discoveredSubdomains = [];
            $existingSubdomains = $domain->subdomains->pluck('subdomain')->toArray();

            // Extract subdomain names from DNS records
            foreach ($dnsRecords as $record) {
                $host = trim($record->host ?? '');

                // Skip empty, root (@), or wildcard records
                if (empty($host) || $host === '@' || $host === '*' || str_starts_with($host, '*.')) {
                    continue;
                }

                // Extract subdomain name
                $subdomainName = $this->extractSubdomainName($host, $domain->domain);

                if ($subdomainName && ! in_array($subdomainName, $discoveredSubdomains) && ! in_array($subdomainName, $existingSubdomains)) {
                    $discoveredSubdomains[] = $subdomainName;
                }
            }

            if (empty($discoveredSubdomains)) {
                Log::info('DiscoverSubdomainsJob: No new subdomains found', [
                    'domain' => $domain->domain,
                ]);

                return;
            }

            // Create subdomain entries and populate IP from DNS records
            $created = 0;
            foreach ($discoveredSubdomains as $subdomainName) {
                $fullDomain = "{$subdomainName}.{$domain->domain}";

                // Check if it already exists (double-check)
                $exists = Subdomain::where('domain_id', $domain->id)
                    ->where('subdomain', $subdomainName)
                    ->exists();

                if (! $exists) {
                    // Find IP address from DNS A records for this subdomain
                    $ipAddress = $this->findIpFromDnsRecords($fullDomain, $dnsRecords);

                    $subdomain = Subdomain::create([
                        'domain_id' => $domain->id,
                        'subdomain' => $subdomainName,
                        'full_domain' => $fullDomain,
                        'ip_address' => $ipAddress,
                        'is_active' => true,
                    ]);

                    // If we have an IP address, try to get IP-API info
                    if ($ipAddress) {
                        try {
                            $ipApiData = $ipApiService->query($ipAddress);

                            if ($ipApiData) {
                                $updateData = [
                                    'ip_checked_at' => now(),
                                    'ip_isp' => $ipApiData['isp'] ?? null,
                                    'ip_organization' => $ipApiData['org'] ?? null,
                                    'ip_as_number' => $ipApiData['as'] ?? null,
                                    'ip_country' => $ipApiData['country'] ?? null,
                                    'ip_city' => $ipApiData['city'] ?? null,
                                    'ip_hosting_flag' => $ipApiData['hosting'] ?? null,
                                ];

                                $ipApiProvider = $ipApiService->extractHostingProvider($ipApiData);
                                if ($ipApiProvider) {
                                    $updateData['hosting_provider'] = $ipApiProvider;
                                    // Get suggested login URL for the provider
                                    $suggestedUrl = \App\Services\HostingProviderUrls::getLoginUrl($ipApiProvider);
                                    if ($suggestedUrl) {
                                        $updateData['hosting_admin_url'] = $suggestedUrl;
                                    }
                                }

                                $subdomain->update($updateData);
                            }
                        } catch (\Exception $e) {
                            // Silently fail - IP-API update is optional
                        }
                    }

                    $created++;
                }
            }

            Log::info('DiscoverSubdomainsJob: Completed', [
                'domain' => $domain->domain,
                'created' => $created,
                'subdomains' => $discoveredSubdomains,
            ]);
        } catch (\Exception $e) {
            Log::error('DiscoverSubdomainsJob: Failed', [
                'domain' => $domain->domain,
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Extract subdomain name from DNS record host
     */
    private function extractSubdomainName(string $host, string $domain): ?string
    {
        $host = rtrim($host, '.');

        if ($host === $domain) {
            return null;
        }

        if (str_ends_with($host, '.'.$domain)) {
            $subdomain = substr($host, 0, -(strlen($domain) + 1));

            if (preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $subdomain)) {
                return $subdomain;
            }
        }

        if (! str_contains($host, '.')) {
            if (preg_match('/^[a-z0-9]([a-z0-9\-_]*[a-z0-9])?$/i', $host)) {
                return $host;
            }
        }

        return null;
    }

    /**
     * Find IP address from DNS records for a subdomain
     *
     * @param  iterable<DnsRecord>  $dnsRecords
     */
    private function findIpFromDnsRecords(string $fullDomain, iterable $dnsRecords): ?string
    {
        foreach ($dnsRecords as $record) {
            $host = trim($record->host ?? '');
            $host = rtrim($host, '.');

            if (($host === $fullDomain || $host === rtrim($fullDomain, '.')) && $record->type === 'A') {
                $value = trim($record->value ?? '');
                if (filter_var($value, FILTER_VALIDATE_IP)) {
                    return $value;
                }
            }

            if (($host === $fullDomain || $host === rtrim($fullDomain, '.')) && $record->type === 'CNAME') {
                $cnameTarget = trim($record->value ?? '');
                $cnameTarget = rtrim($cnameTarget, '.');

                foreach ($dnsRecords as $targetRecord) {
                    $targetHost = trim($targetRecord->host ?? '');
                    $targetHost = rtrim($targetHost, '.');

                    if (($targetHost === $cnameTarget || $targetHost === rtrim($cnameTarget, '.')) && $targetRecord->type === 'A') {
                        $value = trim($targetRecord->value ?? '');
                        if (filter_var($value, FILTER_VALIDATE_IP)) {
                            return $value;
                        }
                    }
                }
            }
        }

        try {
            $aRecords = @dns_get_record($fullDomain, DNS_A);
            if ($aRecords && ! empty($aRecords)) {
                $ip = $aRecords[0]['ip'] ?? null;
                if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        return null;
    }
}
