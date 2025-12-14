<?php

namespace App\Console\Commands;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncDnsRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:sync-dns-records 
                            {--domain= : Specific domain to sync (optional)}
                            {--all : Sync all .com.au domains}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DNS records from Synergy Wholesale API for domains';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            $this->error('No active Synergy Wholesale credentials found.');
            $this->info('Please create credentials first using the SynergyCredential model.');

            return Command::FAILURE;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        $domainOption = $this->option('domain');
        $allOption = $this->option('all');

        if ($domainOption) {
            $domain = Domain::where('domain', $domainOption)->first();

            if (! $domain) {
                $this->error("Domain '{$domainOption}' not found.");

                return Command::FAILURE;
            }

            if (! str_ends_with($domain->domain, '.com.au')) {
                $this->warn("Domain '{$domainOption}' is not a .com.au domain. Synergy Wholesale only handles .com.au domains.");

                return Command::FAILURE;
            }

            return $this->syncDomain($domain, $client);
        }

        if ($allOption) {
            $domains = Domain::where('is_active', true)
                ->where('domain', 'LIKE', '%.com.au')
                ->get();

            if ($domains->isEmpty()) {
                $this->warn('No active .com.au domains found.');

                return Command::SUCCESS;
            }

            $this->info("Syncing DNS records for {$domains->count()} .com.au domain(s)...");
            $this->newLine();

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $successCount = 0;
            foreach ($domains as $domain) {
                if ($this->syncDomain($domain, $client, false) === Command::SUCCESS) {
                    $successCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
            $this->info("Successfully synced DNS records for {$successCount}/{$domains->count()} domain(s).");

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Sync DNS records for a single domain
     */
    private function syncDomain(Domain $domain, SynergyWholesaleClient $client, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Syncing DNS records for: {$domain->domain}");
        }

        try {
            $dnsRecords = $client->getDnsRecords($domain->domain);

            if (! $dnsRecords || empty($dnsRecords)) {
                if ($verbose) {
                    $this->warn('  Could not retrieve DNS records from Synergy Wholesale');
                }

                return Command::FAILURE;
            }

            // Delete existing records for this domain
            DnsRecord::where('domain_id', $domain->id)->delete();

            // Insert new records
            $syncedAt = now();
            $inserted = 0;

            foreach ($dnsRecords as $record) {
                DnsRecord::create([
                    'domain_id' => $domain->id,
                    'host' => $record['host'] ?? '',
                    'type' => strtoupper($record['type'] ?? ''),
                    'value' => $record['value'] ?? '',
                    'ttl' => $record['ttl'] ?? null,
                    'priority' => $record['priority'] ?? null,
                    'record_id' => $record['id'] ?? null,
                    'synced_at' => $syncedAt,
                ]);
                $inserted++;
            }

            if ($verbose) {
                $this->line("  ✅ Synced {$inserted} DNS record(s)");
                $this->line('     Types: '.implode(', ', array_unique(array_column($dnsRecords, 'type'))));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }
            Log::error('DNS records sync failed', [
                'domain' => $domain->domain,
                'error' => $e->getMessage(),
            ]);

            return Command::FAILURE;
        }
    }
}
