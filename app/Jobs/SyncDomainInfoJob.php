<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDomainInfoJob implements ShouldQueue
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
    public $backoff = 60;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $domainId
    ) {
        //
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return 'sync-domain-info-'.$this->domainId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('SyncDomainInfoJob: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
            Log::debug('SyncDomainInfoJob: Domain is not Australian TLD, skipping', [
                'domain' => $domain->domain,
            ]);

            return;
        }

        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            Log::warning('SyncDomainInfoJob: No active Synergy credentials found', [
                'domain' => $domain->domain,
            ]);

            return;
        }

        try {
            $client = SynergyWholesaleClient::fromEncryptedCredentials(
                $credential->reseller_id,
                $credential->api_key_encrypted,
                $credential->api_url
            );

            $domainInfo = $client->getDomainInfo($domain->domain);

            if (! $domainInfo) {
                Log::warning('SyncDomainInfoJob: Could not retrieve domain information', [
                    'domain' => $domain->domain,
                ]);

                return;
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

            // Update domain
            if (! empty($updateData)) {
                $domain->update($updateData);
            }

            Log::info('SyncDomainInfoJob: Domain synced successfully', [
                'domain' => $domain->domain,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncDomainInfoJob: Failed to sync domain', [
                'domain' => $domain->domain,
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
