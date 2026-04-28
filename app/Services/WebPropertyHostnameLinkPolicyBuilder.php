<?php

namespace App\Services;

use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use Illuminate\Support\Str;

class WebPropertyHostnameLinkPolicyBuilder
{
    /**
     * @return array{
     *   owning_marketing_domain: string|null,
     *   hostnames: array<int, array{
     *     hostname: string,
     *     role: string,
     *     property_kind: string,
     *     controller_owner: string,
     *     expected_links: array{
     *       household_quote: array{status: string, url: string|null},
     *       vehicle_quote: array{status: string, url: string|null},
     *       booking: array{status: string, url: string|null},
     *       contact: array{status: string, url: string|null},
     *       customer_portal: array{status: string, url: string|null}
     *     }
     *   }>
     * }
     */
    public function build(WebProperty $property): array
    {
        $owningMarketingDomain = $property->primaryDomainName();
        $hostnames = collect($this->candidateHostnames($property, $owningMarketingDomain))
            ->map(fn (array $candidate): array => $this->buildHostnameSummary($property, $candidate, $owningMarketingDomain))
            ->sortBy(fn (array $entry): string => sprintf(
                '%d:%s',
                $entry['role'] === 'marketing_domain' ? 0 : 1,
                $entry['hostname']
            ))
            ->values()
            ->all();

        return [
            'owning_marketing_domain' => $owningMarketingDomain,
            'hostnames' => $hostnames,
        ];
    }

    /**
     * @return array<int, array{
     *   hostname: string,
     *   is_marketing_domain: bool,
     *   is_customer_portal: bool,
     *   is_operational_app_shell: bool,
     *   surface: WebPropertyConversionSurface|null
     * }>
     */
    private function candidateHostnames(WebProperty $property, ?string $owningMarketingDomain): array
    {
        /** @var array<string, array{
         *   hostname: string,
         *   is_marketing_domain: bool,
         *   is_customer_portal: bool,
         *   is_operational_app_shell: bool,
         *   surface: WebPropertyConversionSurface|null
         * }> $candidates
         */
        $candidates = [];

        $addCandidate = function (
            ?string $hostname,
            bool $isMarketingDomain = false,
            bool $isCustomerPortal = false,
            bool $isOperationalAppShell = false,
            ?WebPropertyConversionSurface $surface = null
        ) use (&$candidates): void {
            if (! is_string($hostname) || trim($hostname) === '') {
                return;
            }

            $normalized = Str::lower(trim($hostname, '.'));

            if ($normalized === '') {
                return;
            }

            $existing = $candidates[$normalized] ?? [
                'hostname' => $normalized,
                'is_marketing_domain' => false,
                'is_customer_portal' => false,
                'is_operational_app_shell' => false,
                'surface' => null,
            ];

            $existing['is_marketing_domain'] = $existing['is_marketing_domain'] || $isMarketingDomain;
            $existing['is_customer_portal'] = $existing['is_customer_portal'] || $isCustomerPortal;
            $existing['is_operational_app_shell'] = $existing['is_operational_app_shell'] || $isOperationalAppShell;
            $existing['surface'] = $existing['surface'] ?? $surface;

            $candidates[$normalized] = $existing;
        };

        $addCandidate($owningMarketingDomain, isMarketingDomain: true, isOperationalAppShell: $property->property_type === 'app');

        $conversionSurfaces = $property->relationLoaded('conversionSurfaces')
            ? $property->conversionSurfaces
            : $property->conversionSurfaces()->get();

        foreach ($conversionSurfaces as $surface) {
            $addCandidate(
                $surface->hostname,
                isCustomerPortal: $surface->surface_type === 'portal_subdomain',
                surface: $surface,
            );
        }

        $targetSummary = $property->conversionLinkSummary()['target'];

        $addCandidate($this->hostnameFromUrl($targetSummary['household_quote'] ?? null));
        $addCandidate($this->hostnameFromUrl($targetSummary['vehicle_quote'] ?? null));
        $addCandidate($this->hostnameFromUrl($targetSummary['moveroo_subdomain'] ?? null), isCustomerPortal: true);
        $addCandidate($this->hostnameFromUrl($targetSummary['legacy_bookings_replacement'] ?? null), isOperationalAppShell: true);
        $addCandidate($this->hostnameFromUrl($targetSummary['legacy_payments_replacement'] ?? null), isOperationalAppShell: true);
        $addCandidate($this->hostnameFromUrl($property->target_household_booking_url));
        $addCandidate($this->hostnameFromUrl($property->target_vehicle_booking_url));
        $addCandidate($this->hostnameFromUrl($property->target_contact_us_page_url));

        return array_values($candidates);
    }

