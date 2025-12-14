<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlatformDetector
{
    /**
     * Detect the platform for a given domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @return array{platform_type: string|null, platform_version: string|null, admin_url: string|null, detection_confidence: string}
     */
    public function detect(string $domain): array
    {
        $url = $this->normalizeUrl($domain);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'DomainMonitor/1.0',
                ])
                ->get($url);

            if (! $response->successful()) {
                return [
                    'platform_type' => 'Other',
                    'platform_version' => null,
                    'admin_url' => null,
                    'detection_confidence' => 'low',
                ];
            }

            $html = $response->body();
            /** @var array<string, array<int, string>|string> $headers */
            $headers = $response->headers();

            // Check headers first (most reliable)
            // Laravel HTTP client returns headers as arrays, get first value
            $poweredByHeader = $headers['X-Powered-By'] ?? $headers['x-powered-by'] ?? [];
            $poweredBy = is_array($poweredByHeader) ? (strtolower($poweredByHeader[0] ?? '')) : strtolower($poweredByHeader);

            $serverHeader = $headers['Server'] ?? $headers['server'] ?? [];
            $server = is_array($serverHeader) ? (strtolower($serverHeader[0] ?? '')) : strtolower($serverHeader);

            // WordPress detection
            if ($this->isWordPress($html, $poweredBy)) {
                $version = $this->extractWordPressVersion($html);

                return [
                    'platform_type' => 'WordPress',
                    'platform_version' => $version,
                    'admin_url' => $this->buildUrl($url, '/wp-admin'),
                    'detection_confidence' => 'high',
                ];
            }

            // Laravel detection
            if ($this->isLaravel($html, $headers)) {
                return [
                    'platform_type' => 'Laravel',
                    'platform_version' => null,
                    'admin_url' => $this->buildUrl($url, '/admin'),
                    'detection_confidence' => 'high',
                ];
            }

            // Next.js detection
            if ($this->isNextJs($html, $headers)) {
                return [
                    'platform_type' => 'Next.js',
                    'platform_version' => null,
                    'admin_url' => null,
                    'detection_confidence' => 'high',
                ];
            }

            // Shopify detection
            if ($this->isShopify($html, $headers, $domain)) {
                return [
                    'platform_type' => 'Shopify',
                    'platform_version' => null,
                    'admin_url' => $this->buildShopifyAdminUrl($domain),
                    'detection_confidence' => 'high',
                ];
            }

            // Parked domain detection (check before static site)
            if ($this->isParked($html, $domain)) {
                return [
                    'platform_type' => 'Parked',
                    'platform_version' => null,
                    'admin_url' => null,
                    'detection_confidence' => 'high',
                ];
            }

            // Static site detection (no CMS indicators)
            if ($this->isStaticSite($html, $poweredBy)) {
                return [
                    'platform_type' => 'Static',
                    'platform_version' => null,
                    'admin_url' => null,
                    'detection_confidence' => 'medium',
                ];
            }

            return [
                'platform_type' => 'Other',
                'platform_version' => null,
                'admin_url' => null,
                'detection_confidence' => 'low',
            ];
        } catch (\Exception $e) {
            Log::warning('Platform detection failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'platform_type' => 'Other',
                'platform_version' => null,
                'admin_url' => null,
                'detection_confidence' => 'low',
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

        return "https://{$domain}";
    }

    /**
     * Build URL from base and path
     */
    private function buildUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').$path;
    }

    /**
     * Check if site is WordPress
     */
    private function isWordPress(string $html, string $poweredBy): bool
    {
        return str_contains($html, 'wp-content') ||
            str_contains($html, 'wp-includes') ||
            str_contains($html, 'WordPress') ||
            preg_match('/<link[^>]*href=["\'][^"\']*wp-content[^"\']*["\'][^>]*>/i', $html) ||
            preg_match('/<script[^>]*src=["\'][^"\']*wp-content[^"\']*["\'][^>]*>/i', $html) ||
            str_contains($poweredBy, 'wordpress');
    }

    /**
     * Extract WordPress version from HTML
     */
    private function extractWordPressVersion(string $html): ?string
    {
        // Try to extract version from theme CSS
        if (preg_match('/wp-content\/themes\/[^\/]+\/style\.css\?ver=([\d.]+)/i', $html, $matches)) {
            return $matches[1];
        }

        // Try to extract from generator meta tag
        if (preg_match('/<meta[^>]*name=["\']generator["\'][^>]*content=["\']WordPress\s+([\d.]+)["\'][^>]*>/i', $html, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if site is Laravel
     */
    private function isLaravel(string $html, array $headers): bool
    {
        $poweredByHeader = $headers['X-Powered-By'] ?? $headers['x-powered-by'] ?? [];
        $poweredBy = is_array($poweredByHeader) ? (strtolower($poweredByHeader[0] ?? '')) : strtolower($poweredByHeader);

        return str_contains($poweredBy, 'laravel') ||
            isset($headers['X-Laravel-Session']) ||
            isset($headers['x-laravel-session']) ||
            str_contains($html, 'laravel_session') ||
            str_contains($html, '_token');
    }

    /**
     * Check if site is Next.js
     */
    private function isNextJs(string $html, array $headers): bool
    {
        $poweredByHeader = $headers['X-Powered-By'] ?? $headers['x-powered-by'] ?? [];
        $poweredBy = is_array($poweredByHeader) ? (strtolower($poweredByHeader[0] ?? '')) : strtolower($poweredByHeader);

        return str_contains($poweredBy, 'next') ||
            isset($headers['X-Nextjs-Cache']) ||
            isset($headers['x-nextjs-cache']) ||
            str_contains($html, '_next/static') ||
            str_contains($html, '__next');
    }

    /**
     * Check if site is Shopify
     */
    private function isShopify(string $html, array $headers, string $domain): bool
    {
        return str_contains(strtolower($html), 'shopify') ||
            str_contains($html, 'Shopify.theme') ||
            isset($headers['X-Shopify-Stage']) ||
            isset($headers['x-shopify-stage']) ||
            str_contains($domain, '.myshopify.com');
    }

    /**
     * Build Shopify admin URL
     */
    private function buildShopifyAdminUrl(string $domain): string
    {
        if (str_contains($domain, '.myshopify.com')) {
            $shopName = str_replace('.myshopify.com', '', $domain);

            return "https://{$shopName}.myshopify.com/admin";
        }

        return "https://{$domain}/admin";
    }

    /**
     * Check if site is static (no CMS indicators)
     */
    private function isStaticSite(string $html, string $poweredBy): bool
    {
        return empty($poweredBy) &&
            ! str_contains($html, 'wp-') &&
            ! str_contains($html, 'laravel') &&
            ! str_contains($html, '_next') &&
            ! str_contains(strtolower($html), 'shopify');
    }

    /**
     * Check if domain is parked
     */
    private function isParked(string $html, string $domain): bool
    {
        $htmlLower = strtolower($html);
        $domainLower = strtolower($domain);

        // Common parked page indicators
        $parkedIndicators = [
            'this domain is parked',
            'domain is parked',
            'parked domain',
            'this domain name is parked',
            'domain parking',
            'parked by',
            'parking page',
            'domain for sale',
            'this domain may be for sale',
            'buy this domain',
            'domain name registration',
            'sedo parking',
            'bodis',
            'parkingcrew',
            'domain parking service',
            'parked free',
            'parking page',
            'this domain is available',
            'domain is available',
            'domain name is available',
        ];

        // Check for parked page indicators
        foreach ($parkedIndicators as $indicator) {
            if (str_contains($htmlLower, $indicator)) {
                return true;
            }
        }

        // Check for common parked page providers
        $parkedProviders = [
            'sedo.com',
            'bodis.com',
            'parkingcrew.com',
            'parkingpage',
            'parked.com',
            'domainsponsor',
            'namedrive',
            'trafficz',
        ];

        foreach ($parkedProviders as $provider) {
            if (str_contains($htmlLower, $provider)) {
                return true;
            }
        }

        // Check for very minimal content (typical of parked pages)
        // Parked pages often have very little content
        $contentLength = strlen(strip_tags($html));
        if ($contentLength < 500) {
            // Check if it's a simple "coming soon" or parking page
            if (preg_match('/coming\s+soon|under\s+construction|parked|for\s+sale/i', $html)) {
                return true;
            }
        }

        return false;
    }
}
