<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HostingDetector
{
    public function __construct(
        private ?IpApiService $ipApiService = null
    ) {
        $this->ipApiService = $ipApiService ?? app(IpApiService::class);
    }

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

            // Get IP address(es) for the domain
            $ipAddresses = $this->getIpAddresses($domainOnly);

            // Query IP-API.com for hosting provider info (use first IP)
            $ipApiData = null;
            $ipApiProvider = null;
            if (! empty($ipAddresses)) {
                $primaryIp = $ipAddresses[0];
                $ipApiData = $this->ipApiService->query($primaryIp);
                if ($ipApiData) {
                    $ipApiProvider = $this->ipApiService->extractHostingProvider($ipApiData);
                }
            }

            // Get HTTP response
            $httpHeaders = $this->getHttpHeaders($url);

            // Check for each provider
            $detections = [];

            // Vercel detection
            if ($this->isVercel($dnsRecords, $httpHeaders, $ipAddresses)) {
                $detections[] = [
                    'provider' => 'Vercel',
                    'confidence' => 'high',
                    'admin_url' => 'https://vercel.com/dashboard',
                ];
            }

            // Render detection
            if ($this->isRender($dnsRecords, $httpHeaders, $ipAddresses)) {
                $detections[] = [
                    'provider' => 'Render',
                    'confidence' => 'high',
                    'admin_url' => 'https://dashboard.render.com',
                ];
            }

            // Cloudflare detection
            if ($this->isCloudflare($dnsRecords, $httpHeaders, $ipAddresses)) {
                $detections[] = [
                    'provider' => 'Cloudflare',
                    'confidence' => 'high',
                    'admin_url' => 'https://dash.cloudflare.com',
                ];
            }

            // AWS detection
            if ($this->isAws($dnsRecords, $httpHeaders, $ipAddresses)) {
                $detections[] = [
                    'provider' => 'AWS',
                    'confidence' => 'high',
                    'admin_url' => 'https://console.aws.amazon.com',
                ];
            }

            // Netlify detection
            if ($this->isNetlify($dnsRecords, $httpHeaders, $ipAddresses)) {
                $detections[] = [
                    'provider' => 'Netlify',
                    'confidence' => 'high',
                    'admin_url' => 'https://app.netlify.com',
                ];
            }

            // DigitalOcean detection (via IP ranges)
            if ($this->isDigitalOcean($ipAddresses, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'DigitalOcean',
                    'confidence' => 'high',
                    'admin_url' => 'https://cloud.digitalocean.com',
                ];
            }

            // Linode detection (via IP ranges)
            if ($this->isLinode($ipAddresses, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Linode',
                    'confidence' => 'high',
                    'admin_url' => 'https://cloud.linode.com',
                ];
            }

            // Google Cloud detection
            if ($this->isGoogleCloud($ipAddresses, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Google Cloud',
                    'confidence' => 'high',
                    'admin_url' => 'https://console.cloud.google.com',
                ];
            }

            // Azure detection
            if ($this->isAzure($ipAddresses, $httpHeaders)) {
                $detections[] = [
                    'provider' => 'Azure',
                    'confidence' => 'high',
                    'admin_url' => 'https://portal.azure.com',
                ];
            }

            // If IP-API provided a provider and we don't have a high-confidence detection, use it
            if ($ipApiProvider && empty($detections)) {
                return [
                    'provider' => $ipApiProvider,
                    'confidence' => 'medium',
                    'admin_url' => null,
                    'ip_api_data' => $ipApiData,
                ];
            }

            // If IP-API provider matches one of our detections, boost confidence
            if ($ipApiProvider && ! empty($detections)) {
                foreach ($detections as &$detection) {
                    if (stripos($ipApiProvider, $detection['provider']) !== false ||
                        stripos($detection['provider'], $ipApiProvider) !== false) {
                        $detection['confidence'] = 'high';
                        $detection['ip_api_data'] = $ipApiData;
                        break;
                    }
                }
            }

            // Return primary provider (first high confidence, or first if none)
            if (! empty($detections)) {
                // Prefer high confidence providers
                $highConfidence = array_filter($detections, fn ($d) => $d['confidence'] === 'high');
                if (! empty($highConfidence)) {
                    $result = reset($highConfidence);
                    if ($ipApiData && ! isset($result['ip_api_data'])) {
                        $result['ip_api_data'] = $ipApiData;
                    }

                    return $result;
                }

                $result = reset($detections);
                if ($ipApiData && ! isset($result['ip_api_data'])) {
                    $result['ip_api_data'] = $ipApiData;
                }

                return $result;
            }

            // If we have IP-API data but no other detection, use IP-API provider
            if ($ipApiProvider) {
                return [
                    'provider' => $ipApiProvider,
                    'confidence' => 'medium',
                    'admin_url' => null,
                    'ip_api_data' => $ipApiData,
                ];
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
     * Get IP addresses for domain
     *
     * @return array<int, string>
     */
    private function getIpAddresses(string $domain): array
    {
        $ipAddresses = [];

        try {
            // Get A records (IPv4)
            $aRecords = @dns_get_record($domain, DNS_A);
            if ($aRecords) {
                foreach ($aRecords as $record) {
                    if (isset($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP)) {
                        $ipAddresses[] = $record['ip'];
                    }
                }
            }

            // Also try gethostbyname as fallback
            $ip = @gethostbyname($domain);
            if ($ip && $ip !== $domain && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (! in_array($ip, $ipAddresses)) {
                    $ipAddresses[] = $ip;
                }
            }

            // Get AAAA records (IPv6)
            $aaaaRecords = @dns_get_record($domain, DNS_AAAA);
            if ($aaaaRecords) {
                foreach ($aaaaRecords as $record) {
                    if (isset($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $ipAddresses[] = $record['ipv6'];
                    }
                }
            }
        } catch (\Exception $e) {
            Log::debug('IP lookup failed', ['domain' => $domain, 'error' => $e->getMessage()]);
        }

        return array_unique($ipAddresses);
    }

    /**
     * Get reverse DNS (PTR) record for IP address
     */
    private function getReverseDns(string $ip): ?string
    {
        try {
            $hostname = @gethostbyaddr($ip);
            if ($hostname && $hostname !== $ip) {
                return $hostname;
            }
        } catch (\Exception $e) {
            Log::debug('Reverse DNS lookup failed', ['ip' => $ip, 'error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Check if IP belongs to a known hosting provider range
     *
     * @param  array<int, string>  $ipAddresses
     */
    private function checkIpRanges(array $ipAddresses, string $providerName): bool
    {
        foreach ($ipAddresses as $ip) {
            // Check reverse DNS first (often contains provider name)
            $reverseDns = $this->getReverseDns($ip);
            if ($reverseDns && stripos($reverseDns, $providerName) !== false) {
                return true;
            }

            // Check known IP ranges (simplified - could be expanded with full CIDR ranges)
            // This is a basic check; for production, you'd want a comprehensive IP range database
            if ($this->matchesIpRange($ip, $providerName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP matches known ranges for a provider
     * Note: This is simplified. For production, use a proper IP geolocation/ASN database
     */
    private function matchesIpRange(string $ip, string $providerName): bool
    {
        // Convert IP to long for range checking
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        // Known IP ranges (simplified examples - expand as needed)
        $ranges = [
            'digitalocean' => [
                ['start' => ip2long('104.131.0.0'), 'end' => ip2long('104.131.255.255')],
                ['start' => ip2long('159.89.0.0'), 'end' => ip2long('159.89.255.255')],
                ['start' => ip2long('167.99.0.0'), 'end' => ip2long('167.99.255.255')],
                ['start' => ip2long('188.166.0.0'), 'end' => ip2long('188.166.255.255')],
            ],
            'linode' => [
                ['start' => ip2long('45.33.0.0'), 'end' => ip2long('45.33.255.255')],
                ['start' => ip2long('50.116.0.0'), 'end' => ip2long('50.116.255.255')],
                ['start' => ip2long('172.104.0.0'), 'end' => ip2long('172.104.255.255')],
            ],
            'aws' => [
                // AWS has many ranges, this is just a sample
                ['start' => ip2long('3.0.0.0'), 'end' => ip2long('3.255.255.255')],
                ['start' => ip2long('52.0.0.0'), 'end' => ip2long('52.255.255.255')],
                ['start' => ip2long('54.0.0.0'), 'end' => ip2long('54.255.255.255')],
            ],
        ];

        $providerKey = strtolower(str_replace(' ', '', $providerName));
        if (! isset($ranges[$providerKey])) {
            return false;
        }

        foreach ($ranges[$providerKey] as $range) {
            if ($ipLong >= $range['start'] && $ipLong <= $range['end']) {
                return true;
            }
        }

        return false;
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
     * @param  array<int, string>  $ipAddresses
     */
    private function isVercel(array $dnsRecords, array $httpHeaders, array $ipAddresses = []): bool
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
     * @param  array<int, string>  $ipAddresses
     */
    private function isRender(array $dnsRecords, array $httpHeaders, array $ipAddresses = []): bool
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
     * @param  array<int, string>  $ipAddresses
     */
    private function isCloudflare(array $dnsRecords, array $httpHeaders, array $ipAddresses = []): bool
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
     * @param  array<int, string>  $ipAddresses
     */
    private function isAws(array $dnsRecords, array $httpHeaders, array $ipAddresses = []): bool
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

        // Check IP ranges
        if ($this->checkIpRanges($ipAddresses, 'aws')) {
            return true;
        }

        return false;
    }

    /**
     * Check if domain is hosted on Netlify
     *
     * @param  array<int, array<string, mixed>>  $dnsRecords
     * @param  array<string, array<int, string>|string>  $httpHeaders
     * @param  array<int, string>  $ipAddresses
     */
    private function isNetlify(array $dnsRecords, array $httpHeaders, array $ipAddresses = []): bool
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

    /**
     * Check if domain is hosted on DigitalOcean
     *
     * @param  array<int, string>  $ipAddresses
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isDigitalOcean(array $ipAddresses, array $httpHeaders): bool
    {
        // Check IP ranges
        if ($this->checkIpRanges($ipAddresses, 'digitalocean')) {
            return true;
        }

        // Check reverse DNS
        foreach ($ipAddresses as $ip) {
            $reverseDns = $this->getReverseDns($ip);
            if ($reverseDns && (stripos($reverseDns, 'digitalocean') !== false || stripos($reverseDns, 'droplet') !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain is hosted on Linode
     *
     * @param  array<int, string>  $ipAddresses
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isLinode(array $ipAddresses, array $httpHeaders): bool
    {
        // Check IP ranges
        if ($this->checkIpRanges($ipAddresses, 'linode')) {
            return true;
        }

        // Check reverse DNS
        foreach ($ipAddresses as $ip) {
            $reverseDns = $this->getReverseDns($ip);
            if ($reverseDns && (stripos($reverseDns, 'linode') !== false || stripos($reverseDns, 'members.linode.com') !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain is hosted on Google Cloud
     *
     * @param  array<int, string>  $ipAddresses
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isGoogleCloud(array $ipAddresses, array $httpHeaders): bool
    {
        // Check HTTP headers
        $server = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? [];
        $serverValue = is_array($server) ? ($server[0] ?? '') : $server;
        if (stripos($serverValue, 'gfe') !== false || stripos($serverValue, 'Google') !== false) {
            return true;
        }

        // Check reverse DNS
        foreach ($ipAddresses as $ip) {
            $reverseDns = $this->getReverseDns($ip);
            if ($reverseDns && (stripos($reverseDns, 'googleusercontent.com') !== false ||
                stripos($reverseDns, 'google.com') !== false ||
                stripos($reverseDns, 'cloud.google.com') !== false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if domain is hosted on Azure
     *
     * @param  array<int, string>  $ipAddresses
     * @param  array<string, array<int, string>|string>  $httpHeaders
     */
    private function isAzure(array $ipAddresses, array $httpHeaders): bool
    {
        // Check HTTP headers
        $server = $httpHeaders['Server'] ?? $httpHeaders['server'] ?? [];
        $serverValue = is_array($server) ? ($server[0] ?? '') : $server;
        if (stripos($serverValue, 'Microsoft-IIS') !== false || stripos($serverValue, 'Azure') !== false) {
            return true;
        }

        // Check reverse DNS
        foreach ($ipAddresses as $ip) {
            $reverseDns = $this->getReverseDns($ip);
            if ($reverseDns && (stripos($reverseDns, 'azurewebsites.net') !== false ||
                stripos($reverseDns, 'cloudapp.net') !== false ||
                stripos($reverseDns, 'azure.com') !== false)) {
                return true;
            }
        }

        return false;
    }
}
