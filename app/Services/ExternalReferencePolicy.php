<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use Illuminate\Support\Str;

class ExternalReferencePolicy
{
    /**
     * @return array{
     *   classification: string,
     *   action: string,
     *   approved: bool,
     *   reason: string,
     *   category: string|null,
     *   registry_source: string|null,
     *   scope: string|null,
     *   policy_reference: string|null
     * }
     */
    public function classify(?string $host, ?string $sourceHost = null, ?WebProperty $property = null): array
    {
        $normalizedHost = $this->normalizedHost($host);
        $normalizedSourceHost = $this->normalizedHost($sourceHost);

        if ($normalizedHost === null) {
            return $this->result(
                classification: 'broken_unverified',
                action: 'broken_unverified',
                approved: false,
                reason: 'Outbound link host could not be parsed.',
            );
        }

        if ($this->hostMatches($normalizedHost, $this->configuredHosts('disallowed_hosts'))) {
            return $this->result(
                classification: 'disallowed',
                action: 'disallowed',
                approved: false,
                reason: 'Host is listed as a disallowed external reference.',
            );
        }

        $scopedEntry = $this->configuredScopedHostMatch($normalizedHost, $normalizedSourceHost, $property);
        if ($scopedEntry !== null) {
            return $this->result(
                classification: 'approved_scoped',
                action: 'approved',
                approved: true,
                reason: (string) ($scopedEntry['reason'] ?? 'Host is configured as an approved scoped external-link destination.'),
                category: is_string($scopedEntry['category'] ?? null) ? $scopedEntry['category'] : 'approved_external_reference',
                registrySource: is_string($scopedEntry['registry_source'] ?? null) ? $scopedEntry['registry_source'] : 'fleet_reviewed',
                scope: is_string($scopedEntry['scope'] ?? null) ? $scopedEntry['scope'] : 'property',
                policyReference: $this->policyReference($scopedEntry),
            );
        }

        $registryEntry = $this->configuredHostMatch($normalizedHost, 'approved_registry_hosts');
        if ($registryEntry !== null) {
            return $this->result(
                classification: 'approved_registry',
                action: 'approved',
                approved: true,
                reason: (string) ($registryEntry['reason'] ?? 'Host is configured as an approved external-link registry destination.'),
                category: is_string($registryEntry['category'] ?? null) ? $registryEntry['category'] : 'approved_external_reference',
                registrySource: is_string($registryEntry['registry_source'] ?? null) ? $registryEntry['registry_source'] : 'fleet_reviewed',
                scope: is_string($registryEntry['scope'] ?? null) ? $registryEntry['scope'] : 'global',
                policyReference: $this->policyReference($registryEntry),
            );
        }

        $partner = $this->configuredHostMatch($normalizedHost, 'approved_partner_hosts');
        if ($partner !== null) {
            return $this->result(
                classification: 'approved_partner',
                action: 'approved',
                approved: true,
                reason: (string) ($partner['reason'] ?? 'Host is configured as an approved partner reference.'),
                category: is_string($partner['category'] ?? null) ? $partner['category'] : 'partner',
            );
        }

        if ($this->isAuthorityHost($normalizedHost)) {
            return $this->result(
                classification: 'authority_reference',
                action: 'approved',
                approved: true,
                reason: 'Host is an approved authority or official reference.',
                category: $this->authorityCategory($normalizedHost),
            );
        }

        if ($this->isOperationalSurface($normalizedHost, $normalizedSourceHost, $property)) {
            return $this->result(
                classification: 'operational_surface',
                action: 'approved',
                approved: true,
                reason: 'Host is an operational surface for this property or estate.',
            );
        }

        if ($this->isOwnedEstateHost($normalizedHost)) {
            return $this->result(
                classification: 'owned_estate',
                action: 'approved',
                approved: true,
                reason: 'Host belongs to a known Domain Monitor property.',
            );
        }

        return $this->result(
            classification: 'review_required',
            action: 'review_required',
            approved: false,
            reason: 'Host is not yet classified by the external reference policy.',
        );
    }

    /**
     * @return array{
     *   classification: string,
     *   action: string,
     *   approved: bool,
     *   reason: string,
     *   category: string|null,
     *   registry_source: string|null,
     *   scope: string|null,
     *   policy_reference: string|null
     * }
     */
    public function classifyUrl(?string $url, ?string $sourceHost = null, ?WebProperty $property = null): array
    {
        $host = $this->hostFromUrl($url);

        if ($this->isAcceptedAppHandoffUrl($url, $sourceHost, $property)) {
            return $this->result(
                classification: 'accepted_app_handoff',
                action: 'approved',
                approved: true,
                reason: 'URL is an accepted quote, booking, contact, or app handoff for this property.',
                category: 'app_handoff',
                registrySource: 'property_runtime_policy',
                scope: 'property',
            );
        }

        return $this->classify($host, $sourceHost, $property);
    }

