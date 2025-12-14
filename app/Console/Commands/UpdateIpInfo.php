<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Subdomain;
use App\Services\HostingDetector;
use App\Services\IpApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateIpInfo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:update-ip-info
                            {--domain= : Specific domain to update (optional)}
                            {--all : Update all active domains and subdomains}
                            {--hours=24 : Only update domains not checked in the last N hours}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update IP address and hosting provider information from IP-API.com for domains and subdomains';

    /**
     * Execute the console command.
     */
    public function handle(IpApiService $ipApiService, HostingDetector $hostingDetector): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');
        $hours = (int) $this->option('hours');

        if ($domainOption) {
            $domain = Domain::where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->updateDomain($domain, $ipApiService, $hostingDetector);
        }

        if ($allOption) {
            // Get domains that need updating (not checked in last N hours)
            $domains = Domain::where('is_active', true)
                ->where(function ($query) use ($hours) {
                    $query->whereNull('ip_checked_at')
                        ->orWhere('ip_checked_at', '<', now()->subHours($hours));
                })
                ->get();

            if ($domains->isEmpty()) {
                $this->info('No domains need IP information updates.');

                return Command::SUCCESS;
            }

            $this->info("Updating IP information for {$domains->count()} domain(s)...");
            $this->newLine();

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $successCount = 0;
            foreach ($domains as $domain) {
                if ($this->updateDomain($domain, $ipApiService, $hostingDetector, false) === Command::SUCCESS) {
                    $successCount++;
                }
                $bar->advance();

                // Rate limiting: wait 2 seconds between requests (30 req/min, well under 45 limit)
                sleep(2);
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully updated {$successCount}/{$domains->count()} domain(s).");

            // Also update subdomains
            $this->updateSubdomains($ipApiService, $hostingDetector, $hours);

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Update IP information for a single domain
     */
    private function updateDomain(Domain $domain, IpApiService $ipApiService, HostingDetector $hostingDetector, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Updating IP information for: {$domain->domain}");
        }

        try {
            // Get IP addresses
            $ipAddresses = $this->getIpAddresses($domain->domain);

            if (empty($ipAddresses)) {
                if ($verbose) {
                    $this->warn('  Could not resolve domain to IP address');
                }

                return Command::FAILURE;
            }

            $primaryIp = $ipAddresses[0];

            // Query IP-API.com
            $ipApiData = $ipApiService->query($primaryIp);

            if (! $ipApiData) {
                if ($verbose) {
                    $this->warn('  Could not retrieve IP information from IP-API.com');
                }

                return Command::FAILURE;
            }

            // Update domain with IP-API data
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
            if ($ipApiProvider && ! $domain->hosting_provider) {
                $updateData['hosting_provider'] = $ipApiProvider;
            }

            $domain->update($updateData);

            if ($verbose) {
                $this->line("  IP: {$primaryIp}");
                $this->line('  Organization: '.($ipApiData['org'] ?? 'N/A'));
                $this->line('  ISP: '.($ipApiData['isp'] ?? 'N/A'));
                if ($ipApiProvider) {
                    $this->line("  Suggested Provider: {$ipApiProvider}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }
            Log::error('IP info update failed', [
                'domain' => $domain->domain,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Update IP information for subdomains
     */
    private function updateSubdomains(IpApiService $ipApiService, HostingDetector $hostingDetector, int $hours): void
    {
        $subdomains = Subdomain::where('is_active', true)
            ->where(function ($query) use ($hours) {
                $query->whereNull('ip_checked_at')
                    ->orWhere('ip_checked_at', '<', now()->subHours($hours));
            })
            ->get();

        if ($subdomains->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->info("Updating IP information for {$subdomains->count()} subdomain(s)...");

        $bar = $this->output->createProgressBar($subdomains->count());
        $bar->start();

        $successCount = 0;
        foreach ($subdomains as $subdomain) {
            try {
                $ipAddresses = $this->getIpAddresses($subdomain->full_domain);

                if (! empty($ipAddresses)) {
                    $primaryIp = $ipAddresses[0];
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

                        $ipApiProvider = $ipApiService->extractHostingProvider($ipApiData);
                        if ($ipApiProvider && ! $subdomain->hosting_provider) {
                            $updateData['hosting_provider'] = $ipApiProvider;
                        }

                        $subdomain->update($updateData);
                        $successCount++;
                    }
                }

                // Rate limiting
                sleep(2);
            } catch (\Exception $e) {
                Log::error('Subdomain IP info update failed', [
                    'subdomain' => $subdomain->full_domain,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully updated {$successCount}/{$subdomains->count()} subdomain(s).");
    }

    /**
     * Get IP addresses for domain
     *
     * @return array<int, string>
     */
    private function getIpAddresses(string $domain): array
    {
        $ipAddresses = [];

        try {
            // Get A records (IPv4)
            $aRecords = @dns_get_record($domain, DNS_A);
            if ($aRecords) {
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
            Log::debug('IP lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        return array_unique($ipAddresses);
    }
}
