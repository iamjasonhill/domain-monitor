<?php

namespace App\Services;

use Exception;
use Spatie\Dns\Dns;

class EmailSecurityHealthCheck
{
    public function __construct(protected Dns $dns)
    {
        //
    }

    /**
     * Perform Email & DNS Security health check.
     *
     * Overall baseline status is based on verified SPF and DMARC only.
     * DKIM selector discovery, DNSSEC, and CAA are supporting signals.
     *
     * @param  array<int, string>  $customSelectors
     * @return array<string, mixed>
     */
    public function check(string $domain, array $customSelectors = []): array
    {
        $startTime = microtime(true);

        try {
            // SPF Check
            $spfResult = $this->checkSpf($domain);

            // DMARC Check
            $dmarcResult = $this->checkDmarc($domain);

            // DNSSEC Check
            $dnssecResult = $this->checkDnssec($domain);

            // CAA Check
            $caaResult = $this->checkCaa($domain);

            // DKIM Check (Selector Discovery)
            $dkimResult = $this->checkDkim($domain, $customSelectors);

            $overallStatus = $this->determineOverallStatus($spfResult, $dmarcResult);
            $overallAssessment = $this->buildOverallAssessment($overallStatus, $spfResult, $dmarcResult);
            $isValid = $overallStatus === 'ok';
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'overall_status' => $overallStatus,
                'overall_assessment' => $overallAssessment,
                'methodology_note' => 'Overall email security is based on verified SPF and DMARC. DKIM discovery, DNSSEC, and CAA are supporting signals only.',
                'spf' => $spfResult,
                'dmarc' => $dmarcResult,
                'dnssec' => $dnssecResult,
                'caa' => $caaResult,
                'dkim' => $dkimResult,
                'error_message' => $isValid ? null : $overallAssessment,
                'payload' => [
                    'domain' => $domain,
                    'is_valid' => $isValid,
                    'overall_status' => $overallStatus,
                    'overall_assessment' => $overallAssessment,
                    'methodology_note' => 'Overall email security is based on verified SPF and DMARC. DKIM discovery, DNSSEC, and CAA are supporting signals only.',
                    'spf' => $spfResult,
                    'dmarc' => $dmarcResult,
                    'dnssec' => $dnssecResult,
                    'caa' => $caaResult,
                    'dkim' => $dkimResult,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'overall_status' => 'unknown',
                'overall_assessment' => 'The email security checks could not be completed.',
                'methodology_note' => 'Overall email security is based on verified SPF and DMARC. DKIM discovery, DNSSEC, and CAA are supporting signals only.',
                'spf' => ['present' => false, 'valid' => false, 'verified' => false, 'record' => null, 'mechanism' => null, 'status' => 'unknown', 'assessment' => 'Could not verify SPF.', 'error' => 'Check failed'],
                'dmarc' => ['present' => false, 'valid' => false, 'verified' => false, 'record' => null, 'policy' => null, 'status' => 'unknown', 'assessment' => 'Could not verify DMARC.', 'error' => 'Check failed'],
                'dnssec' => ['enabled' => false, 'verified' => false, 'status' => 'unknown', 'assessment' => 'Could not verify DNSSEC.', 'error' => 'Check failed'],
                'caa' => ['present' => false, 'verified' => false, 'records' => [], 'status' => 'unknown', 'assessment' => 'Could not verify CAA.', 'error' => 'Check failed'],
                'dkim' => ['present' => false, 'verified' => false, 'selectors' => [], 'status' => 'unknown', 'assessment' => 'Could not verify DKIM discovery.', 'discovery_mode' => 'heuristic', 'error' => 'Check failed'],
                'error_message' => 'Exception: '.$e->getMessage(),
                'payload' => [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
                ],
            ];
        }
    }

