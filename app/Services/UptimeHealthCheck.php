<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class UptimeHealthCheck
{
    /**
     * Perform Uptime health check for a domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @param  int  $timeout  Timeout in seconds (default 5 for uptime)
     * @return array{is_valid: bool, status_code: int|null, duration_ms: int, error_message: string|null, payload: array<string, mixed>}
     */
    public function check(string $domain, int $timeout = 5): array
    {
        $url = $this->normalizeUrl($domain);
        $startTime = microtime(true);

        try {
            // Try HEAD first for speed
            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout($timeout)
                    ->withoutVerifying()
                    ->withHeaders(['User-Agent' => 'DomainMonitor/1.0 (Uptime)'])
                    ->head($url);

                // If Method Not Allowed, fallback to GET
                if ($response->status() === 405) {
                    /** @var \Illuminate\Http\Client\Response $response */
                    $response = Http::timeout($timeout)
                        ->withoutVerifying()
                        ->withHeaders(['User-Agent' => 'DomainMonitor/1.0 (Uptime)'])
                        ->get($url);
                }
            } catch (\Exception $e) {
                // Fallback to GET on any initial failure (e.g. connection reset on HEAD)
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout($timeout)
                    ->withoutVerifying()
                    ->withHeaders(['User-Agent' => 'DomainMonitor/1.0 (Uptime)'])
                    ->get($url);
            }

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $statusCode = $response->status();
            $isUp = $response->successful() || ($statusCode >= 200 && $statusCode < 500);

            return [
                'is_valid' => $isUp,
                'status_code' => $statusCode,
                'duration_ms' => $duration,
                'error_message' => $isUp ? null : "HTTP {$statusCode}",
                'payload' => [
                    'url' => $url,
                    'status_code' => $statusCode,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (\Exception $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => false,
                'status_code' => null,
                'duration_ms' => $duration,
                'error_message' => 'Check failed: '.$e->getMessage(),
                'payload' => [
                    'url' => $url,
                    'error_type' => 'exception',
                    'duration_ms' => $duration,
                ],
            ];
        }
    }

    private function normalizeUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return "https://{$domain}";
    }
}
