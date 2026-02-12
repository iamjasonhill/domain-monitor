<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Subdomain;
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
                $ipAddress = $subdomain->ip_address;

                if (! $ipAddress) {
                    $ipAddresses = $this->getIpAddresses($subdomain->full_domain);
                    $ipAddress = $ipAddresses[0] ?? null;
                }

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

        $ipApiService = app(IpApiService::class);
        $ipAddresses = $this->getIpAddresses($subdomain->full_domain);

        if ($ipAddresses === []) {
            return ['ok' => false, 'error' => 'Could not resolve IP address for subdomain.'];
        }

        $primaryIp = $ipAddresses[0];
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
     * @return array<int, string>
     */
    private function getIpAddresses(string $domain): array
    {
        $ipAddresses = [];

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
}