    /**
     * @param  array{
     *   hostname: string,
     *   is_marketing_domain: bool,
     *   is_customer_portal: bool,
     *   is_operational_app_shell: bool,
     *   surface: WebPropertyConversionSurface|null
     * }  $candidate
     * @return array{
     *   hostname: string,
     *   role: string,
     *   property_kind: string,
     *   controller_owner: string,
     *   expected_links: array{
     *     household_quote: array{status: string, url: string|null},
     *     vehicle_quote: array{status: string, url: string|null},
     *     booking: array{status: string, url: string|null},
     *     contact: array{status: string, url: string|null},
     *     customer_portal: array{status: string, url: string|null}
     *   }
     * }
     */
    private function buildHostnameSummary(WebProperty $property, array $candidate, ?string $owningMarketingDomain): array
    {
        $hostname = $candidate['hostname'];
        $propertyKind = $this->propertyKind($property, $candidate);
        $role = $candidate['is_marketing_domain']
            ? 'marketing_domain'
            : ($candidate['is_customer_portal'] ? 'customer_portal_hostname' : 'quote_or_app_hostname');

        return [
            'hostname' => $hostname,
            'role' => $role,
            'property_kind' => $propertyKind,
            'controller_owner' => $this->controllerOwner($property, $candidate['surface'], $propertyKind),
            'expected_links' => [
                'household_quote' => $this->householdQuoteSlot($property, $hostname, $candidate['is_marketing_domain'], $propertyKind),
                'vehicle_quote' => $this->vehicleQuoteSlot($property, $hostname, $candidate['is_marketing_domain'], $propertyKind),
                'booking' => $this->bookingSlot($property, $hostname, $candidate['is_marketing_domain'], $propertyKind),
                'contact' => $this->contactSlot($property, $hostname, $candidate['is_marketing_domain'], $propertyKind, $owningMarketingDomain),
                'customer_portal' => $this->customerPortalSlot($property, $hostname, $candidate['is_marketing_domain'], $propertyKind),
            ],
        ];
    }

    /**
     * @param  array{
     *   hostname: string,
     *   is_marketing_domain: bool,
     *   is_customer_portal: bool,
     *   is_operational_app_shell: bool,
     *   surface: WebPropertyConversionSurface|null
     * }  $candidate
     */
    private function propertyKind(WebProperty $property, array $candidate): string
    {
        if ($candidate['is_marketing_domain']) {
            return $property->property_type === 'app'
                ? 'operational_app_shell_apex'
                : 'normal_marketing_site';
        }

        if ($candidate['is_customer_portal']) {
            return 'quote_conversion_surface';
        }

        if ($candidate['is_operational_app_shell']) {
            return 'operational_app_shell_apex';
        }

        return 'quote_conversion_surface';
    }

