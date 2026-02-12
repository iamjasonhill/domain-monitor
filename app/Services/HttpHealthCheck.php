<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HttpHealthCheck
{
    /**
     * Perform HTTP health check for a domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @param  int  $timeout  Timeout in seconds (default 10)
     * @return array{status_code: int|null, duration_ms: int, is_up: bool, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain, int $timeout = 10): array
    {
        $url = $this->normalizeUrl($domain);
        $startTime = microtime(true);

        try {
            // Try HTTPS first, but allow fallback to HTTP for parked domains with SSL issues
            try {
                $response = Http::timeout($timeout)
                    ->async(false)
                    ->withoutVerifying() // Allow self-signed certificates
                    ->withHeaders([
                        'User-Agent' => 'DomainMonitor/1.0',
                    ])
                    ->get($url);
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // If HTTPS fails with SSL error, try HTTP
                if (str_contains($e->getMessage(), 'SSL') || str_contains($e->getMessage(), 'TLS')) {
                    $httpUrl = str_replace('https://', 'http://', $url);
                    $response = Http::timeout($timeout)
                        ->async(false)
                        ->withHeaders([
                            'User-Agent' => 'DomainMonitor/1.0',
                        ])
                        ->get($httpUrl);
                } else {
                    throw $e; // Re-throw if it's not an SSL error
                }
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            /** @var \Illuminate\Http\Client\Response $response */
            $statusCode = $response->status();
            $isUp = $response->successful() || $statusCode < 500;

            /** @var array<string, array<int, string>|string> $headers */
            $headers = $response->headers();

            return [
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'is_up' => $isUp,
                'error_message' => $isUp ? null : "HTTP {$statusCode}",
                'payload' => [
                    'url' => $url,
                    'headers' => $this->normalizeHeaders($headers),
                    'redirected' => $response->redirect(),
                ],
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'status_code' => null,
                'duration_ms' => $duration,
                'is_up' => false,
                'error_message' => 'Connection failed: '.$e->getMessage(),
                'payload' => ['url' => $url, 'error_type' => 'connection'],
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            // RequestException always has a response, but it may not be successful
            $statusCode = $e->response->status();

            return [
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'is_up' => false,
                'error_message' => 'Request failed: '.$e->getMessage(),
                'payload' => [
                    'url' => $url,
                    'error_type' => 'request',
                    'status_code' => $statusCode,
                ],
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            Log::warning('HTTP health check exception', [
                'domain' => $domain,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return [
                'status_code' => null,
                'duration_ms' => $duration,
                'is_up' => false,
                'error_message' => 'Unexpected error: '.$e->getMessage(),
                'payload' => ['url' => $url, 'error_type' => 'unknown'],
            ];
        }
    }

    /**
     * Normalize URL to include protocol
     */
    private function normalizeUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        // Try HTTPS first, fallback to HTTP if needed
        return "https://{$domain}";
    }

    /**
     * Normalize headers array to simple key-value pairs
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = $value[0] ?? '';
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