    /**
     * @param  array<int, string>  $customSelectors
     * @return array<string, mixed>
     */
    private function checkDkim(string $domain, array $customSelectors = []): array
    {
        // Internal default selectors to check (expanded list)
        $defaultSelectors = [
            'google', 'default', 'mail', 'k1', 'smtp', 's1', 's2019', 's2020', '20230601',
            'mandrill', 'sendgrid', 'mailchimp', 'sparkpost', 'postmark', 'amazonses',
            'protonmail', 'office365', 'outlook', 'm1', 'm2', 'dkim', 'dkim1', 'dkim2',
            'dns1', 'dns2', 'zoho', 'fastmail', 'ms', 'key1',
        ];

        // Merge custom selectors with defaults, removing duplicates
        $selectors = array_unique(array_merge($defaultSelectors, $customSelectors));

        $foundSelectors = [];

        try {
            foreach ($selectors as $selector) {
                $host = "{$selector}._domainkey.{$domain}";
                $records = $this->dns->getRecords($host, 'TXT');

                foreach ($records as $record) {
                    $txt = $record->txt();
                    if (str_contains($txt, 'v=DKIM1')) {
                        $foundSelectors[] = [
                            'selector' => $selector,
                            'record' => $txt,
                        ];
                        // Found one for this selector, break inner loop to move to next selector
                        break;
                    }
                }
            }

            return [
                'present' => ! empty($foundSelectors),
                'verified' => true,
                'selectors' => $foundSelectors,
                'status' => ! empty($foundSelectors) ? 'ok' : 'warn',
                'assessment' => ! empty($foundSelectors)
                    ? 'DKIM selectors were discovered in the checked selector list.'
                    : 'No DKIM selector was discovered in the checked selector list. This discovery is heuristic and does not prove DKIM is absent.',
                'discovery_mode' => 'heuristic',
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'verified' => false,
                'selectors' => [],
                'status' => 'unknown',
                'assessment' => 'Could not verify DKIM selector discovery.',
                'discovery_mode' => 'heuristic',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkSpf(string $domain): array
    {
        try {
            $records = $this->dns->getRecords($domain, 'TXT');
            $spfRecords = array_values(array_filter($records, fn ($r) => str_starts_with($r->txt(), 'v=spf1')));

            if (empty($spfRecords)) {
                return [
                    'present' => false,
                    'valid' => false,
                    'verified' => true,
                    'record' => null,
                    'mechanism' => null,
                    'status' => 'fail',
                    'assessment' => 'No SPF record was found for this domain.',
                    'error' => 'No SPF record found',
                ];
            }

            if (count($spfRecords) > 1) {
                return [
                    'present' => true,
                    'valid' => false,
                    'verified' => true,
                    'record' => implode(' | ', array_map(fn ($r) => $r->txt(), $spfRecords)),
                    'mechanism' => null,
                    'status' => 'fail',
                    'assessment' => 'Multiple SPF records were found. SPF must exist as a single consolidated TXT record.',
                    'error' => 'Multiple SPF records found (Invalid)',
                ];
            }

            $record = $spfRecords[0]->txt();
            $mechanism = $this->detectSpfMechanism($record);

            $status = 'fail';
            $valid = false;
            $assessment = 'The SPF record does not end with a recognized enforcement mechanism.';
            $error = 'SPF record does not end with a recognized all mechanism.';

            if ($mechanism === 'hard_fail') {
                $status = 'ok';
                $valid = true;
                $assessment = 'A single SPF record was found and it ends with -all, which meets the current baseline.';
                $error = null;
            } elseif ($mechanism === 'soft_fail') {
                $status = 'warn';
                $assessment = 'A single SPF record was found, but it ends with ~all. That is softer than the current baseline and should be tightened once all legitimate senders are confirmed.';
                $error = 'SPF ends with ~all rather than -all.';
            } elseif ($mechanism === 'neutral') {
                $assessment = 'The SPF record ends with ?all, which does not meaningfully restrict unauthorized senders.';
                $error = 'SPF ends with ?all, which is too weak.';
            } elseif ($mechanism === 'allow_all') {
                $assessment = 'The SPF record ends with +all, which effectively allows any sender and is unsafe.';
                $error = 'SPF ends with +all, which is unsafe.';
            }

            return [
                'present' => true,
                'valid' => $valid,
                'verified' => true,
                'record' => $record,
                'mechanism' => $mechanism,
                'status' => $status,
                'assessment' => $assessment,
                'error' => $error,
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'valid' => false,
                'verified' => false,
                'record' => null,
                'mechanism' => null,
                'status' => 'unknown',
                'assessment' => 'Could not verify SPF.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDmarc(string $domain): array
    {
        $dmarcDomain = '_dmarc.'.$domain;

        try {
            $records = $this->dns->getRecords($dmarcDomain, 'TXT');
            $dmarcRecords = array_filter($records, fn ($r) => str_starts_with($r->txt(), 'v=DMARC1'));

            if (empty($dmarcRecords)) {
                return [
                    'present' => false,
                    'valid' => false,
                    'verified' => true,
                    'record' => null,
                    'policy' => null,
                    'status' => 'fail',
                    'assessment' => 'No DMARC record was found for this domain.',
                    'error' => 'No DMARC record found',
                ];
            }

            if (count($dmarcRecords) > 1) {
                return [
                    'present' => true,
                    'valid' => false,
                    'verified' => true,
                    'record' => implode(' | ', array_map(fn ($r) => $r->txt(), $dmarcRecords)),
                    'policy' => null,
                    'status' => 'fail',
                    'assessment' => 'Multiple DMARC records were found. DMARC must exist as a single TXT record at _dmarc.',
                    'error' => 'Multiple DMARC records found',
                ];
            }

            $record = reset($dmarcRecords)->txt();

            preg_match('/p=([^;\s]+)/', $record, $matches);
            $policy = $matches[1] ?? 'unknown';
            $status = 'fail';
            $valid = false;
            $assessment = 'The DMARC record is missing a supported p= policy.';
            $error = 'Invalid or missing policy (p=)';

            if (in_array($policy, ['reject', 'quarantine'], true)) {
                $status = 'ok';
                $valid = true;
                $assessment = "The DMARC record uses p={$policy}, which meets the current baseline.";
                $error = null;
            } elseif ($policy === 'none') {
                $status = 'warn';
                $assessment = 'The DMARC record is present, but p=none is monitor-only and does not enforce protection.';
                $error = 'DMARC uses p=none, which is monitor-only.';
            }

            return [
                'present' => true,
                'valid' => $valid,
                'verified' => true,
                'record' => $record,
                'policy' => $policy,
                'status' => $status,
                'assessment' => $assessment,
                'error' => $error,
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'valid' => false,
                'verified' => false,
                'record' => null,
                'policy' => null,
                'status' => 'unknown',
                'assessment' => 'Could not verify DMARC.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDnssec(string $domain): array
    {
        try {
            $records = $this->getDnsKey($domain);

            return [
                'enabled' => ! empty($records),
                'verified' => true,
                'status' => ! empty($records) ? 'ok' : 'warn',
                'assessment' => ! empty($records)
                    ? 'DNSSEC appears to be enabled.'
                    : 'DNSSEC does not appear to be enabled. This is advisory and handled at the registrar or DNS host level.',
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'enabled' => false,
                'verified' => false,
                'status' => 'unknown',
                'assessment' => 'Could not verify DNSSEC.',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch DNSKEY records using native PHP function
     *
     * @return array<int, mixed>
     */
    public function getDnsKey(string $domain): array
    {
        // DNS_DNSKEY constant might not be defined depending on PHP build
        $type = defined('DNS_DNSKEY') ? DNS_DNSKEY : 48;

        return @dns_get_record($domain, $type) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkCaa(string $domain): array
    {
        try {
            $records = $this->dns->getRecords($domain, 'CAA');

            if (empty($records)) {
                return [
                    'present' => false,
                    'verified' => true,
                    'records' => [],
                    'status' => 'warn',
                    'assessment' => 'No CAA records were found. This is advisory only and should only be changed if you know which certificate authorities you intend to authorize.',
                    'error' => null,
                ];
            }

            return [
                'present' => true,
                'verified' => true,
                'records' => array_map(fn ($r) => (string) $r, $records),
                'status' => 'ok',
                'assessment' => 'CAA records are present.',
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'verified' => false,
                'records' => [],
                'status' => 'unknown',
                'assessment' => 'Could not verify CAA.',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function detectSpfMechanism(string $record): string
    {
        if (preg_match('/(?:^|\s)-all(?:\s|$)/', $record)) {
            return 'hard_fail';
        }
        if (preg_match('/(?:^|\s)~all(?:\s|$)/', $record)) {
            return 'soft_fail';
        }
        if (preg_match('/(?:^|\s)\?all(?:\s|$)/', $record)) {
            return 'neutral';
        }
        if (preg_match('/(?:^|\s)\+all(?:\s|$)/', $record)) {
            return 'allow_all';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $spf
     * @param  array<string, mixed>  $dmarc
     */
    private function determineOverallStatus(array $spf, array $dmarc): string
    {
        if (($spf['verified'] ?? false) !== true || ($dmarc['verified'] ?? false) !== true) {
            return 'unknown';
        }

        $spfStatus = $spf['status'] ?? 'unknown';
        $dmarcStatus = $dmarc['status'] ?? 'unknown';

        if ($spfStatus === 'fail' || $dmarcStatus === 'fail') {
            return 'fail';
        }

        if ($spfStatus === 'warn' || $dmarcStatus === 'warn') {
            return 'warn';
        }

        if ($spfStatus === 'ok' && $dmarcStatus === 'ok') {
            return 'ok';
        }

        return 'unknown';
    }

    /**
     * @param  array<string, mixed>  $spf
     * @param  array<string, mixed>  $dmarc
     */
    private function buildOverallAssessment(string $overallStatus, array $spf, array $dmarc): string
    {
        return match ($overallStatus) {
            'ok' => 'Verified SPF and DMARC meet the current baseline.',
            'warn' => implode(' ', array_filter([
                'Email security needs review before it meets the current baseline.',
                ($spf['status'] ?? null) === 'warn' ? $spf['assessment'] : null,
                ($dmarc['status'] ?? null) === 'warn' ? $dmarc['assessment'] : null,
            ])),
            'fail' => implode(' ', array_filter([
                'Email security is missing required baseline protection.',
                in_array($spf['status'] ?? null, ['fail', 'unknown'], true) ? $spf['assessment'] : null,
                in_array($dmarc['status'] ?? null, ['fail', 'unknown'], true) ? $dmarc['assessment'] : null,
            ])),
            default => 'Could not verify SPF or DMARC, so the email security baseline could not be confirmed.',
        };
    }
}
