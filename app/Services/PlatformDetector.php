<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
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
        // Check for email-only domains first (before HTTP check)
        // Email-only domains have MX records but no A/AAAA records for web hosting
        if ($this->isEmailOnly($domain)) {
            return [
                'platform_type' => 'Email Only',
                'platform_version' => null,
                'admin_url' => null,
                'detection_confidence' => 'high',
            ];
        }

        $url = $this->normalizeUrl($domain);

        try {
            // Try HTTPS first
            try {
                $response = Http::timeout(10)
                    ->withoutVerifying() // Allow self-signed certificates for parked domains
                    ->withHeaders([
                        'User-Agent' => 'DomainMonitor/1.0',
                    ])
                    ->get($url);
                /** @var Response $response */
            } catch (\Exception $e) {
                // If HTTPS fails with SSL error, try HTTP
                Log::debug('PlatformDetector HTTPS failed, trying HTTP', [
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ]);
                $url = $this->tryHttpFallback($url);
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'DomainMonitor/1.0',
                    ])
                    ->get($url);
                /** @var Response $response */
            }

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

            // Debug: Log HTML content for parked domain detection (first 500 chars)
            if (strlen($html) > 0) {
                Log::debug('PlatformDetector HTML sample', [
                    'domain' => $domain,
                    'html_length' => strlen($html),
                    'html_sample' => substr($html, 0, 500),
                    'status_code' => $response->status(),
                ]);
            } else {
                Log::warning('PlatformDetector received empty HTML', [
                    'domain' => $domain,
                    'status_code' => $response->status(),
                ]);
            }

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
            $isParkedResult = $this->isParked($html, $domain);
            Log::debug('PlatformDetector parked check', [
                'domain' => $domain,
                'is_parked' => $isParkedResult,
                'html_length' => strlen($html),
            ]);

            if ($isParkedResult) {
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

            // Custom PHP detection
            if ($this->isCustomPhp($html, $headers)) {
                return [
                    'platform_type' => 'Custom PHP',
                    'platform_version' => null,
                    'admin_url' => null, // Custom sites rarely have standard admin paths
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
     * Try HTTPS first, but allow fallback to HTTP if SSL fails
     */
    private function normalizeUrl(string $domain): string
    {
        if (str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')) {
            return $domain;
        }

        return "https://{$domain}";
    }

    /**
     * Try HTTP if HTTPS fails (for parked domains with SSL issues)
     */
    private function tryHttpFallback(string $domain): string
    {
        if (str_starts_with($domain, 'http://')) {
            return $domain;
        }
        if (str_starts_with($domain, 'https://')) {
            return str_replace('https://', 'http://', $domain);
        }

        return "http://{$domain}";
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
     *
     * @param  array<string, array<int, string>|string>  $headers
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
     *
     * @param  array<string, array<int, string>|string>  $headers
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
     *
     * @param  array<string, array<int, string>|string>  $headers
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
     * Check if site is likely Custom PHP
     *
     * @param  array<string, array<int, string>|string>  $headers
     */
    private function isCustomPhp(string $html, array $headers): bool
    {
        $poweredByHeader = $headers['X-Powered-By'] ?? $headers['x-powered-by'] ?? [];
        $poweredBy = is_array($poweredByHeader) ? (strtolower($poweredByHeader[0] ?? '')) : strtolower($poweredByHeader);

        // Strong indicator: X-Powered-By: PHP/...
        if (str_contains($poweredBy, 'php')) {
            // But ensure it's not a known framework (already checked above)
            return true;
        }

        // Check for visible .php extensions in links
        // Matches href="something.php" or href="/path/file.php"
        if (preg_match('/href=["\'][^"\']+\.php(?:\?[^"\']*)?["\']/i', $html)) {
            return true;
        }

        // Check for common PHP include comments (sometimes left in by developers)
        // e.g. <!-- header.php -->, <!-- include -->
        if (preg_match('/<!--\s*[\w-]+\.php\s*-->/i', $html)) {
            return true;
        }

        return false;
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

        // Extract title and meta description for more accurate detection
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatches)) {
            $title = strtolower(strip_tags($titleMatches[1]));
        }

        $metaDescription = '';
        if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $metaMatches)) {
            $metaDescription = strtolower($metaMatches[1]);
        }

        // Common parked page indicators (expanded list)
        $parkedIndicators = [
            'this domain is parked',
            'domain is parked',
            'parked domain',
            'this domain name is parked',
            'this domain name is currently parked',
            'domain name is currently parked',
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
            'this domain is available',
            'domain is available',
            'domain name is available',
            'synergywholesale.com/manage', // Synergy Wholesale parking pages
            'static.synergywholesale.com', // Synergy Wholesale static assets
            'manage.synergywholesale.com', // Synergy Wholesale management
            'your domains', // Common parked page title (Synergy/VentraIP)
            'static.ventraip.com.au', // VentraIP static assets
            'static.synergywholesale.com/manage', // Synergy management pages
            'domain parked',
            'is parked',
            'currently parked',
            'domain name parked',
            'parked domain name',
            'this domain has been parked',
            'domain has been parked',
            'parked by the owner',
            'parked by owner',
            'domain parking service',
            'parking service',
            'domain parking page',
            'parked page',
            'this domain is for sale',
            'domain for sale',
            'this domain may be for sale',
            'domain name for sale',
            'buy this domain name',
            'purchase this domain',
            'domain purchase',
            'register this domain',
            'domain registration',
            'domain name registration',
            'coming soon',
            'under construction',
            'website coming soon',
            'site coming soon',
            'page coming soon',
        ];

        // Check for parked page indicators in full HTML
        foreach ($parkedIndicators as $indicator) {
            if (str_contains($htmlLower, $indicator)) {
                return true;
            }
        }

        // Check title specifically (very reliable indicator)
        foreach ($parkedIndicators as $indicator) {
            if (str_contains($title, $indicator)) {
                return true;
            }
        }

        // Check meta description
        foreach ($parkedIndicators as $indicator) {
            if (str_contains($metaDescription, $indicator)) {
                return true;
            }
        }

        // Additional flexible check for "domain name" + "parked" variations
        // This catches "this domain name is currently parked", "domain name is parked", etc.
        if (preg_match('/domain\s+name.*parked|parked.*domain\s+name/i', $html)) {
            return true;
        }

        // Check for common parked page providers (expanded)
        $parkedProviders = [
            'sedo.com',
            'sedoparking',
            'bodis.com',
            'parkingcrew.com',
            'parkingpage',
            'parked.com',
            'domainsponsor',
            'namedrive',
            'trafficz',
            'synergywholesale.com',
            'static.synergywholesale.com',
            'manage.synergywholesale.com',
            'ventraip.com.au',
            'static.ventraip.com.au',
            'crazydomains.com.au',
            'netregistry.com.au',
            'tppwholesale.com',
            'domaincentral.com.au',
            'parklogic',
            'parkingpanel',
            'parkingcrew',
            'parkedpage',
        ];

        foreach ($parkedProviders as $provider) {
            if (str_contains($htmlLower, $provider)) {
                return true;
            }
        }

        // Check for parked page HTML structure patterns
        // Many parked pages have specific class names or IDs
        $parkedHtmlPatterns = [
            '/class=["\'][^"\']*parked[^"\']*["\']/i',
            '/id=["\'][^"\']*parked[^"\']*["\']/i',
            '/class=["\'][^"\']*parking[^"\']*["\']/i',
            '/id=["\'][^"\']*parking[^"\']*["\']/i',
            '/class=["\'][^"\']*domain-sale[^"\']*["\']/i',
            '/id=["\'][^"\']*domain-sale[^"\']*["\']/i',
        ];

        foreach ($parkedHtmlPatterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }

        // Check for very minimal content (typical of parked pages)
        // Parked pages often have very little content
        $contentLength = strlen(strip_tags($html));
        if ($contentLength < 500) {
            // Check if it's a simple "coming soon" or parking page
            if (preg_match('/coming\s+soon|under\s+construction|parked|for\s+sale|domain\s+name|domain\s+parking/i', $html)) {
                return true;
            }

            // Very minimal content with domain name in title often indicates parking
            if ($contentLength < 200 && ! empty($title) && str_contains($title, $domainLower)) {
                return true;
            }
        }

        // Check for redirects to parking services
        if (preg_match('/<meta[^>]*http-equiv=["\']refresh["\'][^>]*content=["\'][^"\']*url=([^"\']*(?:sedo|bodis|parking|parked)[^"\']*)["\'][^>]*>/i', $html)) {
            return true;
        }

        // Check for common parked page text patterns
        // Many parked pages have phrases like "This domain is..." or "Domain name..."
        if (preg_match('/this\s+domain\s+(?:is|has|may|can|will)/i', $html)) {
            // If combined with minimal content, likely parked
            if ($contentLength < 1000) {
                return true;
            }
        }

        // Check for domain name prominently displayed with minimal other content
        // Parked pages often just show the domain name
        if (preg_match('/<h[1-6][^>]*>.*?'.preg_quote($domainLower, '/').'.*?<\/h[1-6]>/i', $html)) {
            if ($contentLength < 800) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain is email-only (has MX records but no A/AAAA records for web)
     *
     * @param  string  $domain  Domain name
     * @return bool True if domain appears to be email-only
     */
    private function isEmailOnly(string $domain): bool
    {
        $domainOnly = $this->extractDomainForDns($domain);

        try {
            // Get MX records (email)
            $mxRecords = @dns_get_record($domainOnly, DNS_MX);
            $hasMxRecord = ! empty($mxRecords);

            // Get A records (IPv4 web hosting)
            $aRecords = @dns_get_record($domainOnly, DNS_A);
            $hasARecord = ! empty($aRecords);

            // Get AAAA records (IPv6 web hosting)
            $aaaaRecords = @dns_get_record($domainOnly, DNS_AAAA);
            $hasAaaaRecord = ! empty($aaaaRecords);

            // Get CNAME records (might point to web hosting)
            $cnameRecords = @dns_get_record($domainOnly, DNS_CNAME);
            $hasCnameRecord = ! empty($cnameRecords);

            // Check for www subdomain A records (common web hosting indicator)
            $wwwARecords = @dns_get_record('www.'.$domainOnly, DNS_A);
            $hasWwwARecord = ! empty($wwwARecords);

            // Domain is email-only if:
            // 1. Has MX records (email configured)
            // 2. No A or AAAA records for root domain (no web hosting)
            // 3. No CNAME records pointing to web services
            // 4. No www subdomain A records
            if ($hasMxRecord && ! $hasARecord && ! $hasAaaaRecord && ! $hasCnameRecord && ! $hasWwwARecord) {
                return true;
            }

            // Also check if nameservers are missing or minimal (often indicates email-only setup)
            $nsRecords = @dns_get_record($domainOnly, DNS_NS);
            $hasNsRecords = ! empty($nsRecords);

            // If has MX but no nameservers or very minimal DNS, likely email-only
            if ($hasMxRecord && (! $hasNsRecords || count($nsRecords) < 2) && ! $hasARecord && ! $hasAaaaRecord) {
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::debug('Email-only check failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract domain from URL for DNS lookups
     */
    private function extractDomainForDns(string $domain): string
    {
        $domain = str_replace(['http://', 'https://'], '', $domain);
        $domain = explode('/', $domain)[0];
        $domain = explode('?', $domain)[0];

        return $domain;
    }
}
