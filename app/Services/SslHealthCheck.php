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
     * @return array{is_valid: bool, expires_at: string|null, days_until_expiry: int|null, issuer: string|null, protocol: string|null, cipher: string|null, chain: array<int, array{subject: string, issuer: string, valid_to: string|null}>, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain, int $timeout = 10): array
    {
        $domainOnly = $this->extractDomain($domain);
        $startTime = microtime(true);

        try {
            // Get SSL certificate information
            $context = $this->createContext([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'capture_peer_cert_chain' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $socket = $this->openSocket(
                "ssl://{$domainOnly}:443",
                $timeout,
                $context
            );

            if (! $socket) {
                $duration = (int) ((microtime(true) - $startTime) * 1000);
                $error = $this->lastSocketError();

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'protocol' => null,
                    'cipher' => null,
                    'chain' => [],
                    'error_message' => $error,
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'connection',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            $params = $this->getContextParams($socket);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;
            $chainData = $params['options']['ssl']['peer_certificate_chain'] ?? [];

            if (! $cert) {
                $this->closeSocket($socket);
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'protocol' => null,
                    'cipher' => null,
                    'chain' => [],
                    'error_message' => 'Could not retrieve SSL certificate',
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'certificate',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            // Parse certificate information
            $certInfo = $this->parseCertificate($cert);

            if (! $certInfo) {
                $this->closeSocket($socket);
                $duration = (int) ((microtime(true) - $startTime) * 1000);

                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => null,
                    'protocol' => null,
                    'cipher' => null,
                    'chain' => [],
                    'error_message' => 'Could not parse SSL certificate',
                    'payload' => [
                        'domain' => $domainOnly,
                        'error_type' => 'parsing',
                        'duration_ms' => $duration,
                    ],
                ];
            }

            // Only extract crypto metadata (Protocol, Cipher) if we have a valid certificate
            $meta = $this->getMetaData($socket);
            $crypto = $meta['crypto'] ?? [];
            $protocol = $crypto['protocol'] ?? null;
            $cipher = $crypto['cipher_name'] ?? null;
            $cipherBits = $crypto['cipher_bits'] ?? 0;
            $cipherString = $cipher ? ($cipherBits > 0 ? "{$cipher} ({$cipherBits} bits)" : $cipher) : null;

            $this->closeSocket($socket);

            $validFrom = $certInfo['validFrom_time_t'] ?? null;
            $validTo = $certInfo['validTo_time_t'] ?? null;
            $issuer = $certInfo['issuer']['CN'] ?? ($certInfo['issuer']['O'] ?? null);

            // Parse Chain
            $chain = [];
            foreach ($chainData as $chainCert) {
                $parsedChain = $this->parseCertificate($chainCert);
                if ($parsedChain) {
                    $chain[] = [
                        'subject' => $parsedChain['subject']['CN'] ?? ($parsedChain['subject']['O'] ?? 'Unknown'),
                        'issuer' => $parsedChain['issuer']['CN'] ?? ($parsedChain['issuer']['O'] ?? 'Unknown'),
                        'valid_to' => isset($parsedChain['validTo_time_t']) ? $this->formatDate('Y-m-d', $parsedChain['validTo_time_t']) : null,
                    ];
                }
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            if (! $validTo) {
                return [
                    'is_valid' => false,
                    'expires_at' => null,
                    'days_until_expiry' => null,
                    'issuer' => $issuer,
                    'protocol' => $protocol,
                    'cipher' => $cipherString,
                    'chain' => $chain,
                    'error_message' => 'Could not determine certificate expiry',
                    'payload' => [
                        'domain' => $domainOnly,
                        'issuer' => $issuer,
                        'duration_ms' => $duration,
                    ],
                ];
            }

            $expiresAt = $this->formatDate('c', $validTo);
            $now = $this->currentTime();
            $daysUntilExpiry = (int) floor(($validTo - $now) / 86400);
            $isValid = $validTo > $now && $validFrom <= $now;

            return [
                'is_valid' => $isValid,
                'expires_at' => $expiresAt,
                'days_until_expiry' => $daysUntilExpiry,
                'issuer' => $issuer,
                'protocol' => $protocol,
                'cipher' => $cipherString,
                'chain' => $chain,
                'error_message' => $isValid ? null : 'Certificate expired or not yet valid',
                'payload' => [
                    'domain' => $domainOnly,
                    'issuer' => $issuer,
                    'protocol' => $protocol,
                    'cipher' => $cipherString,
                    'chain' => $chain,
                    'valid_from' => $validFrom ? $this->formatDate('c', $validFrom) : null,
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
                'protocol' => null,
                'cipher' => null,
                'chain' => [],
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

    /**
     * @param  array<string, mixed>  $options
     * @return resource
     */
    protected function createContext(array $options)
    {
        return stream_context_create($options);
    }

    /**
     * @param  resource  $context
     * @return resource|false
     */
    protected function openSocket(string $address, int $timeout, $context)
    {
        $errno = 0;
        $errstr = '';

        return @stream_socket_client(
            $address,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
    }

    /**
     * @param  resource  $socket
     * @return array<string, mixed>
     */
    protected function getContextParams($socket): array
    {
        return stream_context_get_params($socket);
    }

    /**
     * @param  mixed  $certificate
     * @return array<string, mixed>|false
     */
    protected function parseCertificate($certificate): array|false
    {
        return openssl_x509_parse($certificate);
    }

    /**
     * @param  resource  $socket
     * @return array<string, mixed>
     */
    protected function getMetaData($socket): array
    {
        return stream_get_meta_data($socket);
    }

    /**
     * @param  resource  $socket
     */
    protected function closeSocket($socket): void
    {
        fclose($socket);
    }

    protected function currentTime(): int
    {
        return time();
    }

    protected function formatDate(string $format, int $timestamp): string
    {
        return date($format, $timestamp);
    }

    protected function lastSocketError(): string
    {
        $error = error_get_last();
        $message = is_array($error) ? $error['message'] : null;

        return $message ? 'SSL connection failed: '.$message : 'SSL connection failed';
    }
}
