<?php

namespace App\Services;

class HostingProviderUrls
{
    /**
     * Map of hosting provider names to their login/admin URLs
     * This is a best-guess mapping based on common provider names
     *
     * @var array<string, string>
     */
    private const PROVIDER_URLS = [
        // Major Cloud Providers
        'AWS' => 'https://console.aws.amazon.com',
        'Amazon Web Services' => 'https://console.aws.amazon.com',
        'Google Cloud' => 'https://console.cloud.google.com',
        'GCP' => 'https://console.cloud.google.com',
        'Azure' => 'https://portal.azure.com',
        'Microsoft Azure' => 'https://portal.azure.com',

        // Platform-as-a-Service
        'Vercel' => 'https://vercel.com/dashboard',
        'Render' => 'https://dashboard.render.com',
        'Netlify' => 'https://app.netlify.com',
        'Heroku' => 'https://dashboard.heroku.com',
        'Railway' => 'https://railway.app',
        'Fly.io' => 'https://fly.io/dashboard',

        // CDN & DNS
        'Cloudflare' => 'https://dash.cloudflare.com',
        'Cloudflare Pages' => 'https://dash.cloudflare.com',

        // VPS & Hosting
        'DigitalOcean' => 'https://cloud.digitalocean.com',
        'Linode' => 'https://cloud.linode.com',
        'Vultr' => 'https://my.vultr.com',
        'Hetzner' => 'https://console.hetzner.cloud',
        'OVH' => 'https://www.ovh.com/manager',
        'Scaleway' => 'https://console.scaleway.com',

        // Shared Hosting
        'cPanel' => 'https://cpanel.net',
        'Plesk' => 'https://www.plesk.com',
        'WHM' => 'https://cpanel.net',

        // Australian Hosting Providers
        'VentraIP' => 'https://www.ventraip.com.au/clientarea',
        'Synergy Wholesale' => 'https://www.synergywholesale.com/clientarea',
        'DreamIT Solutions' => 'https://www.dreamit.com.au',
        'Host Universal' => 'https://www.hostuniversal.com.au',
        'Crazy Domains' => 'https://www.crazydomains.com.au/clientarea',
        'VentraIP Australia' => 'https://www.ventraip.com.au/clientarea',

        // WordPress Hosting
        'WP Engine' => 'https://my.wpengine.com',
        'Kinsta' => 'https://my.kinsta.com',
        'Pantheon' => 'https://dashboard.pantheon.io',
        'WordPress.com' => 'https://wordpress.com',

        // Other Common Providers
        'GitHub Pages' => 'https://github.com',
        'GitLab Pages' => 'https://gitlab.com',
        'Surge.sh' => 'https://surge.sh',
        'Firebase' => 'https://console.firebase.google.com',
    ];

    /**
     * Get the suggested login/admin URL for a hosting provider
     *
     * @param  string|null  $providerName  Hosting provider name
     * @return string|null Suggested login URL or null if not found
     */
    public static function getLoginUrl(?string $providerName): ?string
    {
        if (empty($providerName)) {
            return null;
        }

        $providerName = trim($providerName);

        // Direct match
        if (isset(self::PROVIDER_URLS[$providerName])) {
            return self::PROVIDER_URLS[$providerName];
        }

        // Case-insensitive search
        $providerLower = strtolower($providerName);
        foreach (self::PROVIDER_URLS as $key => $url) {
            if (strtolower($key) === $providerLower) {
                return $url;
            }
        }

        // Partial match (e.g., "AWS EC2" matches "AWS")
        foreach (self::PROVIDER_URLS as $key => $url) {
            $keyLower = strtolower($key);
            if (str_contains($providerLower, $keyLower) || str_contains($keyLower, $providerLower)) {
                return $url;
            }
        }

        // Try to extract common patterns
        if (stripos($providerName, 'aws') !== false || stripos($providerName, 'amazon') !== false) {
            return self::PROVIDER_URLS['AWS'];
        }

        if (stripos($providerName, 'google') !== false || stripos($providerName, 'gcp') !== false) {
            return self::PROVIDER_URLS['Google Cloud'];
        }

        if (stripos($providerName, 'azure') !== false || stripos($providerName, 'microsoft') !== false) {
            return self::PROVIDER_URLS['Azure'];
        }

        if (stripos($providerName, 'cloudflare') !== false) {
            return self::PROVIDER_URLS['Cloudflare'];
        }

        if (stripos($providerName, 'digitalocean') !== false || stripos($providerName, 'digital ocean') !== false) {
            return self::PROVIDER_URLS['DigitalOcean'];
        }

        if (stripos($providerName, 'vercel') !== false || stripos($providerName, 'cypress communications') !== false || stripos($providerName, 'cypress') !== false) {
            return self::PROVIDER_URLS['Vercel'];
        }

        if (stripos($providerName, 'render') !== false) {
            return self::PROVIDER_URLS['Render'];
        }

        if (stripos($providerName, 'netlify') !== false) {
            return self::PROVIDER_URLS['Netlify'];
        }

        return null;
    }

    /**
     * Get all known provider URLs (for reference)
     *
     * @return array<string, string>
     */
    public static function getAllUrls(): array
    {
        return self::PROVIDER_URLS;
    }
}