    /**
     * @return array{
     *   classification: string,
     *   action: string,
     *   approved: bool,
     *   reason: string,
     *   category: string|null,
     *   registry_source: string|null,
     *   scope: string|null,
     *   policy_reference: string|null
     * }
     */
    private function result(
        string $classification,
        string $action,
        bool $approved,
        string $reason,
        ?string $category = null,
        ?string $registrySource = null,
        ?string $scope = null,
        ?string $policyReference = null,
    ): array {
        return [
            'classification' => $classification,
            'action' => $action,
            'approved' => $approved,
            'reason' => $reason,
            'category' => $category,
            'registry_source' => $registrySource,
            'scope' => $scope,
            'policy_reference' => $policyReference,
        ];
    }

    private function isOperationalSurface(string $host, ?string $sourceHost, ?WebProperty $property): bool
    {
        if ($sourceHost !== null && ($this->isSubdomainOf($host, $sourceHost) || $this->isSubdomainOf($sourceHost, $host))) {
            return true;
        }

        if (! $property instanceof WebProperty) {
            return false;
        }

        $configuredHosts = collect([
            $this->hostFromUrl($property->target_household_quote_url),
            $this->hostFromUrl($property->target_household_booking_url),
            $this->hostFromUrl($property->target_vehicle_quote_url),
            $this->hostFromUrl($property->target_vehicle_booking_url),
            $this->hostFromUrl($property->target_moveroo_subdomain_url),
            $this->hostFromUrl($property->target_contact_us_page_url),
            $this->hostFromUrl($property->target_legacy_bookings_replacement_url),
            $this->hostFromUrl($property->target_legacy_payments_replacement_url),
        ])
            ->filter()
            ->values()
            ->all();

        if (in_array($host, $configuredHosts, true)) {
            return true;
        }

        $surfaces = $property->relationLoaded('conversionSurfaces')
            ? $property->conversionSurfaces
            : ($property->exists ? $property->conversionSurfaces()->get() : collect());

        return $surfaces->contains(fn (WebPropertyConversionSurface $surface): bool => $this->normalizedHost($surface->hostname) === $host);
    }

    private function isAcceptedAppHandoffUrl(mixed $url, ?string $sourceHost, ?WebProperty $property): bool
    {
        if (! is_string($url) || trim($url) === '') {
            return false;
        }

        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return false;
        }

        $host = $this->normalizedHost($parts['host'] ?? null);
        if ($host === null) {
            return false;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');
        if (! $this->isAcceptedAppHandoffPath($path)) {
            return false;
        }

        $normalizedSourceHost = $this->normalizedHost($sourceHost);
        if ($normalizedSourceHost !== null && $host === 'quoting.'.$normalizedSourceHost) {
            return true;
        }

        if (! $property instanceof WebProperty) {
            return false;
        }

        $configuredHosts = $this->configuredOperationalHosts($property);
        if (in_array($host, $configuredHosts, true)) {
            return true;
        }

        $surfaces = $property->relationLoaded('conversionSurfaces')
            ? $property->conversionSurfaces
            : ($property->exists ? $property->conversionSurfaces()->get() : collect());

        return $surfaces->contains(fn (WebPropertyConversionSurface $surface): bool => $this->normalizedHost($surface->hostname) === $host);
    }

