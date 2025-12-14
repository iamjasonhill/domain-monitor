<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HostingDetector
{
    /**
     * Detect hosting provider for a given domain
     *
     * @param  string  $domain  Domain name (with or without protocol)
     * @return array{provider: string|null, confidence: string, admin_url: string|null}
     */
    public function detect(string $domain): array
    {
        $url = $this->normalizeUrl($domain);
        $domainOnly = $this->extractDomain($domain);

        try {
            // Get DNS records
            $dnsRecords = $this->getDnsRecords($domainOnly);

            // Get HTTP response
            $httpHeaders = $this->getHttpHeaders($url);

            // Check for each provider
            $detections = [];

            // Vercel detection
            if ($this->isVercel($dnsRecords, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Vercel',
                    'confidence' => 'high',
                    'admin_url' => 'https://vercel.com/dashboard',
                ];
            }

            // Render detection
            if ($this->isRender($dnsRecords, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Render',
                    'confidence' => 'high',
                    'admin_url' => 'https://dashboard.render.com',
                ];
            }

            // Cloudflare detection
            if ($this->isCloudflare($dnsRecords, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Cloudflare',
                    'confidence' => 'high',
                    'admin_url' => 'https://dash.cloudflare.com',
                ];
            }

            // AWS detection
            if ($this->isAws($dnsRecords, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'AWS',
                    'confidence' => 'medium',
                    'admin_url' => 'https://console.aws.amazon.com',
                ];
            }

            // Netlify detection
            if ($this->isNetlify($dnsRecords, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Netlify',
                    'confidence' => 'high',
                    'admin_url' => 'https://app.netlify.com',
                ];
            }

            // Return primary provider (first high confidence, or first if none)
            if (! empty($detections)) {
                // Prefer high confidence providers
                $highConfidence = array_filter($detections, fn ($d) => $d['confidence'] === 'high');
                if (! empty($highConfidence)) {
                    return reset($highConfidence);
                }

                return reset($detections);
            }

            return [
                'provider' => 'Other',
                'confidence' => 'low',
                'admin_url' => null,
            ];
        } catch (\Exception $e) {
            Log::warning('Hosting detection failed', [
                'domain' => $domain,
                'error' => $e->getMessage(),
            ]);

            return [
                'provider' => 'Other',
                'confidence' => 'low',
                'admin_url' => null,
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
     * Get DNS records for domain
     *
     * @return array<int, array<string, mixed>>
     */
    private function getDnsRecords(string $domain): array
    {
        $records = [];

        try {
            // Get NS records
            $nsRecords = @dns_get_record($domain, DNS_NS);
            if ($nsRecords) {
                $records = array_merge($records, $nsRecords);
            }

            // Get A records
            $aRecords = @dns_get_record($domain, DNS_A);
            if ($aRecords) {
                $records = array_merge($records, $aRecords);
            }

            // Get CNAME records
            $cnameRecords = @dns_get_record($domain, DNS_CNAME);
            if ($cnameRecords) {
                $records = array_merge($records, $cnameRecords);
            }
        } catch (\Exception $e) {
            Log::debug('DNS lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        return $records;
    }

    /**
     * Get HTTP headers for domain
     *
     * @return array<string, array<int, string>|string>
     */
    /**
     * @return array<string, array<int, string>|string>
     */
    private function getHttpHeaders(string $url): array
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'DomainMonitor/1.0',
                ])
                ->get($url);

            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                /** @var array<string, array<int, string>|string> $headers */
                $headers = $response->headers();

                return $headers;
            }
        } catch (\Exception $e) {
            Log::debug('HTTP request failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return [];
    }

    /**
     * Check if domain is hosted on Vercel
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isVercel(array $dnsRecords, array $httpHeaders): bool
    {
        // Check DNS records
        foreach ($dnsRecords as $record) {
            $value = strtolower($record['target'] ?? $record['host'] ?? '');
            if (str_contains($value, 'vercel-dns') || str_contains($value, 'vercel.com')) {
                return true;
            }
        }

        // Check HTTP headers
        $vercelId = $httpHeaders['X-Vercel-Id'] ?? $httpHeaders['x-vercel-id'] ?? [];
        $vercelCache = $httpHeaders['X-Vercel-Cache'] ?? $httpHeaders['x-vercel-cache'] ?? [];

        if (! empty($vercelId) || ! empty($vercelCache)) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is hosted on Render
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isRender(array $dnsRecords, array $httpHeaders): bool
    {
        // Check HTTP headers
        $renderHeader = $httpHeaders['X-Render'] ?? $httpHeaders['x-render'] ?? [];
        if (! empty($renderHeader)) {
            return true;
        }

        $server = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? [];
        $serverValue = is_array($server) ? ($server[0] ?? '') : $server;
        if (str_contains(strtolower($serverValue), 'render')) {
            return true;
        }

        // Check DNS records
        foreach ($dnsRecords as $record) {
            $value = strtolower($record['target'] ?? $record['host'] ?? '');
            if (str_contains($value, 'render.com')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain uses Cloudflare
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isCloudflare(array $dnsRecords, array $httpHeaders): bool
    {
        // Check DNS records (NS records)
        foreach ($dnsRecords as $record) {
            if (($record['type'] ?? '') === 'NS') {
                $value = strtolower($record['target'] ?? $record['host'] ?? '');
                if (str_contains($value, 'cloudflare')) {
                    return true;
                }
            }
        }

        // Check HTTP headers
        $cfRay = $httpHeaders['CF-Ray'] ?? $httpHeaders['cf-ray'] ?? [];
        $cfCache = $httpHeaders['CF-Cache-Status'] ?? $httpHeaders['cf-cache-status'] ?? [];

        if (! empty($cfRay) || ! empty($cfCache)) {
            return true;
        }

        $server = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? [];
        $serverValue = is_array($server) ? ($server[0] ?? '') : $server;
        if (str_contains(strtolower($serverValue), 'cloudflare')) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is hosted on AWS
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isAws(array $dnsRecords, array $httpHeaders): bool
    {
        // Check DNS records
        foreach ($dnsRecords as $record) {
            $value = strtolower($record['target'] ?? $record['host'] ?? '');
            if (str_contains($value, 'amazonaws.com') || str_contains($value, 'aws')) {
                return true;
            }
        }

        // Check HTTP headers
        $amzRequestId = $httpHeaders['X-Amz-Request-Id'] ?? $httpHeaders['x-amz-request-id'] ?? [];
        $amzCfId = $httpHeaders['X-Amz-Cf-Id'] ?? $httpHeaders['x-amz-cf-id'] ?? [];

        if (! empty($amzRequestId) || ! empty($amzCfId)) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is hosted on Netlify
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isNetlify(array $dnsRecords, array $httpHeaders): bool
    {
        // Check HTTP headers
        $nfRequestId = $httpHeaders['X-Nf-Request-Id'] ?? $httpHeaders['x-nf-request-id'] ?? [];
        if (! empty($nfRequestId)) {
            return true;
        }

        $server = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? [];
        $serverValue = is_array($server) ? ($server[0] ?? '') : $server;
        if (str_contains(strtolower($serverValue), 'netlify')) {
            return true;
        }

        // Check DNS records
        foreach ($dnsRecords as $record) {
            $value = strtolower($record['target'] ?? $record['host'] ?? '');
            if (str_contains($value, 'netlify')) {
                return true;
            }
        }

        return false;
    }
}
