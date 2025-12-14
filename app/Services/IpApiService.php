<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpApiService
{
    /**
     * Rate limit: 45 requests per minute (free tier)
     * We'll use 40 requests per minute to be safe
     */
    private const RATE_LIMIT_PER_MINUTE = 40;

    private const CACHE_TTL_SECONDS = 86400; // 24 hours

    /**
     * Query IP-API.com for IP address information
     *
     * @param  string  $ipAddress  IP address to query
     * @return array<string, mixed>|null API response data or null on failure
     */
    public function query(string $ipAddress): ?array
    {
        // Validate IP address
        if (! filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            Log::warning('Invalid IP address provided to IpApiService', ['ip' => $ipAddress]);

            return null;
        }

        // Check rate limit
        if (! $this->checkRateLimit()) {
            Log::warning('IP-API.com rate limit reached, skipping query', ['ip' => $ipAddress]);

            return null;
        }

        // Check cache first
        $cacheKey = "ip_api:{$ipAddress}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(10)
                ->get("http://ip-api.com/json/{$ipAddress}");

            if (! $response->successful()) {
                Log::warning('IP-API.com request failed', [
                    'ip' => $ipAddress,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();

            if (isset($data['status']) && $data['status'] === 'fail') {
                Log::warning('IP-API.com returned error', [
                    'ip' => $ipAddress,
                    'message' => $data['message'] ?? 'Unknown error',
                ]);

                return null;
            }

            // Cache the result for 24 hours
            Cache::put($cacheKey, $data, self::CACHE_TTL_SECONDS);

            // Record this request for rate limiting
            $this->recordRequest();

            return $data;
        } catch (\Exception $e) {
            Log::error('IP-API.com query exception', [
                'ip' => $ipAddress,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if we're within rate limit
     */
    private function checkRateLimit(): bool
    {
        $key = 'ip_api:rate_limit';
        $requests = Cache::get($key, []);

        // Remove requests older than 1 minute
        $now = now()->timestamp;
        $requests = array_filter($requests, fn ($timestamp) => ($now - $timestamp) < 60);

        // Check if we're at the limit
        if (count($requests) >= self::RATE_LIMIT_PER_MINUTE) {
            return false;
        }

        return true;
    }

    /**
     * Record a request for rate limiting
     */
    private function recordRequest(): void
    {
        $key = 'ip_api:rate_limit';
        $requests = Cache::get($key, []);

        // Remove requests older than 1 minute
        $now = now()->timestamp;
        $requests = array_filter($requests, fn ($timestamp) => ($now - $timestamp) < 60);

        // Add current request
        $requests[] = $now;

        // Store for 2 minutes (to allow cleanup)
        Cache::put($key, $requests, 120);
    }

    /**
     * Extract hosting provider from IP-API data
     * Prioritizes organization field, then ISP field
     *
     * @param  array<string, mixed>  $data  IP-API response data
     * @return string|null Hosting provider name or null
     */
    public function extractHostingProvider(array $data): ?string
    {
        // Organization field is most reliable
        if (! empty($data['org'])) {
            return $this->normalizeProviderName($data['org']);
        }

        // Fallback to ISP field
        if (! empty($data['isp'])) {
            return $this->normalizeProviderName($data['isp']);
        }

        return null;
    }

    /**
     * Normalize provider name (remove common suffixes, clean up)
     *
     * @param  string  $name  Raw provider name
     * @return string Normalized provider name
     */
    private function normalizeProviderName(string $name): string
    {
        // Remove common suffixes
        $name = preg_replace('/\s+(Inc|LLC|Ltd|Limited|Corp|Corporation)\.?$/i', '', $name);
        $name = trim($name);

        // Common provider name mappings
        $mappings = [
            'Amazon.com' => 'AWS',
            'Amazon Technologies Inc' => 'AWS',
            'Amazon Data Services' => 'AWS',
            'Google LLC' => 'Google Cloud',
            'Microsoft Corporation' => 'Azure',
            'DigitalOcean' => 'DigitalOcean',
            'Linode' => 'Linode',
            'Cloudflare' => 'Cloudflare',
            'Vercel' => 'Vercel',
            'CYPRESS COMMUNICATIONS' => 'Vercel',
            'Cypress Communications' => 'Vercel',
            'Render' => 'Render',
            'Netlify' => 'Netlify',
        ];

        foreach ($mappings as $key => $value) {
            if (stripos($name, $key) !== false) {
                return $value;
            }
        }

        return $name;
    }

    /**
     * Get suggested login URL for hosting provider
     *
     * @param  string|null  $providerName  Hosting provider name
     * @return string|null Suggested login URL or null
     */
    public function getSuggestedLoginUrl(?string $providerName): ?string
    {
        return \App\Services\HostingProviderUrls::getLoginUrl($providerName);
    }
}