    private function isAcceptedAppHandoffPath(string $path): bool
    {
        $normalizedPath = '/'.ltrim(Str::lower($path), '/');

        return Str::startsWith($normalizedPath, [
            '/booking',
            '/bookings',
            '/contact',
            '/quote',
            '/customer',
            '/login',
            '/portal',
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function configuredOperationalHosts(WebProperty $property): array
    {
        return collect([
            $this->hostFromUrl($property->current_household_quote_url),
            $this->hostFromUrl($property->current_household_booking_url),
            $this->hostFromUrl($property->current_vehicle_quote_url),
            $this->hostFromUrl($property->current_vehicle_booking_url),
            $this->hostFromUrl($property->target_household_quote_url),
            $this->hostFromUrl($property->target_household_booking_url),
            $this->hostFromUrl($property->target_vehicle_quote_url),
            $this->hostFromUrl($property->target_vehicle_booking_url),
            $this->hostFromUrl($property->target_moveroo_subdomain_url),
            $this->hostFromUrl($property->target_contact_us_page_url),
            $this->hostFromUrl($property->target_legacy_bookings_replacement_url),
            $this->hostFromUrl($property->target_legacy_payments_replacement_url),
        ])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isOwnedEstateHost(string $host): bool
    {
        return Domain::query()
            ->where(function ($query) use ($host): void {
                $query->where('domain', $host);

                $parent = $this->parentRegistrableDomain($host);
                if ($parent !== null) {
                    $query->orWhere('domain', $parent);
                }
            })
            ->exists();
    }

    private function isAuthorityHost(string $host): bool
    {
        if ($host === 'gov.au' || Str::endsWith($host, '.gov.au')) {
            return true;
        }

        return $this->hostMatches($host, $this->configuredHosts('authority_reference_hosts'));
    }

    private function authorityCategory(string $host): string
    {
        if ($host === 'gov.au' || Str::endsWith($host, '.gov.au')) {
            return 'government';
        }

        return 'authority';
    }

    /**
     * @return array<int, string>
     */
    private function configuredHosts(string $key): array
    {
        $configured = config('domain_monitor.external_reference_policy.'.$key, []);

        if (! is_array($configured)) {
            return [];
        }

        return collect($configured)
            ->map(function (mixed $value): ?string {
                if (is_string($value)) {
                    return $this->normalizedHost($value);
                }

                if (is_array($value) && is_string($value['host'] ?? null)) {
                    return $this->normalizedHost($value['host']);
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredHostMatch(string $host, string $key): ?array
    {
        $configured = config('domain_monitor.external_reference_policy.'.$key, []);

        if (! is_array($configured)) {
            return null;
        }

        foreach ($configured as $entry) {
            $entryHost = is_array($entry) && is_string($entry['host'] ?? null)
                ? $this->normalizedHost($entry['host'])
                : (is_string($entry) ? $this->normalizedHost($entry) : null);

            if ($entryHost === null || ! $this->hostMatches($host, [$entryHost])) {
                continue;
            }

            return is_array($entry) ? $entry : ['host' => $entryHost];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredScopedHostMatch(string $host, ?string $sourceHost, ?WebProperty $property): ?array
    {
        $configured = config('domain_monitor.external_reference_policy.approved_scoped_hosts', []);

        if (! is_array($configured)) {
            return null;
        }

        foreach ($configured as $entry) {
            if (! is_array($entry) || ! is_string($entry['host'] ?? null)) {
                continue;
            }

            $entryHost = $this->normalizedHost($entry['host']);
            if ($entryHost === null || ! $this->hostMatches($host, [$entryHost])) {
                continue;
            }

            if ($this->scopedEntryMatches($entry, $sourceHost, $property)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function scopedEntryMatches(array $entry, ?string $sourceHost, ?WebProperty $property): bool
    {
        $propertySlugs = $this->configuredStrings($entry['property_slugs'] ?? [])->all();
        if ($property instanceof WebProperty && in_array($property->slug, $propertySlugs, true)) {
            return true;
        }

        $sourceHosts = $this->configuredStrings($entry['source_hosts'] ?? [])
            ->map(fn (string $host): ?string => $this->normalizedHost($host))
            ->filter()
            ->values()
            ->all();

        return $sourceHost !== null && $this->hostMatches($sourceHost, $sourceHosts);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function configuredStrings(mixed $value): \Illuminate\Support\Collection
    {
        return collect(is_array($value) ? $value : [$value])
            ->filter(fn (mixed $item): bool => is_string($item) && trim($item) !== '')
            ->map(fn (mixed $item): string => trim((string) $item))
            ->values();
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function policyReference(array $entry): ?string
    {
        if (is_string($entry['policy_reference'] ?? null) && $entry['policy_reference'] !== '') {
            return $entry['policy_reference'];
        }

        $fleetIssue = config('domain_monitor.external_reference_policy.policy_standard.fleet_issue');

        return is_string($fleetIssue) && $fleetIssue !== '' ? $fleetIssue : null;
    }

    /**
     * @param  array<int, string>  $configuredHosts
     */
    private function hostMatches(string $host, array $configuredHosts): bool
    {
        foreach ($configuredHosts as $configuredHost) {
            if ($host === $configuredHost || $this->isSubdomainOf($host, $configuredHost)) {
                return true;
            }
        }

        return false;
    }

    private function isSubdomainOf(string $host, string $parentHost): bool
    {
        return Str::endsWith($host, '.'.$parentHost);
    }

    private function hostFromUrl(mixed $url): ?string
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $host = parse_url(trim($url), PHP_URL_HOST);

        return is_string($host) ? $this->normalizedHost($host) : null;
    }

    private function normalizedHost(mixed $host): ?string
    {
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $host = Str::lower(trim($host));

        if (Str::startsWith($host, ['http://', 'https://'])) {
            $parsedHost = parse_url($host, PHP_URL_HOST);
            $host = is_string($parsedHost) ? Str::lower($parsedHost) : $host;
        }

        return rtrim($host, '.');
    }

    private function parentRegistrableDomain(string $host): ?string
    {
        $parts = explode('.', $host);

        if (count($parts) < 3) {
            return null;
        }

        $lastTwo = implode('.', array_slice($parts, -2));
        $lastThree = implode('.', array_slice($parts, -3));

        if (in_array($lastTwo, ['com.au', 'net.au', 'org.au', 'gov.au', 'edu.au'], true)) {
            return $lastThree;
        }

        return $lastTwo;
    }
}
