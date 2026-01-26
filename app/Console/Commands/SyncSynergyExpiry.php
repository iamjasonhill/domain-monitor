<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainEligibilityCheck;
use App\Models\SynergyCredential;
use App\Services\PlatformDetector;
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
                            {--all : Sync all Australian TLD domains (.com.au, .net.au, etc.)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync domain information (expiry, status, nameservers, registrant info, etc.) from Synergy Wholesale API for Australian TLDs';

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

            if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
                $this->warn("Domain '{$domainOption}' is not an Australian TLD (.com.au, .net.au, etc.). Synergy Wholesale only handles Australian TLDs.");

                return Command::FAILURE;
            }

            return $this->syncDomain($domain, $client);
        }

        if ($allOption) {
            // Get all active domains and filter for Australian TLDs
            $allDomains = Domain::where('is_active', true)->get();
            $domains = $allDomains->filter(function ($domain) {
                return SynergyWholesaleClient::isAustralianTld($domain->domain);
            });

            if ($domains->isEmpty()) {
                $this->warn('No active Australian TLD domains found.');

                return Command::SUCCESS;
            }

            $this->info("Syncing expiry dates for {$domains->count()} Australian TLD domain(s)...");
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
            if (! empty($domainInfo['nameservers'])) {
                $updateData['nameservers'] = $domainInfo['nameservers'];
            }
            if (! empty($domainInfo['nameserver_details'])) {
                $updateData['nameserver_details'] = $domainInfo['nameserver_details'];
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

            // Additional .au compliance fields
            if (isset($domainInfo['au_policy_id'])) {
                $updateData['au_policy_id'] = $domainInfo['au_policy_id'];
            }
            if (isset($domainInfo['au_policy_desc'])) {
                $updateData['au_policy_desc'] = $domainInfo['au_policy_desc'];
            }
            if (isset($domainInfo['au_compliance_reason'])) {
                $updateData['au_compliance_reason'] = $domainInfo['au_compliance_reason'];
            }
            if (isset($domainInfo['au_association_id'])) {
                $updateData['au_association_id'] = $domainInfo['au_association_id'];
            }

            // Registry and domain identifiers
            if (isset($domainInfo['domain_roid'])) {
                $updateData['domain_roid'] = $domainInfo['domain_roid'];
            }
            if (isset($domainInfo['registry_id'])) {
                $updateData['registry_id'] = $domainInfo['registry_id'];
            }

            // DNS configuration
            if (isset($domainInfo['dns_config_id'])) {
                $updateData['dns_config_id'] = $domainInfo['dns_config_id'];
            }

            // ID protection and categories
            if (isset($domainInfo['id_protect'])) {
                $updateData['id_protect'] = $domainInfo['id_protect'];
            }
            if (isset($domainInfo['categories'])) {
                $updateData['categories'] = $domainInfo['categories'];
            }

            // Check transfer lock status
            $lockStatus = $client->getDomainLockStatus($domain->domain);
            if ($lockStatus && ! isset($lockStatus['error_message'])) {
                $updateData['transfer_lock'] = $lockStatus['locked'];
            }

            // Check renewal status
            $renewalStatus = $client->checkRenewalRequired($domain->domain);
            if ($renewalStatus && ! isset($renewalStatus['error_message'])) {
                $updateData['renewal_required'] = $renewalStatus['renewal_required'];
                $updateData['can_renew'] = $renewalStatus['can_renew'];
            }

            // Update registrar if not set
            if (! $domain->registrar && isset($domainInfo['registrar'])) {
                $updateData['registrar'] = $domainInfo['registrar'];
            } elseif (! $domain->registrar) {
                $updateData['registrar'] = 'Synergy Wholesale';
            }

            // Check if domain is parked (detect via platform detection)
            // Only check if platform is not already set or if we want to update it
            if (! $domain->platform || $domain->platform !== 'Parked') {
                try {
                    $platformDetector = app(PlatformDetector::class);
                    $platformInfo = $platformDetector->detect($domain->domain);

                    // If detected as parked, update platform
                    if ($platformInfo['platform_type'] === 'Parked') {
                        $updateData['platform'] = 'Parked';
                    }
                } catch (\Exception $e) {
                    // Silently fail platform detection - don't break sync
                }
            }

            // Update the domain
            $domain->update($updateData);

            if (array_key_exists('eligibility_valid', $updateData) || array_key_exists('eligibility_last_check', $updateData) || array_key_exists('eligibility_type', $updateData)) {
                DomainEligibilityCheck::create([
                    'domain_id' => $domain->id,
                    'source' => 'synergy',
                    'eligibility_type' => $updateData['eligibility_type'] ?? $domain->eligibility_type,
                    'is_valid' => $updateData['eligibility_valid'] ?? $domain->eligibility_valid,
                    'checked_at' => $updateData['eligibility_last_check'] ?? now(),
                    'payload' => [
                        'domain_status' => $updateData['domain_status'] ?? $domain->domain_status,
                        'raw_last_check' => $domainInfo['eligibility_last_check'] ?? null,
                    ],
                ]);
            }

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
                if (isset($updateData['nameservers'])) {
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
