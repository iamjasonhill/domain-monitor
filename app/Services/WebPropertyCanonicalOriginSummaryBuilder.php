<?php

namespace App\Services;

use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Support\Str;

class WebPropertyCanonicalOriginSummaryBuilder
{
    /**
     * @return array{
     *   scheme: string|null,
     *   host: string|null,
     *   base_url: string|null,
     *   policy: 'known'|'unknown',
     *   scope: 'property_only',
     *   enforcement_eligible: bool,
     *   owned_subdomains: array<int, string>,
     *   excluded_subdomains: array<int, string>,
     *   sitemap_policy_known: bool
     * }
     */
    public function build(WebProperty $property): array
    {
        $scheme = $this->canonicalOriginSchemeValue($property);
        $host = $this->canonicalOriginHostValue($property);
        $baseUrl = $scheme !== null && $host !== null ? $scheme.'://'.$host : null;
        $policy = $property->canonical_origin_policy === 'known' ? 'known' : 'unknown';
        $hasExplicitCanonicalOrigin = is_string($property->canonical_origin_scheme)
            && $property->canonical_origin_scheme !== ''
            && is_string($property->canonical_origin_host)
            && $property->canonical_origin_host !== '';

        return [
            'scheme' => $scheme,
            'host' => $host,
            'base_url' => $baseUrl,
            'policy' => $policy,
            'scope' => 'property_only',
            'enforcement_eligible' => (bool) $property->canonical_origin_enforcement_eligible
                && $policy === 'known'
                && $hasExplicitCanonicalOrigin,
            'owned_subdomains' => $this->ownedSubdomainHosts($property, $host),
            'excluded_subdomains' => $this->normalizedCanonicalOriginSubdomains($property->canonical_origin_excluded_subdomains),
            'sitemap_policy_known' => (bool) $property->canonical_origin_sitemap_policy_known,
        ];
    }

    private function canonicalOriginSchemeValue(WebProperty $property): ?string
    {
        if (is_string($property->canonical_origin_scheme) && $property->canonical_origin_scheme !== '') {
            return strtolower($property->canonical_origin_scheme);
        }

        $scheme = parse_url((string) $property->production_url, PHP_URL_SCHEME);

        return is_string($scheme) && $scheme !== '' ? strtolower($scheme) : null;
    }

    private function canonicalOriginHostValue(WebProperty $property): ?string
    {
        if (is_string($property->canonical_origin_host) && $property->canonical_origin_host !== '') {
            return strtolower($property->canonical_origin_host);
        }

        $host = parse_url((string) $property->production_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? strtolower($host) : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizedCanonicalOriginSubdomains(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $host): ?string => $this->normalizeCanonicalOriginHost($host))
            ->filter(fn (?string $host): bool => is_string($host) && $host !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function ownedSubdomainHosts(WebProperty $property, ?string $canonicalHost): array
    {
        if (! is_string($canonicalHost) || $canonicalHost === '') {
            return [];
        }

        return $property->orderedDomainLinks()
            ->map(fn (WebPropertyDomain $link): ?string => $this->normalizeCanonicalOriginHost($link->domain?->domain))
            ->filter(
                fn (?string $host): bool => is_string($host)
                    && $host !== ''
                    && $host !== $canonicalHost
                    && Str::endsWith($host, '.'.$canonicalHost)
            )
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeCanonicalOriginHost(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        if (Str::contains($trimmed, '://')) {
            $host = parse_url($trimmed, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? strtolower($host) : null;
        }

        return strtolower(rtrim($trimmed, '.'));
    }
}
