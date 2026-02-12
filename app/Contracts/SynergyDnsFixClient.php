<?php

namespace App\Contracts;

interface SynergyDnsFixClient
{
    /**
     * @return array<int, array{host: string, type: string, value: string, ttl: int|null, priority?: int|null, id?: string|null}>|null
     */
    public function getDnsRecords(string $domain): ?array;

    /**
     * @return array{status?: string, error_message?: string, record_id?: string}|null
     */
    public function addDnsRecord(
        string $domain,
        string $recordName,
        string $recordType,
        string $recordContent,
        int $recordTTL = 300,
        int $recordPrio = 0
    ): ?array;

    /**
     * @return array{status?: string, error_message?: string}|null
     */
    public function updateDnsRecord(
        string $domain,
        string $recordId,
        string $recordName,
        string $recordType,
        string $recordContent,
        int $recordTTL = 300,
        int $recordPrio = 0
    ): ?array;
}
