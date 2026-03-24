<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;

class SecurityHeadersHealthCheck
{
    private const MIN_HSTS_MAX_AGE = 31536000;

    /**
     * Perform Security Headers health check
     *
     * @return array{
     *     is_valid: bool,
     *     verified: bool,
     *     score: int,
     *     headers: array<string, array{name: string, present: bool, value: string|null, status: string, recommendation: string|null, assessment: string}>,
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
            $isValid = collect($results)->every(fn (array $result): bool => $result['status'] === 'pass');

            $duration = (int) ((microtime(true) - $startTime) * 1000);

            return [
                'is_valid' => $isValid,
                'verified' => true,
                'score' => $score,
                'headers' => $results,
                'error_message' => null,
                'payload' => [
                    'domain' => $domain,
                    'score' => $score,
                    'standard_name' => 'Domain Monitor security header baseline',
                    'standard_summary' => 'Checks six response headers against a baseline standard. This is not a full browser security audit.',
                    'results' => $results,
                    'duration_ms' => $duration,
                ],
            ];
        } catch (Exception $e) {
            return [
                'is_valid' => false,
                'verified' => false,
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
     * Analyze security headers against the Domain Monitor baseline standard.
     *
     * @param  array<string, array<int, string>|string>  $headers
     * @return array<string, array{name: string, present: bool, value: string|null, status: string, recommendation: string|null, assessment: string}>
     */
    private function analyzeHeaders(array $headers): array
    {
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = is_array($value) ? implode('; ', $value) : $value;
        }

        $checks = [
            'strict-transport-security' => [
                'name' => 'HSTS',
                'validator' => fn (?string $value): array => $this->validateHsts($value),
            ],
            'content-security-policy' => [
                'name' => 'CSP',
                'validator' => fn (?string $value): array => $this->validateCsp($value),
            ],
            'x-frame-options' => [
                'name' => 'X-Frame-Options',
                'validator' => fn (?string $value): array => $this->validateXFrameOptions($value),
            ],
            'x-content-type-options' => [
                'name' => 'X-Content-Type-Options',
                'validator' => fn (?string $value): array => $this->validateXContentTypeOptions($value),
            ],
            'referrer-policy' => [
                'name' => 'Referrer-Policy',
                'validator' => fn (?string $value): array => $this->validateReferrerPolicy($value),
            ],
            'permissions-policy' => [
                'name' => 'Permissions-Policy',
                'validator' => fn (?string $value): array => $this->validatePermissionsPolicy($value),
            ],
        ];

        $results = [];

        foreach ($checks as $header => $config) {
            $present = isset($normalizedHeaders[$header]);
            $value = $present ? $normalizedHeaders[$header] : null;
            $assessment = $config['validator']($value);

            $results[$header] = [
                'name' => $config['name'],
                'present' => $present,
                'value' => $value,
                'status' => $assessment['status'],
                'recommendation' => $assessment['recommendation'],
                'assessment' => $assessment['assessment'],
            ];
        }

        return $results;
    }

    /**
     * Calculate a security score (0-100)
     *
     * @param  array<string, array{name: string, present: bool, value: string|null, status: string, recommendation: string|null, assessment: string}>  $results
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

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validateHsts(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'fail',
                'recommendation' => 'Add Strict-Transport-Security with a max-age of at least 31536000 seconds.',
                'assessment' => 'Missing.',
            ];
        }

        if (! preg_match('/max-age=(\d+)/i', $value, $matches)) {
            return [
                'status' => 'fail',
                'recommendation' => 'Include max-age in Strict-Transport-Security.',
                'assessment' => 'Present, but missing max-age.',
            ];
        }

        $maxAge = (int) $matches[1];
        $hasIncludeSubdomains = str_contains(strtolower($value), 'includesubdomains');

        if ($maxAge < self::MIN_HSTS_MAX_AGE) {
            return [
                'status' => 'warn',
                'recommendation' => 'Increase Strict-Transport-Security max-age to at least 31536000 seconds.',
                'assessment' => "Present, but max-age is only {$maxAge}.",
            ];
        }

        if (! $hasIncludeSubdomains) {
            return [
                'status' => 'warn',
                'recommendation' => 'Add includeSubDomains when all subdomains are ready for HTTPS-only enforcement.',
                'assessment' => 'Present, but missing includeSubDomains.',
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validateCsp(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'fail',
                'recommendation' => 'Add Content-Security-Policy with at least default-src, object-src, base-uri, and frame-ancestors.',
                'assessment' => 'Missing.',
            ];
        }

        $requiredDirectives = ['default-src', 'object-src', 'base-uri', 'frame-ancestors'];
        $missingDirectives = [];
        foreach ($requiredDirectives as $directive) {
            if (! preg_match('/(^|;)\s*'.preg_quote($directive, '/').'\s+/i', $value)) {
                $missingDirectives[] = $directive;
            }
        }

        if ($missingDirectives !== []) {
            return [
                'status' => 'warn',
                'recommendation' => 'Add missing CSP directives: '.implode(', ', $missingDirectives).'.',
                'assessment' => 'Present, but missing '.implode(', ', $missingDirectives).'.',
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validateXFrameOptions(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'fail',
                'recommendation' => 'Set X-Frame-Options to DENY or SAMEORIGIN.',
                'assessment' => 'Missing.',
            ];
        }

        $normalized = strtoupper(trim($value));
        if (! in_array($normalized, ['DENY', 'SAMEORIGIN'], true)) {
            return [
                'status' => 'fail',
                'recommendation' => 'Use DENY or SAMEORIGIN for X-Frame-Options.',
                'assessment' => "Present, but '{$value}' is not an accepted value.",
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validateXContentTypeOptions(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'fail',
                'recommendation' => 'Set X-Content-Type-Options to nosniff.',
                'assessment' => 'Missing.',
            ];
        }

        if (strtolower(trim($value)) !== 'nosniff') {
            return [
                'status' => 'fail',
                'recommendation' => 'Use "nosniff" for X-Content-Type-Options.',
                'assessment' => "Present, but '{$value}' is not an accepted value.",
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validateReferrerPolicy(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'warn',
                'recommendation' => 'Add Referrer-Policy, ideally strict-origin-when-cross-origin or stricter.',
                'assessment' => 'Missing.',
            ];
        }

        $normalized = strtolower(trim($value));
        $allowed = [
            'no-referrer',
            'same-origin',
            'strict-origin',
            'strict-origin-when-cross-origin',
        ];

        if (! in_array($normalized, $allowed, true)) {
            return [
                'status' => 'warn',
                'recommendation' => 'Use strict-origin-when-cross-origin or a stricter Referrer-Policy.',
                'assessment' => "Present, but '{$value}' is weaker than the baseline standard.",
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }

    /**
     * @return array{status: string, recommendation: string|null, assessment: string}
     */
    private function validatePermissionsPolicy(?string $value): array
    {
        if ($value === null) {
            return [
                'status' => 'warn',
                'recommendation' => 'Add Permissions-Policy to disable browser features your site does not need.',
                'assessment' => 'Missing.',
            ];
        }

        if (trim($value) === '') {
            return [
                'status' => 'warn',
                'recommendation' => 'Populate Permissions-Policy with explicit feature controls.',
                'assessment' => 'Present, but empty.',
            ];
        }

        return [
            'status' => 'pass',
            'recommendation' => null,
            'assessment' => 'Meets the baseline standard.',
        ];
    }
}
