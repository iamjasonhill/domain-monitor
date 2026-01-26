<?php

namespace App\Jobs;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\SynergyWholesaleClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncDnsRecordsJob implements ShouldQueue
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
        return 'sync-dns-records-'.$this->domainId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('SyncDnsRecordsJob: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
            Log::debug('SyncDnsRecordsJob: Domain is not Australian TLD, skipping', [
                'domain' => $domain->domain,
            ]);

            return;
        }

        $credential = SynergyCredential::where('is_active', true)->first();

        if (! $credential) {
            Log::warning('SyncDnsRecordsJob: No active Synergy credentials found', [
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

            /** @var array<int, array{host?: string, type?: string, value?: string, ttl?: int|null, priority?: int|null, id?: string|null}>|null $dnsRecords */
            $dnsRecords = $client->getDnsRecords($domain->domain);

            if (empty($dnsRecords)) {
                Log::warning('SyncDnsRecordsJob: Could not retrieve DNS records', [
                    'domain' => $domain->domain,
                ]);

                return;
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

            Log::info('SyncDnsRecordsJob: DNS records synced successfully', [
                'domain' => $domain->domain,
                'records_count' => $inserted,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncDnsRecordsJob: Failed to sync DNS records', [
                'domain' => $domain->domain,
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
