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
                            {--all : Sync all Australian TLD domains (.com.au, .net.au, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync DNS records from Synergy Wholesale API for Australian TLD domains';

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

            if (! \App\Services\SynergyWholesaleClient::isAustralianTld($domain->domain)) {
                $this->warn("Domain '{$domainOption}' is not an Australian TLD (.com.au, .net.au, etc.). Synergy Wholesale only handles Australian TLDs.");

                return Command::FAILURE;
            }

            return $this->syncDomain($domain, $client);
        }

        if ($allOption) {
            // Get all active domains and filter for Australian TLDs
            $allDomains = Domain::where('is_active', true)->get();
            $domains = $allDomains->filter(function ($domain) {
                return \App\Services\SynergyWholesaleClient::isAustralianTld($domain->domain);
            });

            if ($domains->isEmpty()) {
                $this->warn('No active Australian TLD domains found.');

                return Command::SUCCESS;
            }

            $this->info("Syncing DNS records for {$domains->count()} Australian TLD domain(s)...");
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
            /** @var array<int, array{host?: string, type?: string, value?: string, ttl?: int|null, priority?: int|null, id?: string|null}>|null $dnsRecords */
            $dnsRecords = $client->getDnsRecords($domain->domain);

            if (empty($dnsRecords)) {
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
