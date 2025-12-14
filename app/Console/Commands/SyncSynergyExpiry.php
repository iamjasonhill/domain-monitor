<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Console\Command;

class SyncSynergyExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:sync-synergy-expiry 
                            {--domain= : Specific domain to sync (optional)}
                            {--all : Sync all .com.au domains}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync domain expiry dates from Synergy Wholesale API';

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

            $this->info("Syncing expiry dates for {$domains->count()} .com.au domain(s)...");
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
            $this->info("Successfully synced {$successCount}/{$domains->count()} domain(s).");

            // Update credential last_sync_at
            $credential->update(['last_sync_at' => now()]);

            return Command::SUCCESS;
        }

        $this->error('Please specify --domain=<domain> or --all');

        return Command::FAILURE;
    }

    /**
     * Sync expiry date for a single domain
     */
    private function syncDomain(Domain $domain, SynergyWholesaleClient $client, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Syncing expiry date for: {$domain->domain}");
        }

        try {
            $expiryDate = $client->syncDomainExpiry($domain->domain);

            if ($expiryDate) {
                $domain->update(['expires_at' => $expiryDate]);

                if ($verbose) {
                    $this->line("  Expiry Date: {$expiryDate->format('Y-m-d')}");
                }

                return Command::SUCCESS;
            }

            if ($verbose) {
                $this->warn('  Could not retrieve expiry date from Synergy Wholesale');
            }

            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($verbose) {
                $this->error("  Failed: {$e->getMessage()}");
            }

            return Command::FAILURE;
        }
    }
}
