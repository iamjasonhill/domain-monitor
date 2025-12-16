<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TestIpApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:ip-api 
                            {input : Domain name or IP address to query}
                            {--domain : Force input to be treated as a domain (will resolve to IP)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test IP-API.com integration to see what data is available for hosting provider detection';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $input = $this->argument('input');
        $forceDomain = $this->option('domain');

        $this->info('Testing IP-API.com integration');
        $this->newLine();

        // Determine if input is IP or domain
        $isIp = filter_var($input, FILTER_VALIDATE_IP) !== false;

        if ($isIp && ! $forceDomain) {
            $ipAddress = $input;
            $this->info("Input detected as IP address: {$ipAddress}");
        } else {
            // Treat as domain and resolve to IP
            $this->info("Resolving domain: {$input}");
            $ipAddress = $this->resolveDomainToIp($input);

            if (! $ipAddress) {
                $this->error("Failed to resolve domain '{$input}' to an IP address.");
                $this->warn('You can also test with a direct IP address: php artisan test:ip-api <ip-address>');

                return Command::FAILURE;
            }

            $this->info("Resolved to IP: {$ipAddress}");
        }

        $this->newLine();
        $this->info("Querying IP-API.com for: {$ipAddress}");
        $this->newLine();

        // Query IP-API.com
        try {
            $response = Http::timeout(10)
                ->get("http://ip-api.com/json/{$ipAddress}");

            if (! $response->successful()) {
                $this->error("API request failed with status: {$response->status()}");

                return Command::FAILURE;
            }

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === 'fail') {
                $this->error("API returned error: {$data['message']}");

                return Command::FAILURE;
            }

            // Display results in a formatted table
            $this->displayResults($data, $input, $ipAddress);

            // Show what fields might be useful for hosting detection
            $this->newLine();
            $this->info('Analysis for hosting provider detection:');
            $this->newLine();

            $this->analyzeForHosting($data);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Error querying IP-API.com: {$e->getMessage()}");
            Log::error('IP-API.com test failed', [
                'input' => $input,
                'ip' => $ipAddress ?? null,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Resolve domain to IP address
     */
    private function resolveDomainToIp(string $domain): ?string
    {
        // Remove protocol if present
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];

        try {
            // Try DNS A record first
            $aRecords = @dns_get_record($domain, DNS_A);
            if ($aRecords && ! empty($aRecords)) {
                return $aRecords[0]['ip'] ?? null;
            }

            // Fallback to gethostbyname
            $ip = @gethostbyname($domain);
            if ($ip && $ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        } catch (\Exception $e) {
            Log::debug('Domain resolution failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Display API results in a formatted table
     *
     * @param  array<string, mixed>  $data
     */
    private function displayResults(array $data, string $originalInput, string $ipAddress): void
    {
        $this->table(
            ['Field', 'Value'],
            [
                ['Query (IP)', $data['query'] ?? 'N/A'],
                ['Status', $data['status'] ?? 'N/A'],
                ['Country', $data['country'] ?? 'N/A'],
                ['Country Code', $data['countryCode'] ?? 'N/A'],
                ['Region', $data['region'] ?? 'N/A'],
                ['Region Name', $data['regionName'] ?? 'N/A'],
                ['City', $data['city'] ?? 'N/A'],
                ['ZIP Code', $data['zip'] ?? 'N/A'],
                ['Latitude', $data['lat'] ?? 'N/A'],
                ['Longitude', $data['lon'] ?? 'N/A'],
                ['Timezone', $data['timezone'] ?? 'N/A'],
                ['ISP', $data['isp'] ?? 'N/A'],
                ['Organization', $data['org'] ?? 'N/A'],
                ['AS Number', $data['as'] ?? 'N/A'],
                ['AS Name', $data['asname'] ?? 'N/A'],
                ['Mobile', isset($data['mobile']) ? ($data['mobile'] ? 'Yes' : 'No') : 'N/A'],
                ['Proxy', isset($data['proxy']) ? ($data['proxy'] ? 'Yes' : 'No') : 'N/A'],
                ['Hosting', isset($data['hosting']) ? ($data['hosting'] ? 'Yes' : 'No') : 'N/A'],
            ]
        );
    }

    /**
     * Analyze the data for hosting provider detection
     *
     * @param  array<string, mixed>  $data
     */
    private function analyzeForHosting(array $data): void
    {
        $usefulFields = [];

        // ISP field - often contains hosting provider name
        if (! empty($data['isp'])) {
            $usefulFields[] = "ISP: {$data['isp']}";
        }

        // Organization field - often contains hosting provider or company name
        if (! empty($data['org'])) {
            $usefulFields[] = "Organization: {$data['org']}";
        }

        // AS Name - Autonomous System name, often contains hosting provider
        if (! empty($data['asname'])) {
            $usefulFields[] = "AS Name: {$data['asname']}";
        }

        // Hosting flag - indicates if IP is used for hosting
        if (isset($data['hosting']) && $data['hosting']) {
            $usefulFields[] = 'Hosting flag: Yes (IP is used for hosting)';
        }

        // AS Number - can be used to identify hosting providers
        if (! empty($data['as'])) {
            $usefulFields[] = "AS Number: {$data['as']}";
        }

        if (empty($usefulFields)) {
            $this->warn('No useful fields found for hosting detection');
        } else {
            foreach ($usefulFields as $field) {
                $this->line("  • {$field}");
            }
        }

        $this->newLine();
        $this->comment('Potential hosting provider identification:');
        $this->comment('  - ISP field often contains hosting provider names');
        $this->comment('  - Organization field may contain company/hosting provider');
        $this->comment('  - AS Name (Autonomous System) can identify hosting networks');
        $this->comment('  - Hosting flag indicates if IP is used for hosting');
        $this->comment('  - AS Number can be cross-referenced with known hosting provider ASNs');
    }
}
