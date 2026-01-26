<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\DomainContact;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SyncDomainContactsJob implements ShouldQueue
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
     * Create a new job instance.
     */
    public function __construct(public string $domainId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('SyncDomainContactsJob: Domain not found', ['domain_id' => $this->domainId]);

            return;
        }

        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            Log::warning('SyncDomainContactsJob: No active Synergy credentials found', ['domain_id' => $this->domainId]);

            return;
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        Log::info('SyncDomainContactsJob: Syncing contacts', ['domain' => $domain->domain]);

        try {
            $contacts = $client->getDomainContacts($domain->domain);

            if ($contacts === null) {
                Log::warning('SyncDomainContactsJob: Could not retrieve contacts', [
                    'domain' => $domain->domain,
                ]);

                return;
            }

            if ($contacts['status'] !== 'OK') {
                Log::warning('SyncDomainContactsJob: API returned error', [
                    'domain' => $domain->domain,
                    'status' => $contacts['status'],
                    'error_message' => $contacts['error_message'] ?? null,
                ]);

                return;
            }

            $syncedAt = now();

            // Sync each contact type
            $contactTypes = ['registrant', 'admin', 'tech', 'billing'];

            foreach ($contactTypes as $type) {
                $contactData = $contacts[$type] ?? null;

                if (! $contactData) {
                    continue;
                }

                // Create or update contact record
                DomainContact::updateOrCreate(
                    [
                        'domain_id' => $domain->id,
                        'contact_type' => $type,
                        'synced_at' => $syncedAt,
                    ],
                    [
                        'name' => $contactData['name'] ?? null,
                        'email_encrypted' => isset($contactData['email']) ? Crypt::encryptString($contactData['email']) : null,
                        'phone_encrypted' => isset($contactData['phone']) ? Crypt::encryptString($contactData['phone']) : null,
                        'organization' => $contactData['organization'] ?? null,
                        'address_encrypted' => isset($contactData['address']) ? Crypt::encryptString($contactData['address']) : null,
                        'city' => $contactData['city'] ?? null,
                        'state' => $contactData['state'] ?? null,
                        'postal_code' => $contactData['postal_code'] ?? null,
                        'country' => $contactData['country'] ?? null,
                        'raw_data' => $contactData,
                    ]
                );
            }

            Log::info('SyncDomainContactsJob: Contacts synced successfully', [
                'domain' => $domain->domain,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncDomainContactsJob: Failed to sync contacts', [
                'domain' => $domain->domain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
