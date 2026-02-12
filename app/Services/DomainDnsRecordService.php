<?php

namespace App\Services;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use Illuminate\Support\Facades\DB;

class DomainDnsRecordService
{
    /**
     * @param  array{host: string, type: string, value: string, ttl: int, priority: int|null}  $recordData
     * @return array{ok: bool, message?: string, error?: string, error_field?: string}
     */
    public function saveRecord(Domain $domain, array $recordData, ?string $editingDnsRecordId = null): array
    {
        if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
            return [
                'ok' => false,
                'error' => 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records.',
                'error_field' => 'dnsRecordHost',
            ];
        }

        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            return [
                'ok' => false,
                'error' => 'No active domain registrar credentials found. Please configure Synergy Wholesale credentials in Settings.',
                'error_field' => 'dnsRecordHost',
            ];
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        if ($editingDnsRecordId) {
            $record = $this->findDomainRecord($domain, $editingDnsRecordId);
            if (! $record || ! $record->record_id) {
                return [
                    'ok' => false,
                    'error' => 'DNS record not found or cannot be updated.',
                    'error_field' => 'dnsRecordValue',
                ];
            }

            $result = $client->updateDnsRecord(
                $domain->domain,
                $record->record_id,
                $recordData['host'],
                $recordData['type'],
                $recordData['value'],
                $recordData['ttl'],
                $recordData['priority']
            );

            if (! $result || $result['status'] !== 'OK') {
                return [
                    'ok' => false,
                    'error' => $result['error_message'] ?? 'Failed to update DNS record.',
                    'error_field' => 'dnsRecordValue',
                ];
            }

            DB::transaction(function () use ($record, $recordData): void {
                $record->update([
                    'host' => $recordData['host'],
                    'type' => strtoupper($recordData['type']),
                    'value' => $recordData['value'],
                    'ttl' => $recordData['ttl'],
                    'priority' => $recordData['priority'],
                ]);
            });

            return [
                'ok' => true,
                'message' => 'DNS record updated successfully!',
            ];
        }

        $result = $client->addDnsRecord(
            $domain->domain,
            $recordData['host'],
            $recordData['type'],
            $recordData['value'],
            $recordData['ttl'],
            $recordData['priority']
        );

        if (! $result || $result['status'] !== 'OK' || empty($result['record_id'])) {
            return [
                'ok' => false,
                'error' => $result['error_message'] ?? 'Failed to add DNS record. Please check the values and try again.',
                'error_field' => 'dnsRecordValue',
            ];
        }

        DB::transaction(function () use ($domain, $recordData, $result): void {
            DnsRecord::create([
                'domain_id' => $domain->id,
                'host' => $recordData['host'],
                'type' => strtoupper($recordData['type']),
                'value' => $recordData['value'],
                'ttl' => $recordData['ttl'],
                'priority' => $recordData['priority'],
                'record_id' => $result['record_id'],
                'synced_at' => now(),
            ]);
        });

        return [
            'ok' => true,
            'message' => 'DNS record added successfully!',
        ];
    }

    /**
     * @return array{ok: bool, message?: string, error?: string}
     */
    public function deleteRecord(Domain $domain, string $recordId): array
    {
        if (! SynergyWholesaleClient::isAustralianTld($domain->domain)) {
            return [
                'ok' => false,
                'error' => 'Only Australian TLD domains (.com.au, .net.au, etc.) can manage DNS records.',
            ];
        }

        $record = $this->findDomainRecord($domain, $recordId);
        if (! $record || ! $record->record_id) {
            return [
                'ok' => false,
                'error' => 'DNS record not found or cannot be deleted.',
            ];
        }

        $credential = SynergyCredential::where('is_active', true)->first();
        if (! $credential) {
            return [
                'ok' => false,
                'error' => 'No active domain registrar credentials found.',
            ];
        }

        $client = SynergyWholesaleClient::fromEncryptedCredentials(
            $credential->reseller_id,
            $credential->api_key_encrypted,
            $credential->api_url
        );

        $result = $client->deleteDnsRecord($domain->domain, $record->record_id);

        if (! $result || $result['status'] !== 'OK') {
            return [
                'ok' => false,
                'error' => $result['error_message'] ?? 'Failed to delete DNS record.',
            ];
        }

        DB::transaction(function () use ($record): void {
            $record->delete();
        });

        return [
            'ok' => true,
            'message' => 'DNS record deleted successfully!',
        ];
    }

    public function findDomainRecord(Domain $domain, string $recordId): ?DnsRecord
    {
        return DnsRecord::where('id', $recordId)
            ->where('domain_id', $domain->id)
            ->first();
    }
}
