<?php

namespace App\Services;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\DomainCheck;
use Illuminate\Support\Carbon;

class MailPlaneDnsSummaryBuilder
{
    /**
     * @return array{
     *   enabled: bool,
     *   plane_type: string|null,
     *   provider: string|null,
     *   status: string,
     *   checked_at: string|null,
     *   latest_dns_synced_at: string|null,
     *   latest_email_security_checked_at: string|null,
     *   provider_verification: array<string, mixed>,
     *   counts: array{total:int, required:int, verified:int, missing:int, drifted:int, optional_missing:int},
     *   records: array<int, array<string, mixed>>,
     *   next_actions: array<int, string>
     * }
     */
    public function build(Domain $domain): array
    {
        $requirements = $this->requirements($domain);
        $dnsRecords = $domain->relationLoaded('dnsRecords') ? $domain->dnsRecords : $domain->dnsRecords()->get();
        $latestEmailSecurityCheck = $domain->relationLoaded('latestEmailSecurityCheck')
            ? $domain->latestEmailSecurityCheck
            : $domain->latestEmailSecurityCheck()->first();

        $recordSummaries = collect($requirements)
            ->map(fn (array $requirement): array => $this->recordSummary($domain, $dnsRecords, $requirement))
            ->values()
            ->all();

        $counts = $this->counts($recordSummaries);
        $status = $this->status($domain, $recordSummaries, $counts);
        $latestDnsSyncedAt = $this->latestDnsSyncedAt($dnsRecords);
        $latestEmailSecurityCheckedAt = $latestEmailSecurityCheck instanceof DomainCheck
            ? $latestEmailSecurityCheck->finished_at
            : null;
        $checkedAt = $this->latestTimestamp($latestDnsSyncedAt, $latestEmailSecurityCheckedAt);

        return [
            'enabled' => $domain->isMailPlane(),
            'plane_type' => $domain->mail_plane_type,
            'provider' => $domain->mail_provider,
            'status' => $status,
            'checked_at' => $checkedAt?->toIso8601String(),
            'latest_dns_synced_at' => $latestDnsSyncedAt?->toIso8601String(),
            'latest_email_security_checked_at' => $latestEmailSecurityCheckedAt?->toIso8601String(),
            'provider_verification' => $this->providerVerification($domain),
            'counts' => $counts,
            'records' => $recordSummaries,
            'next_actions' => $this->nextActions($recordSummaries),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function requirements(Domain $domain): array
    {
        $requirements = $domain->mail_dns_requirements;

        if (! is_array($requirements)) {
            return [];
        }

        return collect($requirements)
            ->map(function (array $item): array {
                return [
                    'purpose' => $this->stringValue($item['purpose'] ?? null, 'provider_verification'),
                    'host' => $this->stringValue($item['host'] ?? null, '@'),
                    'type' => strtoupper($this->stringValue($item['type'] ?? null, 'TXT')),
                    'value' => $this->stringValue($item['value'] ?? null, ''),
                    'priority' => isset($item['priority']) ? (int) $item['priority'] : null,
                    'required' => (bool) ($item['required'] ?? true),
                    'description' => $this->nullableString($item['description'] ?? null),
                ];
            })
            ->filter(fn (array $item): bool => $item['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DnsRecord>  $dnsRecords
     * @param  array<string, mixed>  $requirement
     * @return array<string, mixed>
     */
    private function recordSummary(Domain $domain, $dnsRecords, array $requirement): array
    {
        $hostAliases = $this->hostAliases((string) $requirement['host'], $domain->domain);
        $type = (string) $requirement['type'];
        $candidateRecords = $dnsRecords
            ->filter(fn (DnsRecord $record): bool => $this->recordMatchesHostAndType($record, $hostAliases, $type))
            ->values();

        $matchedRecord = $candidateRecords->first(function (DnsRecord $record) use ($requirement): bool {
            return $this->recordValueMatches($record, $requirement);
        });

        $status = match (true) {
            $matchedRecord instanceof DnsRecord => 'ok',
            $candidateRecords->isNotEmpty() => 'drifted',
            default => 'missing',
        };

        return [
            'purpose' => $requirement['purpose'],
            'host' => $requirement['host'],
            'fqdn' => $this->recordFqdn((string) $requirement['host'], $domain->domain),
            'type' => $type,
            'value' => $requirement['value'],
            'priority' => $requirement['priority'],
            'required' => $requirement['required'],
            'description' => $requirement['description'],
            'status' => $status,
            'matched_record_id' => $matchedRecord instanceof DnsRecord ? $matchedRecord->id : null,
            'candidate_record_count' => $candidateRecords->count(),
            'copy_paste' => $this->copyPasteInstruction($domain, $requirement),
            'action' => $this->recordAction($status, $requirement),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $recordSummaries
     * @return array{total:int, required:int, verified:int, missing:int, drifted:int, optional_missing:int}
     */
    private function counts(array $recordSummaries): array
    {
        $counts = [
            'total' => count($recordSummaries),
            'required' => 0,
            'verified' => 0,
            'missing' => 0,
            'drifted' => 0,
            'optional_missing' => 0,
        ];

        foreach ($recordSummaries as $record) {
            $required = (bool) ($record['required'] ?? true);
            $status = (string) ($record['status'] ?? 'missing');

            if ($required) {
                $counts['required']++;
            }

            if ($status === 'ok') {
                $counts['verified']++;
            }

            if ($required && $status === 'missing') {
                $counts['missing']++;
            }

            if ($required && $status === 'drifted') {
                $counts['drifted']++;
            }

            if (! $required && $status !== 'ok') {
                $counts['optional_missing']++;
            }
        }

        return $counts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $recordSummaries
     * @param  array{total:int, required:int, verified:int, missing:int, drifted:int, optional_missing:int}  $counts
     */
    private function status(Domain $domain, array $recordSummaries, array $counts): string
    {
        if (! $domain->isMailPlane()) {
            return 'not_applicable';
        }

        if ($recordSummaries === []) {
            return 'unknown';
        }

        if ($counts['missing'] > 0 || $counts['drifted'] > 0) {
            return 'fail';
        }

        if ($counts['optional_missing'] > 0) {
            return 'warn';
        }

        return 'ok';
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DnsRecord>  $dnsRecords
     */
    private function latestDnsSyncedAt($dnsRecords): ?Carbon
    {
        return $dnsRecords
            ->map(fn (DnsRecord $record): ?Carbon => $record->synced_at)
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function latestTimestamp(?Carbon $first, ?Carbon $second): ?Carbon
    {
        if ($first === null) {
            return $second;
        }

        if ($second === null) {
            return $first;
        }

        return $first->greaterThan($second) ? $first : $second;
    }

    /**
     * @return array<string, mixed>
     */
    private function providerVerification(Domain $domain): array
    {
        $verification = $domain->mail_provider_verification;

        if (! is_array($verification)) {
            return [
                'status' => 'unknown',
                'checked_at' => null,
                'external_id' => null,
                'notes' => null,
            ];
        }

        return [
            'status' => $this->stringValue($verification['status'] ?? null, 'unknown'),
            'checked_at' => $this->nullableString($verification['checked_at'] ?? null),
            'external_id' => $this->nullableString($verification['external_id'] ?? null),
            'notes' => $this->nullableString($verification['notes'] ?? null),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $recordSummaries
     * @return array<int, string>
     */
    private function nextActions(array $recordSummaries): array
    {
        return collect($recordSummaries)
            ->filter(fn (array $record): bool => in_array($record['status'] ?? null, ['missing', 'drifted'], true))
            ->map(fn (array $record): string => (string) $record['action'])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function hostAliases(string $host, string $domain): array
    {
        $normalizedHost = $this->normalizeHost($host);
        $normalizedDomain = $this->normalizeHost($domain);

        if ($normalizedHost === '@' || $normalizedHost === '' || $normalizedHost === $normalizedDomain) {
            return array_values(array_unique(['@', '', $normalizedDomain]));
        }

        if (str_contains($normalizedHost, '.')) {
            $aliases = [$normalizedHost];

            if (! str_ends_with($normalizedHost, '.'.$normalizedDomain)) {
                $aliases[] = "{$normalizedHost}.{$normalizedDomain}";
            }

            return array_values(array_unique($aliases));
        }

        return array_values(array_unique([$normalizedHost, "{$normalizedHost}.{$normalizedDomain}"]));
    }

    private function recordFqdn(string $host, string $domain): string
    {
        $normalizedHost = $this->normalizeHost($host);
        $normalizedDomain = $this->normalizeHost($domain);

        if ($normalizedHost === '@' || $normalizedHost === '' || $normalizedHost === $normalizedDomain) {
            return $normalizedDomain;
        }

        if (str_contains($normalizedHost, '.') && str_ends_with($normalizedHost, '.'.$normalizedDomain)) {
            return $normalizedHost;
        }

        return "{$normalizedHost}.{$normalizedDomain}";
    }

    /**
     * @param  array<int, string>  $hostAliases
     */
    private function recordMatchesHostAndType(DnsRecord $record, array $hostAliases, string $type): bool
    {
        return in_array($this->normalizeHost($record->host), $hostAliases, true)
            && strtoupper($record->type) === $type;
    }

    /**
     * @param  array<string, mixed>  $requirement
     */
    private function recordValueMatches(DnsRecord $record, array $requirement): bool
    {
        if (($requirement['priority'] ?? null) !== null && $record->priority !== (int) $requirement['priority']) {
            return false;
        }

        return $this->normalizeValue($record->value) === $this->normalizeValue((string) $requirement['value']);
    }

    /**
     * @param  array<string, mixed>  $requirement
     */
    private function copyPasteInstruction(Domain $domain, array $requirement): string
    {
        $parts = [
            'Host: '.$this->recordFqdn((string) $requirement['host'], $domain->domain),
            'Type: '.$requirement['type'],
            'Value: '.$requirement['value'],
        ];

        if (($requirement['priority'] ?? null) !== null) {
            $parts[] = 'Priority: '.$requirement['priority'];
        }

        return implode(' | ', $parts);
    }

    /**
     * @param  array<string, mixed>  $requirement
     */
    private function recordAction(string $status, array $requirement): string
    {
        if ($status === 'ok') {
            return 'No action required.';
        }

        $verb = $status === 'drifted' ? 'Update' : 'Add';

        return $verb.' '.$requirement['type'].' record for '.$requirement['purpose'].': '.$requirement['value'];
    }

    private function normalizeHost(string $value): string
    {
        return rtrim(strtolower(trim($value)), '.');
    }

    private function normalizeValue(string $value): string
    {
        return rtrim(trim(strtolower($value), " \t\n\r\0\x0B\"'"), '.');
    }

    private function stringValue(mixed $value, string $default): string
    {
        if (! is_string($value) || trim($value) === '') {
            return $default;
        }

        return trim($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
