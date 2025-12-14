<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\IpApiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class UpdateSubdomainsIpJob implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public $backoff = 60;

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
            Log::warning('UpdateSubdomainsIpJob: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        $subdomains = $domain->subdomains()->where('is_active', true)->get();

        if ($subdomains->isEmpty()) {
            Log::info('UpdateSubdomainsIpJob: No active subdomains', [
                'domain' => $domain->domain,
            ]);

            return;
        }

        $updated = 0;

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
                        // Get suggested login URL for the provider
                        $suggestedUrl = \App\Services\HostingProviderUrls::getLoginUrl($ipApiProvider);
                        if ($suggestedUrl && ! $subdomain->hosting_admin_url) {
                            $updateData['hosting_admin_url'] = $suggestedUrl;
                        }
                    }

                    $subdomain->update($updateData);
                    $updated++;

                    // Rate limiting: wait 2 seconds between requests
                    sleep(2);
                }
            } catch (\Exception $e) {
                Log::warning('UpdateSubdomainsIpJob: Failed to update subdomain', [
                    'subdomain' => $subdomain->full_domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('UpdateSubdomainsIpJob: Completed', [
            'domain' => $domain->domain,
            'updated' => $updated,
            'total' => $subdomains->count(),
        ]);
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
            $aRecords = @dns_get_record($domain, DNS_A);
            if ($aRecords) {
                foreach ($aRecords as $record) {
                    if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP)) {
                        $ipAddresses[] = $record['ip'];
                    }
                }
            }

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
}
