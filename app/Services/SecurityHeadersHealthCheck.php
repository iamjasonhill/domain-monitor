<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class SecurityHeadersHealthCheck
{
    /**
     * Perform Security Headers health check
     *
     * @return array{
     *     is_valid: bool,
     *     score: int,
     *     headers: array<string, array{present: bool, value: string|null, status: string, recommendation: string|null}>,
     *     error_message: string|null,
     *     payload: array<string, mixed>
     * }
     */
    public function check(string $domain): array
    {
        $startTime = microtime(true);
        $url = $this->normalizeUrl($domain);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'DomainMonitor/1.0'])
                ->get($url);

            /** @var \Illuminate\Http\Client\Response $response */
            $headers = $response->headers();
            $results = $this->analyzeHeaders($headers);
            $score = $this->calculateScore($results);

            // Consider valid if score is acceptable (e.g. > 50) or if critical headers are present
            // For now, valid means no critical failures (red status)
            $criticalFailures = collect($results)->where('status', 'fail')->count();
            $isValid = $criticalFailures === 0;

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'score' => $score,
                'headers' => $results,
                'error_message' => null,
                'payload' => [
                    'domain' => $domain,
                    'score' => $score,
                    'results' => $results,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'score' => 0,
                'headers' => [],
                'error_message' => 'Exception: '.$e->getMessage(),
                'payload' => [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
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

    /**
     * Analyze critical security headers
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, array{present: bool, value: string|null, status: string, recommendation: string|null}>
     */
    private function analyzeHeaders(array $headers): array
    {
        // Normalize headers to lowercase keys for easier lookup
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = is_array($value) ? implode('; ', $value) : $value;
        }

        $checks = [
            'strict-transport-security' => [
                'name' => 'HSTS',
                'critical' => true,
                'recommendation' => ' Enable HSTS to enforce HTTPS.',
            ],
            'content-security-policy' => [
                'name' => 'CSP',
                'critical' => false, // CSP is complex, missing it is a warning not a failure
                'recommendation' => 'Add a Content Security Policy to prevent XSS.',
            ],
            'x-frame-options' => [
                'name' => 'X-Frame-Options',
                'critical' => true,
                'recommendation' => 'Set to DENY or SAMEORIGIN to prevent clickjacking.',
            ],
            'x-content-type-options' => [
                'name' => 'X-Content-Type-Options',
                'critical' => true,
                'recommendation' => 'Set to "nosniff" to prevent MIME sniffing.',
            ],
            'referrer-policy' => [
                'name' => 'Referrer-Policy',
                'critical' => false,
                'recommendation' => 'Set a Referrer Policy to control data leakage.',
            ],
            'permissions-policy' => [
                'name' => 'Permissions-Policy',
                'critical' => false,
                'recommendation' => 'Restrict browser features with Permissions Policy.',
            ],
        ];

        $results = [];

        foreach ($checks as $header => $config) {
            $present = isset($normalizedHeaders[$header]);
            $value = $present ? $normalizedHeaders[$header] : null;

            $status = $present ? 'pass' : ($config['critical'] ? 'fail' : 'warn');

            // Special check for HSTS max-age
            if ($header === 'strict-transport-security' && $present && ! str_contains($value, 'max-age')) {
                $status = 'warn'; // HSTS present but invalid
            }

            $results[$header] = [
                'name' => $config['name'],
                'present' => $present,
                'value' => $value,
                'status' => $status,
                'recommendation' => $present ? null : $config['recommendation'],
            ];
        }

        return $results;
    }

    /**
     * Calculate a security score (0-100)
     *
     * @param  array<string, array{present: bool, value: string|null, status: string, recommendation: string|null}>  $results
     */
    private function calculateScore(array $results): int
    {
        $total = count($results);
        $passed = collect($results)->where('status', 'pass')->count();
        $warned = collect($results)->where('status', 'warn')->count();

        // 100 points max
        // Pass = 1 point
        // Warn = 0.5 point
        // Fail = 0 points

        if ($total === 0) {
            return 0;
        }

        $score = (($passed * 1.0) + ($warned * 0.5)) / $total * 100;

        return (int) round($score);
    }
}
