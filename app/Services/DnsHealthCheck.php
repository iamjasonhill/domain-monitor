<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class DnsHealthCheck
{
    /**
     * Perform DNS health check for a domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @return array{is_valid: bool, has_a_record: bool, has_aaaa_record: bool, has_mx_record: bool, nameservers: array<int, string>, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain): array
    {
        $domainOnly = $this->extractDomain($domain);
        $startTime = microtime(true);

        try {
            $records = [
                'A' => [],
                'AAAA' => [],
                'MX' => [],
                'NS' => [],
                'CNAME' => [],
            ];

            // Get A records (IPv4)
            $aRecords = @dns_get_record($domainOnly, DNS_A);
            if ($aRecords) {
                $records['A'] = array_map(fn ($r) => $r['ip'] ?? null, $aRecords);
                $records['A'] = array_filter($records['A']);
            }

            // Get AAAA records (IPv6)
            $aaaaRecords = @dns_get_record($domainOnly, DNS_AAAA);
            if ($aaaaRecords) {
                $records['AAAA'] = array_map(fn ($r) => $r['ipv6'] ?? null, $aaaaRecords);
                $records['AAAA'] = array_filter($records['AAAA']);
            }

            // Get MX records
            $mxRecords = @dns_get_record($domainOnly, DNS_MX);
            if ($mxRecords) {
                $records['MX'] = array_map(fn ($r) => [
                    'priority' => $r['pri'] ?? null,
                    'host' => $r['target'] ?? null,
                ], $mxRecords);
                $records['MX'] = array_filter($records['MX'], fn ($r) => $r['host'] !== null);
            }

            // Get NS records (nameservers)
            $nsRecords = @dns_get_record($domainOnly, DNS_NS);
            if ($nsRecords) {
                $records['NS'] = array_map(fn ($r) => $r['target'] ?? null, $nsRecords);
                $records['NS'] = array_filter($records['NS']);
            }

            // Get CNAME records
            $cnameRecords = @dns_get_record($domainOnly, DNS_CNAME);
            if ($cnameRecords) {
                $records['CNAME'] = array_map(fn ($r) => $r['target'] ?? null, $cnameRecords);
                $records['CNAME'] = array_filter($records['CNAME']);
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $hasARecord = ! empty($records['A']);
            $hasAaaaRecord = ! empty($records['AAAA']);
            $hasMxRecord = ! empty($records['MX']);
            $isValid = $hasARecord || $hasAaaaRecord || $hasMxRecord;

            return [
                'is_valid' => $isValid,
                'has_a_record' => $hasARecord,
                'has_aaaa_record' => $hasAaaaRecord,
                'has_mx_record' => $hasMxRecord,
                'nameservers' => array_values($records['NS']),
                'error_message' => $isValid ? null : 'No DNS records found',
                'payload' => [
                    'domain' => $domainOnly,
                    'records' => [
                        'A' => array_values($records['A']),
                        'AAAA' => array_values($records['AAAA']),
                        'MX' => array_values($records['MX']),
                        'NS' => array_values($records['NS']),
                        'CNAME' => array_values($records['CNAME']),
                    ],
                    'duration_ms' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::warning('DNS health check exception', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_valid' => false,
                'has_a_record' => false,
                'has_aaaa_record' => false,
                'has_mx_record' => false,
                'nameservers' => [],
                'error_message' => 'Unexpected error: '.$e->getMessage(),
                'payload' => [
                    'domain' => $domainOnly,
                    'error_type' => 'unknown',
                    'duration_ms' => $duration,
                ],
            ];
        }
    }

    /**
     * Extract domain from URL
     */
    private function extractDomain(string $domain): string
    {
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];

        return $domain;
    }
}
