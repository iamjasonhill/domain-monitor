<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainEligibilityCheck;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportSynergyDomainsJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 120;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            Log::warning('ImportSynergyDomainsJob: No active Synergy Wholesale credentials found.');

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        Log::info('ImportSynergyDomainsJob: Fetching domains from Synergy Wholesale...');

        try {
            $domains = $client->listDomains();

            if ($domains->isEmpty()) {
                Log::info('ImportSynergyDomainsJob: No domains found in Synergy Wholesale account.');

                return;
            }

            Log::info("ImportSynergyDomainsJob: Found {$domains->count()} domain(s) in Synergy Wholesale account.");

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($domains as $domainData) {
                $domainName = 'unknown';

                // Skip domains with errors
                if (isset($domainData->status) && $domainData->status !== 'OK') {
                    $skipped++;

                    continue;
                }

                if (! isset($domainData->domainName)) {
                    $skipped++;

                    continue;
                }

                try {
                    $domainName = (string) $domainData->domainName;
                    $domain = Domain::firstOrNew(['domain' => $domainData->domainName]);

                    $isNew = ! $domain->exists;

                    // Update domain information
                    /** @var object{domainName: string, domain_expiry?: string, createdDate?: string, domain_status?: string, autoRenew?: string|int|bool, nameServers?: array<int, string>, dnsConfigName?: string, auRegistrantName?: string, auRegistrantIDType?: string, auRegistrantID?: string, auEligibilityType?: string, au_valid_eligibility?: int|bool, auValidEligibility?: int|bool, auEligibilityLastCheck?: string, au_eligibility_last_check?: string} $domainData */
                    $updateData = $this->mapDomainData($domainData);

                    $domain->fill($updateData);
                    $domain->save();

                    if (array_key_exists('eligibility_valid', $updateData) || array_key_exists('eligibility_last_check', $updateData) || array_key_exists('eligibility_type', $updateData)) {
                        DomainEligibilityCheck::create([
                            'domain_id' => $domain->id,
                            'source' => 'synergy',
                            'eligibility_type' => $updateData['eligibility_type'] ?? $domain->eligibility_type,
                            'is_valid' => $updateData['eligibility_valid'] ?? $domain->eligibility_valid,
                            'checked_at' => $updateData['eligibility_last_check'] ?? now(),
                            'payload' => [
                                'domain_status' => $updateData['domain_status'] ?? $domain->domain_status,
                            ],
                        ]);
                    }

                    if ($isNew) {
                        $imported++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('ImportSynergyDomainsJob: Error importing domain', [
                        'domain' => $domainName,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('ImportSynergyDomainsJob: Import completed', [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('ImportSynergyDomainsJob: Bulk import failed', ['error' => $e->getMessage()]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Map Synergy Wholesale domain data to our domain model fields
     *
     * @param  object{domainName: string, domain_expiry?: string, createdDate?: string, domain_status?: string, autoRenew?: string|int|bool, nameServers?: array<int, string>, dnsConfigName?: string, auRegistrantName?: string, auRegistrantIDType?: string, auRegistrantID?: string, auEligibilityType?: string, au_valid_eligibility?: int|bool, auValidEligibility?: int|bool, auEligibilityLastCheck?: string, au_eligibility_last_check?: string}  $domainData
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
            $autoRenew = $domainData->autoRenew;
            if (is_bool($autoRenew)) {
                $data['auto_renew'] = $autoRenew;
            } elseif (is_int($autoRenew)) {
                $data['auto_renew'] = $autoRenew === 1;
            } else {
                $value = strtolower((string) $autoRenew);
                $data['auto_renew'] = $value === 'on' || $value === '1' || $value === 'true';
            }
        }

        // DNS & Nameservers
        if (isset($domainData->nameServers)) {
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

        // Additional .au compliance fields (from getDomainInfo, not listDomains)
        // These will be populated during sync operations
        // Note: listDomains may not return all fields, so we rely on sync commands

        return $data;
    }
}
