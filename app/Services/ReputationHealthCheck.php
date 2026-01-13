<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class ReputationHealthCheck
{
    /**
     * Perform Reputation health check (Google Safe Browsing & DNSBL)
     *
     * @return array{
     *     is_valid: bool,
     *     google_safe_browsing: array{safe: bool, matches: array<mixed>, error: string|null},
     *     dnsbl: array{spamhaus: array{listed: bool, details: string|null, error: string|null}},
     *     error_message: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function check(string $domain): array
    {
        $startTime = microtime(true);

        try {
            // Google Safe Browsing Check
            $gsbResult = $this->checkGoogleSafeBrowsing($domain);

            // DNSBL Check
            $dnsblResult = $this->checkDnsbl($domain);

            $isValid = $gsbResult['safe'] && ! $dnsblResult['spamhaus']['listed'];
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            $errorMessages = [];
            if (! $gsbResult['safe']) {
                $errorMessages[] = 'Detected by Google Safe Browsing';
            }
            if ($dnsblResult['spamhaus']['listed']) {
                $errorMessages[] = 'Listed on Spamhaus Blocklist';
            }

            return [
                'is_valid' => $isValid,
                'google_safe_browsing' => $gsbResult,
                'dnsbl' => $dnsblResult,
                'error_message' => empty($errorMessages) ? null : implode(', ', $errorMessages),
                'payload' => [
                    'domain' => $domain,
                    'google_safe_browsing' => $gsbResult,
                    'dnsbl' => $dnsblResult,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'google_safe_browsing' => ['safe' => true, 'matches' => [], 'error' => 'Check failed'],
                'dnsbl' => ['spamhaus' => ['listed' => false, 'details' => null, 'error' => 'Check failed']],
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
     * @return array{safe: bool, matches: array<mixed>, error: string|null}
     */
    private function checkGoogleSafeBrowsing(string $domain): array
    {
        $apiKey = config('services.google.safe_browsing_key');

        if (empty($apiKey)) {
            return [
                'safe' => true, // Fail open if no key
                'matches' => [],
                'error' => 'API Key missing',
            ];
        }

        try {
            $url = 'https://safebrowsing.googleapis.com/v4/threatMatches:find?key='.$apiKey;

            $payload = [
                'client' => [
                    'clientId' => 'domain-monitor',
                    'clientVersion' => '1.0.0',
                ],
                'threatInfo' => [
                    'threatTypes' => ['MALWARE', 'SOCIAL_ENGINEERING', 'UNWANTED_SOFTWARE', 'POTENTIALLY_HARMFUL_APPLICATION'],
                    'platformTypes' => ['ANY_PLATFORM'],
                    'threatEntryTypes' => ['URL'],
                    'threatEntries' => [
                        ['url' => $domain],
                    ],
                ],
            ];

            $response = Http::post($url, $payload);
            /** @var \Illuminate\Http\Client\Response $response */
            if ($response->failed()) {
                return [
                    'safe' => true,
                    'matches' => [],
                    'error' => 'API Request Failed: '.$response->status(),
                ];
            }

            $data = $response->json();
            /** @var array<array-key, mixed> $data */
            $matches = $data['matches'] ?? [];

            return [
                'safe' => empty($matches),
                'matches' => is_array($matches) ? $matches : [],
                'error' => null,
            ];
        } catch (Exception $e) {
            return [
                'safe' => true,
                'matches' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array{spamhaus: array{listed: bool, details: string|null, error: string|null}}
     */
    private function checkDnsbl(string $domain): array
    {
        try {
            // Get IP of domain
            $ip = $this->resolveIp($domain);

            // If domain doesn't resolve or resolves to itself (failure)
            if ($ip === $domain) {
                return [
                    'spamhaus' => [
                        'listed' => false,
                        'details' => null,
                        'error' => 'Could not resolve domain IP',
                    ],
                ];
            }

            // Reverse IP for DNSBL lookup
            $reversedIp = implode('.', array_reverse(explode('.', $ip)));
            $lookupHost = $reversedIp.'.zen.spamhaus.org';

            // Perform lookup
            $records = $this->resolveDns($lookupHost);

            if ($records === false) {
                // Not listed
                return [
                    'spamhaus' => [
                        'listed' => false,
                        'details' => null,
                        'error' => null,
                    ],
                ];
            }

            // If we get records, it means it is listed.
            // Spamhaus return codes: 127.0.0.2 (SBL), 127.0.0.3 (SBL CSS), 127.0.0.4 (XBL), 127.0.0.10/11 (PBL)
            $details = [];
            foreach ($records as $r) {
                $code = substr($r, strrpos($r, '.') + 1);
                switch ($code) {
                    case '2': $details[] = 'SBL (Spam Source)';
                        break;
                    case '3': $details[] = 'CSS (Spam Source - Snowshoe)';
                        break;
                    case '4': $details[] = 'XBL (Exploits/Proxy)';
                        break;
                    case '10':
                    case '11': $details[] = 'PBL (Policy/End-user IP)';
                        break;
                    default: $details[] = "Listed (Code $code)";
                        break;
                }
            }

            return [
                'spamhaus' => [
                    'listed' => true,
                    'details' => implode(', ', array_unique($details)),
                    'error' => null,
                ],
            ];
        } catch (Exception $e) {
            return [
                'spamhaus' => [
                    'listed' => false,
                    'details' => null,
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Resolve domain to IP (wrapper for gethostbyname)
     */
    protected function resolveIp(string $domain): string
    {
        return gethostbyname($domain);
    }

    /**
     * Resolve DNS records (wrapper for gethostbynamel)
     *
     * @return array<string>|false
     */
    protected function resolveDns(string $host)
    {
        return gethostbynamel($host);
    }
}
