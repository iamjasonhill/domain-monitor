<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SslHealthCheck
{
    /**
     * Perform SSL certificate check for a domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @param  int  $timeout  Timeout in seconds (default 10)
     * @return array{is_valid: bool, expires_at: string|null, days_until_expiry: int|null, issuer: string|null, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain, int $timeout = 10): array
    {
        $domainOnly = $this->extractDomain($domain);
        $startTime = microtime(true);

        try {
            // Get SSL certificate information
            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $socket = @stream_socket_client(
                "ssl://{$domainOnly}:443",
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );

            if (! $socket) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'error_message' => "SSL connection failed: {$errstr} ({$errno})",
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'connection',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if (! $cert) {
                fclose($socket);
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'error_message' => 'Could not retrieve SSL certificate',
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'certificate',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            fclose($socket);

            // Parse certificate information
            $certInfo = openssl_x509_parse($cert);

            if (! $certInfo) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'error_message' => 'Could not parse SSL certificate',
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'parsing',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            $validFrom = $certInfo['validFrom_time_t'] ?? null;
            $validTo = $certInfo['validTo_time_t'] ?? null;
            $issuer = $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? null);
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            if (! $validTo) {
                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => $issuer,
                    'error_message' => 'Could not determine certificate expiry',
                    'payload' => [
                        'domain' => $domainOnly,
                        'issuer' => $issuer,
                        'duration_ms' => $duration,
                    ],
                ];
            }

            $expiresAt = date('c', $validTo);
            $now = time();
            $daysUntilExpiry = (int) floor(($validTo - $now) / 86400);
            $isValid = $validTo > $now && $validFrom <= $now;

            return [
                'is_valid' => $isValid,
                'expires_at' => $expiresAt,
                'days_until_expiry' => $daysUntilExpiry,
                'issuer' => $issuer,
                'error_message' => $isValid ? null : 'Certificate expired or not yet valid',
                'payload' => [
                    'domain' => $domainOnly,
                    'issuer' => $issuer,
                    'valid_from' => $validFrom ? date('c', $validFrom) : null,
                    'valid_to' => $expiresAt,
                    'days_until_expiry' => $daysUntilExpiry,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::warning('SSL health check exception', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'is_valid' => false,
                'expires_at' => null,
                'days_until_expiry' => null,
                'issuer' => null,
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
