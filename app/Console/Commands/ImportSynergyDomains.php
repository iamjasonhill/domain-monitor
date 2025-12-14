<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportSynergyDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:import-synergy
                            {--dry-run : Show what would be imported without actually importing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bulk import all domains from Synergy Wholesale API';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            $this->error('No active Synergy Wholesale credentials found.');

            return Command::FAILURE;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        $this->info('Fetching domains from Synergy Wholesale...');
        $this->newLine();

        try {
            $domains = $client->listDomains();

            if (empty($domains)) {
                $this->warn('No domains found in Synergy Wholesale account.');

                return Command::SUCCESS;
            }

            $this->info("Found {$domains->count()} domain(s) in Synergy Wholesale account.");
            $this->newLine();

            if ($this->option('dry-run')) {
                $this->warn('DRY RUN MODE - No domains will be imported');
                $this->newLine();
            }

            $bar = $this->output->createProgressBar($domains->count());
            $bar->start();

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($domains as $domainData) {
                // Skip domains with errors
                if (isset($domainData->status) && $domainData->status !== 'OK') {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                if (! isset($domainData->domainName)) {
                    $skipped++;
                    $bar->advance();

                    continue;
                }

                try {
                    $domain = Domain::firstOrNew(['domain' => $domainData->domainName]);

                    $isNew = ! $domain->exists;

                    // Update domain information
                    $updateData = $this->mapDomainData($domainData);

                    if (! $this->option('dry-run')) {
                        $domain->fill($updateData);
                        $domain->save();
                    }

                    if ($isNew) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Error importing domain', [
                        'domain' => $domainData->domainName ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);

            if ($this->option('dry-run')) {
                $this->info('DRY RUN Results:');
            } else {
                $this->info('Import Results:');
            }
            $this->line("  Imported: {$imported}");
            $this->line("  Updated: {$updated}");
            $this->line("  Skipped: {$skipped}");
            if ($errors > 0) {
                $this->error("  Errors: {$errors}");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to import domains: {$e->getMessage()}");
            Log::error('Bulk import failed', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }
    }

    /**
     * Map Synergy Wholesale domain data to our domain model fields
     *
     * @param  object{domainName?: string, domain_expiry?: string, createdDate?: string, domain_status?: string, autoRenew?: string|int|bool, nameServers?: array<int, string>, dnsConfigName?: string, auRegistrantName?: string, auRegistrantIDType?: string, auRegistrantID?: string, auEligibilityType?: string, au_valid_eligibility?: int|bool, auValidEligibility?: int|bool, auEligibilityLastCheck?: string, au_eligibility_last_check?: string}  $domainData
     * @return array<string, mixed>
     */
    private function mapDomainData($domainData): array
    {
        $data = [
            'domain' => $domainData->domainName,
            'registrar' => 'Synergy Wholesale',
            'is_active' => true,
        ];

        // Expiry date
        if (isset($domainData->domain_expiry)) {
            try {
                $data['expires_at'] = new \DateTimeImmutable($domainData->domain_expiry);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        // Created date
        if (isset($domainData->createdDate)) {
            try {
                $data['created_at_synergy'] = new \DateTimeImmutable($domainData->createdDate);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        // Status & renewal
        if (isset($domainData->domain_status)) {
            $data['domain_status'] = $domainData->domain_status;
        }
        if (isset($domainData->autoRenew)) {
            $data['auto_renew'] = is_bool($domainData->autoRenew) ? $domainData->autoRenew : (strtolower($domainData->autoRenew) === 'on' || $domainData->autoRenew == 1);
        }

        // DNS & Nameservers
        if (isset($domainData->nameServers) && is_array($domainData->nameServers)) {
            $data['nameservers'] = $domainData->nameServers;
        }
        if (isset($domainData->dnsConfigName)) {
            $data['dns_config_name'] = $domainData->dnsConfigName;
        }

        // Registrant information
        if (isset($domainData->auRegistrantName)) {
            $data['registrant_name'] = $domainData->auRegistrantName;
        }
        if (isset($domainData->auRegistrantIDType)) {
            $data['registrant_id_type'] = $domainData->auRegistrantIDType;
        }
        if (isset($domainData->auRegistrantID)) {
            $data['registrant_id'] = $domainData->auRegistrantID;
        }

        // Eligibility information
        if (isset($domainData->auEligibilityType)) {
            $data['eligibility_type'] = $domainData->auEligibilityType;
        }
        if (isset($domainData->au_valid_eligibility)) {
            $data['eligibility_valid'] = (bool) $domainData->au_valid_eligibility;
        } elseif (isset($domainData->auValidEligibility)) {
            $data['eligibility_valid'] = (bool) $domainData->auValidEligibility;
        }
        if (isset($domainData->auEligibilityLastCheck)) {
            try {
                $data['eligibility_last_check'] = new \DateTimeImmutable($domainData->auEligibilityLastCheck);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        } elseif (isset($domainData->au_eligibility_last_check)) {
            try {
                $data['eligibility_last_check'] = new \DateTimeImmutable($domainData->au_eligibility_last_check);
            } catch (\Exception $e) {
                // Invalid date, skip
            }
        }

        return $data;
    }
}