    private function controllerOwner(WebProperty $property, ?WebPropertyConversionSurface $surface, string $propertyKind): string
    {
        if ($propertyKind !== 'normal_marketing_site') {
            return 'App shell / quote runtime';
        }

        $repository = $property->controllerRepository();
        $executionSurface = $property->executionReadinessSummary()['execution_surface'] ?? null;

        if ($repository instanceof PropertyRepository && $repository->repo_name === '_wp-house') {
            return 'WordPress controller / _wp-house';
        }

        if ($executionSurface === 'astro_repo_controlled') {
            return 'Astro repo';
        }

        if ($executionSurface === 'repository_controlled' && $repository instanceof PropertyRepository) {
            return 'Repository controller';
        }

        if ($surface instanceof WebPropertyConversionSurface) {
            return 'App shell / quote runtime';
        }

        return 'parked / inventory / external';
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function householdQuoteSlot(WebProperty $property, string $hostname, bool $isMarketingDomain, string $propertyKind): array
    {
        $url = $property->target_household_quote_url;

        if ($propertyKind === 'operational_app_shell_apex' && $isMarketingDomain) {
            return $this->slot('suppressed');
        }

        if ($isMarketingDomain) {
            return $this->marketingSlot($url, 'required');
        }

        return $this->destinationSlot($hostname, $url);
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function vehicleQuoteSlot(WebProperty $property, string $hostname, bool $isMarketingDomain, string $propertyKind): array
    {
        $url = data_get($property->conversionLinkSummary(), 'target.vehicle_quote');

        if ($propertyKind === 'operational_app_shell_apex' && $isMarketingDomain) {
            return $this->slot('suppressed');
        }

        if ($isMarketingDomain) {
            return $this->marketingSlot($url, 'required');
        }

        return $this->destinationSlot($hostname, $url);
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function bookingSlot(WebProperty $property, string $hostname, bool $isMarketingDomain, string $propertyKind): array
    {
        $marketingUrl = $this->firstPresentUrl([
            $property->target_household_booking_url,
            $property->target_vehicle_booking_url,
            $property->target_legacy_bookings_replacement_url,
        ]);

        if ($isMarketingDomain) {
            if ($propertyKind === 'operational_app_shell_apex') {
                return $marketingUrl !== null
                    ? $this->slot('required', $marketingUrl)
                    : $this->slot('unknown');
            }

            return $marketingUrl !== null
                ? $this->slot('optional', $marketingUrl)
                : $this->slot('unknown');
        }

        $url = $this->firstHostMatchingUrl($hostname, [
            $property->target_household_booking_url,
            $property->target_vehicle_booking_url,
            $property->target_legacy_bookings_replacement_url,
        ]);

        return $this->destinationSlot($hostname, $url);
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function contactSlot(
        WebProperty $property,
        string $hostname,
        bool $isMarketingDomain,
        string $propertyKind,
        ?string $owningMarketingDomain
    ): array {
        $marketingContactUrl = $property->target_contact_us_page_url;

        if ($propertyKind === 'operational_app_shell_apex' && $isMarketingDomain) {
            return $this->slot('suppressed');
        }

        if ($isMarketingDomain) {
            if ($marketingContactUrl !== null && $this->hostnameFromUrl($marketingContactUrl) === $owningMarketingDomain) {
                return $this->slot('required', $marketingContactUrl);
            }

            return $this->slot('unknown');
        }

        $url = $this->firstHostMatchingUrl($hostname, [
            $property->target_contact_us_page_url,
            $property->target_legacy_payments_replacement_url,
        ]);

        return $this->destinationSlot($hostname, $url);
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function customerPortalSlot(WebProperty $property, string $hostname, bool $isMarketingDomain, string $propertyKind): array
    {
        $url = $property->target_moveroo_subdomain_url;

        if ($propertyKind === 'operational_app_shell_apex' && $isMarketingDomain && $url === null) {
            return $this->slot('suppressed');
        }

        if ($isMarketingDomain) {
            return $url !== null
                ? $this->slot('optional', $url)
                : $this->slot('unknown');
        }

        return $this->destinationSlot($hostname, $url);
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function marketingSlot(?string $url, string $requiredStatus): array
    {
        return $url !== null
            ? $this->slot($requiredStatus, $url)
            : $this->slot('unknown');
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function destinationSlot(string $hostname, ?string $url): array
    {
        if ($url === null) {
            return $this->slot('suppressed');
        }

        return $this->hostnameFromUrl($url) === $hostname
            ? $this->slot('required', $url)
            : $this->slot('suppressed');
    }

    /**
     * @return array{status: string, url: string|null}
     */
    private function slot(string $status, ?string $url = null): array
    {
        return [
            'status' => $status,
            'url' => $url,
        ];
    }

    private function hostnameFromUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));

        if (! is_array($parts) || ! isset($parts['host'])) {
            return null;
        }

        return Str::lower((string) $parts['host']);
    }

    /**
     * @param  array<int, string|null>  $urls
     */
    private function firstPresentUrl(array $urls): ?string
    {
        foreach ($urls as $url) {
            if (is_string($url) && trim($url) !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $urls
     */
    private function firstHostMatchingUrl(string $hostname, array $urls): ?string
    {
        foreach ($urls as $url) {
            if ($this->hostnameFromUrl($url) === $hostname) {
                return $url;
            }
        }

        return null;
    }
}
