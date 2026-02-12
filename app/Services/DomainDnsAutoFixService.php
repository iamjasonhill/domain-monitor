<?php

namespace App\Services;

use App\Contracts\SynergyDnsFixClient;
use App\Models\Domain;
use App\Models\SynergyCredential;
use Illuminate\Support\Facades\Log;
use Spatie\Dns\Dns;

class DomainDnsAutoFixService
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function applyFix(Domain $domain, string $checkType): array
    {
        Log::info('Automated DNS fix initiated', [
            'domain' => $domain->domain,
            'type' => $checkType,
        ]);

        if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
            Log::warning('Automated DNS fix skipped: not eligible', ['domain' => $domain->domain]);

            return [
                'ok' => false,
                'message' => 'Automated fixes are only available for Australian TLD domains.',
            ];
        }

        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            Log::error('Automated DNS fix failed: no credentials');

            return [
                'ok' => false,
                'message' => 'No active Synergy credentials found.',
            ];
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        $liveRecords = $client->getDnsRecords($domain->domain);
        if (! $liveRecords) {
            return [
                'ok' => false,
                'message' => 'Could not fetch current DNS records from Synergy.',
            ];
        }

        $message = '';

        return match ($checkType) {
            'spf' => $this->fixSpf($domain, $client, $liveRecords, $message),
            'dmarc' => $this->fixDmarc($domain, $client, $liveRecords, $message),
            'caa' => $this->fixCaa($domain, $client, $liveRecords, $message),
            default => ['ok' => false, 'message' => "Unknown fix type: {$checkType}"],
        };
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     * @param  string  $message  Output message
     * @return array{ok: bool, message: string}
     */
    private function fixSpf(Domain $domain, SynergyDnsFixClient $client, array $records, string &$message): array
    {
        $existingSpf = null;
        foreach ($records as $record) {
            if ($record['type'] === 'TXT' && ($record['host'] === '@' || $record['host'] === $domain->domain) && str_starts_with($record['value'], 'v=spf1')) {
                $existingSpf = $record;
                break;
            }
        }

        $defaultValue = 'v=spf1 a mx ~all';

        Log::info('Checking for existing SPF record', ['found' => $existingSpf !== null]);

        if ($existingSpf && ! empty($existingSpf['id'])) {
            $result = $client->updateDnsRecord(
                $domain->domain,
                (string) $existingSpf['id'],
                '@',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Updated existing SPF record to safe default.';
        } else {
            $result = $client->addDnsRecord(
                $domain->domain,
                '@',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Created new SPF record.';
        }

        if (isset($result['status']) && $result['status'] === 'OK') {
            return ['ok' => true, 'message' => $message];
        }

        return ['ok' => false, 'message' => $result['error_message'] ?? 'Unknown API error.'];
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     * @param  string  $message  Output message
     * @return array{ok: bool, message: string}
     */
    private function fixDmarc(Domain $domain, SynergyDnsFixClient $client, array $records, string &$message): array
    {
        $existingDmarc = null;
        foreach ($records as $record) {
            if ($record['type'] === 'TXT' && $record['host'] === '_dmarc') {
                $existingDmarc = $record;
                break;
            }
        }

        $defaultValue = 'v=DMARC1; p=none;';

        Log::info('Checking for existing DMARC record', ['found' => $existingDmarc !== null]);

        if ($existingDmarc && ! empty($existingDmarc['id'])) {
            $result = $client->updateDnsRecord(
                $domain->domain,
                (string) $existingDmarc['id'],
                '_dmarc',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Updated existing DMARC record to p=none.';
        } else {
            $result = $client->addDnsRecord(
                $domain->domain,
                '_dmarc',
                'TXT',
                $defaultValue,
                300
            );
            $message = 'Created new DMARC record.';
        }

        if (isset($result['status']) && $result['status'] === 'OK') {
            return ['ok' => true, 'message' => $message];
        }

        return ['ok' => false, 'message' => $result['error_message'] ?? 'Unknown API error.'];
    }

    /**
     * @param  array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>  $records
     * @param  string  $message  Output message
     * @return array{ok: bool, message: string}
     */
    private function fixCaa(Domain $domain, SynergyDnsFixClient $client, array $records, string &$message): array
    {
        $hasCaaInApi = false;
        foreach ($records as $record) {
            if ($record['type'] === 'CAA') {
                $hasCaaInApi = true;
                break;
            }
        }

        $hasCaaInDns = false;
        try {
            $dns = app(Dns::class);
            $caaRecords = $dns->getRecords($domain->domain, 'CAA');
            $hasCaaInDns = ! empty($caaRecords);
        } catch (\Exception $e) {
            Log::warning('CAA DNS lookup failed during fix check', [
                'domain' => $domain->domain,
                'error' => $e->getMessage(),
            ]);

            $message = 'Could not verify CAA records via DNS lookup. Skipping automatic creation to avoid conflicts.';

            return ['ok' => false, 'message' => $message];
        }

        if ($hasCaaInApi || $hasCaaInDns) {
            $message = 'CAA records already exist (verified via API and DNS lookup). Automatic fix skipped to avoid breaking existing authorization.';

            return ['ok' => false, 'message' => $message];
        }

        $result = $client->addDnsRecord(
            $domain->domain,
            '@',
            'CAA',
            '0 issue "letsencrypt.org"',
            300
        );

        if (isset($result['status']) && $result['status'] === 'OK') {
            $message = "Created CAA record for Let's Encrypt.";

            return ['ok' => true, 'message' => $message];
        }

        return ['ok' => false, 'message' => $result['error_message'] ?? 'Unknown API error.'];
    }
}
