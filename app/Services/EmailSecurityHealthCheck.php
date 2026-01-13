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
     * Perform Email Security health check (SPF, DMARC)
     *
     * @return array{
     *     is_valid: bool,
     *     spf: array{present: bool, valid: bool, record: string|null, mechanism: string|null, error: string|null},
     *     dmarc: array{present: bool, valid: bool, record: string|null, policy: string|null, error: string|null},
     *     error_message: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function check(string $domain): array
    {
        $startTime = microtime(true);

        try {
            // SPF Check
            $spfResult = $this->checkSpf($domain);

            // DMARC Check
            $dmarcResult = $this->checkDmarc($domain);

            $isValid = $spfResult['valid'] && $dmarcResult['valid'];
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'spf' => $spfResult,
                'dmarc' => $dmarcResult,
                'error_message' => $isValid ? null : $this->buildErrorMessage($spfResult, $dmarcResult),
                'payload' => [
                    'domain' => $domain,
                    'spf' => $spfResult,
                    'dmarc' => $dmarcResult,
                    'dkim' => ['status' => 'skipped', 'reason' => 'Selector required'], // Placeholder
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'spf' => ['present' => false, 'valid' => false, 'record' => null, 'mechanism' => null, 'error' => 'Check failed'],
                'dmarc' => ['present' => false, 'valid' => false, 'record' => null, 'policy' => null, 'error' => 'Check failed'],
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
     * @return array{present: bool, valid: bool, record: string|null, mechanism: string|null, error: string|null}
     */
    private function checkSpf(string $domain): array
    {
        try {
            $records = $this->dns->getRecords($domain, 'TXT');
            $spfRecords = array_filter($records, fn ($r) => str_starts_with($r->txt(), 'v=spf1'));

            if (empty($spfRecords)) {
                return [
                    'present' => false,
                    'valid' => false,
                    'record' => null,
                    'mechanism' => null,
                    'error' => 'No SPF record found',
                ];
            }

            if (count($spfRecords) > 1) {
                return [
                    'present' => true,
                    'valid' => false,
                    'record' => implode(' | ', array_map(fn ($r) => $r->txt(), $spfRecords)),
                    'mechanism' => null,
                    'error' => 'Multiple SPF records found (Invalid)',
                ];
            }

            $record = reset($spfRecords)->txt();

            // Determine mechanism
            $mechanism = 'unknown';
            if (str_contains($record, '-all')) {
                $mechanism = 'hard_fail';
            } elseif (str_contains($record, '~all')) {
                $mechanism = 'soft_fail';
            } elseif (str_contains($record, '?all')) {
                $mechanism = 'neutral';
            } elseif (str_contains($record, '+all')) {
                $mechanism = 'allow_all';
            }

            // Basic syntax check (very loose)
            $valid = true; // Assume valid unless flagged otherwise by strict parser later

            return [
                'present' => true,
                'valid' => $valid,
                'record' => $record,
                'mechanism' => $mechanism,
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'valid' => false,
                'record' => null,
                'mechanism' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{present: bool, valid: bool, record: string|null, policy: string|null, error: string|null}
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
                    'valid' => false, // DMARC is recommended, so missing = invalid/fail for our strict monitor
                    'record' => null,
                    'policy' => null,
                    'error' => 'No DMARC record found',
                ];
            }

            if (count($dmarcRecords) > 1) {
                return [
                    'present' => true,
                    'valid' => false,
                    'record' => implode(' | ', array_map(fn ($r) => $r->txt(), $dmarcRecords)),
                    'policy' => null,
                    'error' => 'Multiple DMARC records found',
                ];
            }

            $record = reset($dmarcRecords)->txt();

            // Extract Policy (p=)
            preg_match('/p=([^;\s]+)/', $record, $matches);
            $policy = $matches[1] ?? 'unknown';

            $valid = in_array($policy, ['reject', 'quarantine', 'none']);

            return [
                'present' => true,
                'valid' => $valid,
                'record' => $record,
                'policy' => $policy,
                'error' => $valid ? null : 'Invalid or missing policy (p=)',
            ];
        } catch (Exception $e) {
            return [
                'present' => false,
                'valid' => false,
                'record' => null,
                'policy' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array{valid: bool, error: string|null}  $spf
     * @param  array{valid: bool, error: string|null}  $dmarc
     */
    private function buildErrorMessage(array $spf, array $dmarc): ?string
    {
        $errors = [];
        if (! $spf['valid']) {
            $errors[] = 'SPF: '.($spf['error'] ?? 'Invalid');
        }
        if (! $dmarc['valid']) {
            $errors[] = 'DMARC: '.($dmarc['error'] ?? 'Invalid');
        }

        return empty($errors) ? null : implode(', ', $errors);
    }
}
