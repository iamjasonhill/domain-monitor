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
    protected $description = 'Sync domain information (expiry, status, nameservers, registrant info, etc.) from Synergy Wholesale API';

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
     * Sync domain information for a single domain
     */
    private function syncDomain(Domain $domain, SynergyWholesaleClient $client, bool $verbose = true): int
    {
        if ($verbose) {
            $this->info("Syncing domain information for: {$domain->domain}");
        }

        try {
            $domainInfo = $client->getDomainInfo($domain->domain);

            if (! $domainInfo) {
                if ($verbose) {
                    $this->warn('  Could not retrieve domain information from Synergy Wholesale');
                }

                return Command::FAILURE;
            }

            // Prepare update data
            $updateData = [];

            // Expiry date
            if (isset($domainInfo['expiry_date'])) {
                try {
                    $updateData['expires_at'] = new \DateTimeImmutable($domainInfo['expiry_date']);
                } catch (\Exception $e) {
                    // Invalid date format, skip
                }
            }

            // Created date
            if (isset($domainInfo['created_date'])) {
                try {
                    $updateData['created_at_synergy'] = new \DateTimeImmutable($domainInfo['created_date']);
                } catch (\Exception $e) {
                    // Invalid date format, skip
                }
            }

            // Status & renewal
            if (isset($domainInfo['domain_status'])) {
                $updateData['domain_status'] = $domainInfo['domain_status'];
            }
            if (isset($domainInfo['auto_renew'])) {
                $updateData['auto_renew'] = $domainInfo['auto_renew'];
            }

            // DNS & Nameservers
            if (isset($domainInfo['nameservers']) && is_array($domainInfo['nameservers'])) {
                $updateData['nameservers'] = $domainInfo['nameservers'];
            }
            if (isset($domainInfo['dns_config_name'])) {
                $updateData['dns_config_name'] = $domainInfo['dns_config_name'];
            }

            // Registrant information
            if (isset($domainInfo['registrant_name'])) {
                $updateData['registrant_name'] = $domainInfo['registrant_name'];
            }
            if (isset($domainInfo['registrant_id_type'])) {
                $updateData['registrant_id_type'] = $domainInfo['registrant_id_type'];
            }
            if (isset($domainInfo['registrant_id'])) {
                $updateData['registrant_id'] = $domainInfo['registrant_id'];
            }

            // Eligibility information
            if (isset($domainInfo['eligibility_type'])) {
                $updateData['eligibility_type'] = $domainInfo['eligibility_type'];
            }
            if (isset($domainInfo['eligibility_valid'])) {
                $updateData['eligibility_valid'] = $domainInfo['eligibility_valid'];
            }
            if (isset($domainInfo['eligibility_last_check'])) {
                try {
                    $updateData['eligibility_last_check'] = new \DateTimeImmutable($domainInfo['eligibility_last_check']);
                } catch (\Exception $e) {
                    // Invalid date format, skip
                }
            }

            // Update registrar if not set
            if (! $domain->registrar && isset($domainInfo['registrar'])) {
                $updateData['registrar'] = $domainInfo['registrar'];
            } elseif (! $domain->registrar) {
                $updateData['registrar'] = 'Synergy Wholesale';
            }

            // Update the domain
            $domain->update($updateData);

            if ($verbose) {
                $this->line('  ✅ Domain information synced:');
                if (isset($updateData['expires_at'])) {
                    $this->line("     Expiry: {$updateData['expires_at']->format('Y-m-d')}");
                }
                if (isset($updateData['domain_status'])) {
                    $this->line("     Status: {$updateData['domain_status']}");
                }
                if (isset($updateData['auto_renew'])) {
                    $this->line('     Auto-renew: '.($updateData['auto_renew'] ? 'Yes' : 'No'));
                }
                if (isset($updateData['nameservers']) && count($updateData['nameservers']) > 0) {
                    $this->line('     Nameservers: '.count($updateData['nameservers']));
                }
                if (isset($updateData['eligibility_valid'])) {
                    $this->line('     Eligibility: '.($updateData['eligibility_valid'] ? 'Valid' : 'Invalid'));
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
