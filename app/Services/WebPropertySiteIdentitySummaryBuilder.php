<?php

namespace App\Services;

use App\Models\WebProperty;
use Illuminate\Support\Str;

class WebPropertySiteIdentitySummaryBuilder
{
    /**
     * @return array{
     *   site_name: string|null,
     *   legal_name: string|null,
     *   primary_domain: string|null,
     *   quote_portal: string|null,
     *   contact_page: string|null
     * }
     */
    public function build(WebProperty $property): array
    {
        return [
            'site_name' => $this->siteName($property),
            'legal_name' => $this->normalizedText($property->site_identity_legal_name),
            'primary_domain' => $this->normalizedOriginUrl(
                data_get($property->canonicalOriginSummary(), 'base_url')
                    ?? $property->production_url
            ),
            'quote_portal' => $this->quotePortal($property),
            'contact_page' => $this->normalizedPageUrl($property->target_contact_us_page_url),
        ];
    }

    private function siteName(WebProperty $property): ?string
    {
        $explicit = $this->normalizedText($property->site_identity_site_name);

        if ($explicit !== null) {
            return $explicit;
        }

        $fallback = $this->normalizedText($property->name);

        if ($fallback === null) {
            return null;
        }

        return preg_replace('/\s+(website|site)$/i', '', $fallback) ?: $fallback;
    }

    private function quotePortal(WebProperty $property): ?string
    {
        $candidate = $property->target_moveroo_subdomain_url
            ?? $property->target_household_quote_url
            ?? $property->target_household_booking_url
            ?? $property->target_vehicle_quote_url
            ?? $property->target_vehicle_booking_url;

        return $this->normalizedOriginUrl($candidate);
    }

    private function normalizedText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizedOriginUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/';
    }

    private function normalizedPageUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $normalized = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $path = (string) ($parts['path'] ?? '/');

        return $normalized.($path !== '' ? $path : '/');
    }
}
