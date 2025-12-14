<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\HostingDetector;
use Illuminate\Console\Command;

class DetectHosting extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:detect-hosting 
                            {--domain= : Specific domain to detect (optional)}
                            {--all : Detect for all active domains}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect hosting provider for domains (Vercel, Render, Cloudflare, AWS, Netlify, etc.)';

    /**
     * Execute the console command.
     */
    public function handle(HostingDetector $detector): int
    {
        $domainOption = $this->option('domain');
        $allOption = $this->option('all');

        if ($domainOption) {
            $domain = Domain::where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            return $this->detectForDomain($domain, $detector);
        }

        if ($allOption) {
            $domains = Domain::where('is_active', true)->get();

            if ($domains->isEmpty()) {
                $this->warn('No active domains found.');

                return Command::SUCCESS;
            }

            $this->info("Detecting hosting providers for {$domains->count()} domain(s)...");
            $this->newLine();

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $successCount = 0;
            foreach ($domains as $domain) {
                if ($this->detectForDomain($domain, $detector, false) === Command::SUCCESS) {
                    $successCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully detected hosting for {$successCount}/{$domains->count()} domain(s).");

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Detect hosting for a single domain
     */
    private function detectForDomain(Domain $domain, HostingDetector $detector, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Detecting hosting provider for: {$domain->domain}");
        }

        try {
            $result = $detector->detect($domain->domain);

            // Get suggested login URL if admin_url is not provided
            $adminUrl = $result['admin_url'];
            if (! $adminUrl && $result['provider']) {
                $adminUrl = \App\Services\HostingProviderUrls::getLoginUrl($result['provider']);
            }

            $updateData = [
                'hosting_provider' => $result['provider'],
                'hosting_admin_url' => $adminUrl,
            ];

            // Save IP-API data if available
            if (isset($result['ip_api_data']) && is_array($result['ip_api_data'])) {
                $ipApiData = $result['ip_api_data'];
                $ipAddresses = $detector->getIpAddresses($domain->domain);

                if (! empty($ipAddresses)) {
                    $updateData['ip_address'] = $ipAddresses[0];
                    $updateData['ip_checked_at'] = now();
                    $updateData['ip_isp'] = $ipApiData['isp'] ?? null;
                    $updateData['ip_organization'] = $ipApiData['org'] ?? null;
                    $updateData['ip_as_number'] = $ipApiData['as'] ?? null;
                    $updateData['ip_country'] = $ipApiData['country'] ?? null;
                    $updateData['ip_city'] = $ipApiData['city'] ?? null;
                    $updateData['ip_hosting_flag'] = $ipApiData['hosting'] ?? null;
                }
            }

            $domain->update($updateData);

            if ($verbose) {
                $this->line("  Provider: {$result['provider']} ({$result['confidence']} confidence)");
                if ($result['admin_url']) {
                    $this->line("  Admin URL: {$result['admin_url']}");
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }

            return Command::FAILURE;
        }
    }
}
