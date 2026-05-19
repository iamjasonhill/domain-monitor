<?php

namespace App\Services;

use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyEventContract;
use Illuminate\Support\Str;

class PublishedBrandSurfaceFeedBuilder
{
    public function __construct(private readonly BrandStyleSurfaceDraftBuilder $brandStyleDrafts) {}

    /**
     * @return array{
     *   source_system: string,
     *   contract_version: int,
     *   snapshot_id: string,
     *   published_at: string,
     *   generated_by: string,
     *   notes: array<int, string>,
     *   pilot: array{host_allowlist: array<int, string>, scope: string},
     *   surfaces: array<int, array<string, mixed>>
     * }
     */
    public function build(?string $hostname = null): array
    {
        $publishedAt = now();
        $allowlist = $this->pilotHostAllowlist();
        $requestedHostname = $this->normalizeHostname($hostname);
        $hostnames = $requestedHostname === null
            ? $allowlist
            : array_values(array_intersect($allowlist, [$requestedHostname]));

        return [
            'source_system' => 'domain-monitor-published-brand-surfaces',
            'contract_version' => 1,
            'snapshot_id' => 'pbs-'.$publishedAt->utc()->format('Ymd\THis\Z'),
            'published_at' => $publishedAt->toIso8601String(),
            'generated_by' => 'domain-monitor.published-brand-surfaces',
            'notes' => [
                'Pilot-only export. Hostnames outside pilot.host_allowlist are intentionally omitted.',
                'Read-only payload for MoverooCombined fallback-first import; no live website or DNS change is implied.',
            ],
            'pilot' => [
                'host_allowlist' => $allowlist,
                'scope' => 'moveroo_v1_pilot',
            ],
            'surfaces' => $this->surfacesForHostnames($hostnames),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function pilotHostAllowlist(): array
    {
        $allowlist = config('domain_monitor.published_brand_surfaces.pilot_host_allowlist', []);

        if (! is_array($allowlist)) {
            return [];
        }

        return collect($allowlist)
            ->map(fn (mixed $hostname): ?string => $this->normalizeHostname($hostname))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $hostnames
     * @return array<int, array<string, mixed>>
     */
    private function surfacesForHostnames(array $hostnames): array
    {
        if ($hostnames === []) {
            return [];
        }

        $surfaces = WebPropertyConversionSurface::query()
            ->whereIn('hostname', $hostnames)
            ->with([
                'domain',
                'analyticsSource',
                'eventContractAssignment.eventContract',
                'webProperty.primaryDomain',
                'webProperty.propertyDomains.domain',
                'webProperty.repositories',
                'webProperty.analyticsSources',
                'webProperty.eventContractAssignments.eventContract',
            ])
            ->orderBy('hostname')
            ->get()
            ->keyBy(fn (WebPropertyConversionSurface $surface): string => (string) $this->normalizeHostname($surface->hostname));

        $targetProperties = WebProperty::query()
            ->where('status', 'active')
            ->whereNotNull('target_moveroo_subdomain_url')
            ->with([
                'primaryDomain',
                'propertyDomains.domain',
                'repositories',
                'analyticsSources',
                'eventContractAssignments.eventContract',
            ])
            ->orderBy('slug')
            ->get()
            ->mapWithKeys(function (WebProperty $property): array {
                $hostname = $this->normalizeHostname($property->target_moveroo_subdomain_url);

                return $hostname === null ? [] : [$hostname => $property];
            });

        $configuredProperties = $this->configuredPropertySlugsForHostnames($hostnames);

        return collect($hostnames)
            ->map(fn (string $hostname): ?array => $this->surfaceRecord($hostname, $surfaces->get($hostname))
                ?? $this->targetPropertyRecord($hostname, $targetProperties->get($hostname))
                ?? $this->configuredPropertyRecord($hostname, $configuredProperties->get($hostname)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function surfaceRecord(string $hostname, ?WebPropertyConversionSurface $surface): ?array
    {
        if (! $surface instanceof WebPropertyConversionSurface) {
            return null;
        }

        $property = $surface->webProperty;
        if (! $property instanceof WebProperty || $property->status !== 'active') {
            return null;
        }

        $metadata = $this->metadataForHostname($hostname);
        $repository = $property->controllerRepository();
        $analyticsSource = $this->analyticsSource($property, $surface);
        $eventAssignment = $this->eventAssignment($property, $surface);
        $canonicalHostname = $this->canonicalHostname($hostname, $metadata);
        $updatedAt = $surface->verified_at ?? $property->updated_at ?? now();
        $journeyType = $surface->journey_type;

        return $this->withBrandStyleSource($hostname, [
            'hostname' => $hostname,
            'property_slug' => $property->slug,
            'surface_slug' => $metadata['surface_slug'] ?? $this->defaultSurfaceSlug($property, $hostname),
            'status' => $this->surfaceStatus($surface),
            'surface_type' => $metadata['surface_type'] ?? $this->surfaceType($surface),
            'canonical_role' => $metadata['canonical_role'] ?? 'primary',
            'updated_at' => $updatedAt->toIso8601String(),
            'canonical_hostname' => $canonicalHostname,
            'linked_hostnames' => $this->linkedHostnames($hostname, $canonicalHostname),
            'owning_marketing_domain' => $metadata['owning_marketing_domain'] ?? $property->primaryDomainName(),
            'controller_owner' => 'domain-monitor',
            'controller_repo' => $repository?->repo_name,
            'ownership' => $this->ownership($repository),
            'brand' => $this->brand($property, $metadata),
            'copy' => $this->copy($property, $journeyType, $metadata),
            'theme' => $this->theme($property, $metadata),
            'navigation' => $this->navigation($journeyType, $metadata),
            'behavior' => $this->behavior(),
            'links' => $this->links($property, $journeyType, $metadata),
            'contact' => $this->contact($metadata),
            'analytics' => $this->analytics($property, $hostname, $journeyType, $analyticsSource, $eventAssignment),
            'provenance' => $this->provenance($metadata),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function targetPropertyRecord(string $hostname, ?WebProperty $property): ?array
    {
        if (! $property instanceof WebProperty || $property->status !== 'active') {
            return null;
        }

        $metadata = $this->metadataForHostname($hostname);
        $repository = $property->controllerRepository();
        $analyticsSource = $property->primaryAnalyticsSource('ga4');
        $eventAssignment = $property->primaryEventContractAssignment();
        $canonicalHostname = $this->canonicalHostname($hostname, $metadata);
        $updatedAt = $property->updated_at ?? now();
        $journeyType = 'mixed_quote';

        return $this->withBrandStyleSource($hostname, [
            'hostname' => $hostname,
            'property_slug' => $property->slug,
            'surface_slug' => $metadata['surface_slug'] ?? $this->defaultSurfaceSlug($property, $hostname),
            'status' => 'published',
            'surface_type' => $metadata['surface_type'] ?? 'quote',
            'canonical_role' => $metadata['canonical_role'] ?? 'primary',
            'updated_at' => $updatedAt->toIso8601String(),
            'canonical_hostname' => $canonicalHostname,
            'linked_hostnames' => $this->linkedHostnames($hostname, $canonicalHostname),
            'owning_marketing_domain' => $metadata['owning_marketing_domain'] ?? $property->primaryDomainName(),
            'controller_owner' => 'domain-monitor',
            'controller_repo' => $repository?->repo_name,
            'ownership' => $this->ownership($repository),
            'brand' => $this->brand($property, $metadata),
            'copy' => $this->copy($property, $journeyType, $metadata),
            'theme' => $this->theme($property, $metadata),
            'navigation' => $this->navigation($journeyType, $metadata),
            'behavior' => $this->behavior(),
            'links' => $this->links($property, $journeyType, $metadata),
            'contact' => $this->contact($metadata),
            'analytics' => $this->analytics($property, $hostname, $journeyType, $analyticsSource, $eventAssignment),
            'provenance' => $this->provenance($metadata),
        ]);
    }

    /**
     * @param  array<int, string>  $hostnames
     * @return \Illuminate\Support\Collection<string, WebProperty>
     */
    private function configuredPropertySlugsForHostnames(array $hostnames): \Illuminate\Support\Collection
    {
        $metadataByHostname = collect($hostnames)
            ->mapWithKeys(function (string $hostname): array {
                $metadata = $this->metadataForHostname($hostname);
                $propertySlug = $metadata['property_slug'] ?? null;

                return is_string($propertySlug) && trim($propertySlug) !== ''
                    ? [$hostname => trim($propertySlug)]
                    : [];
            });

        if ($metadataByHostname->isEmpty()) {
            return collect();
        }

        $properties = WebProperty::query()
            ->where('status', 'active')
            ->whereIn('slug', $metadataByHostname->values()->unique()->all())
            ->with([
                'primaryDomain',
                'propertyDomains.domain',
                'repositories',
                'analyticsSources',
                'eventContractAssignments.eventContract',
            ])
            ->get()
            ->keyBy('slug');

        return $metadataByHostname
            ->map(fn (string $propertySlug): ?WebProperty => $properties->get($propertySlug))
            ->filter();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configuredPropertyRecord(string $hostname, ?WebProperty $property): ?array
    {
        if (! $property instanceof WebProperty || $property->status !== 'active') {
            return null;
        }

        $metadata = $this->metadataForHostname($hostname);
        $repository = $property->controllerRepository();
        $analyticsSource = $property->primaryAnalyticsSource('ga4');
        $eventAssignment = $property->primaryEventContractAssignment();
        $canonicalHostname = $this->canonicalHostname($hostname, $metadata);
        $updatedAt = $property->updated_at ?? now();
        $journeyType = is_string($metadata['journey_type'] ?? null) ? $metadata['journey_type'] : null;

        return $this->withBrandStyleSource($hostname, [
            'hostname' => $hostname,
            'property_slug' => $property->slug,
            'surface_slug' => $metadata['surface_slug'] ?? $this->defaultSurfaceSlug($property, $hostname),
            'status' => 'published',
            'surface_type' => $metadata['surface_type'] ?? 'quote',
            'canonical_role' => $metadata['canonical_role'] ?? 'primary',
            'updated_at' => $updatedAt->toIso8601String(),
            'canonical_hostname' => $canonicalHostname,
            'linked_hostnames' => $this->linkedHostnames($hostname, $canonicalHostname),
            'owning_marketing_domain' => $metadata['owning_marketing_domain'] ?? $property->primaryDomainName(),
            'controller_owner' => 'domain-monitor',
            'controller_repo' => $repository?->repo_name,
            'ownership' => $this->ownership($repository),
            'brand' => $this->brand($property, $metadata),
            'copy' => $this->copy($property, $journeyType, $metadata),
            'theme' => $this->theme($property, $metadata),
            'navigation' => $this->navigation($journeyType, $metadata),
            'behavior' => $this->behavior(),
            'links' => $this->links($property, $journeyType, $metadata),
            'contact' => $this->contact($metadata),
            'analytics' => $this->analytics($property, $hostname, $journeyType, $analyticsSource, $eventAssignment),
            'provenance' => $this->provenance($metadata),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function metadataForHostname(string $hostname): array
    {
        $hostnames = config('domain_monitor.published_brand_surfaces.hostnames', []);
        $metadata = is_array($hostnames) ? ($hostnames[$hostname] ?? []) : [];

        return is_array($metadata) ? $metadata : [];
    }

    private function analyticsSource(WebProperty $property, WebPropertyConversionSurface $surface): ?PropertyAnalyticsSource
    {
        if ($surface->analytics_binding_mode !== 'inherits_property' && $surface->analyticsSource instanceof PropertyAnalyticsSource) {
            return $surface->analyticsSource;
        }

        return $property->primaryAnalyticsSource('ga4');
    }

    private function eventAssignment(WebProperty $property, WebPropertyConversionSurface $surface): ?WebPropertyEventContract
    {
        if ($surface->event_contract_binding_mode !== 'inherits_property' && $surface->eventContractAssignment instanceof WebPropertyEventContract) {
            return $surface->eventContractAssignment;
        }

        return $property->primaryEventContractAssignment();
    }

    private function surfaceStatus(WebPropertyConversionSurface $surface): string
    {
        return in_array($surface->rollout_status, ['paused', 'retired'], true)
            ? $surface->rollout_status
            : 'published';
    }

    private function surfaceType(WebPropertyConversionSurface $surface): string
    {
        return match ($surface->journey_type) {
            'vehicle_quote' => 'quote',
            default => str_contains($surface->surface_type, 'portal') ? 'portal' : 'quote',
        };
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function canonicalHostname(string $hostname, array $metadata): string
    {
        return $this->normalizeHostname($metadata['canonical_hostname'] ?? null) ?? $hostname;
    }

    /**
     * @return array<int, string>
     */
    private function linkedHostnames(string $hostname, string $canonicalHostname): array
    {
        return collect([$hostname, $canonicalHostname])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, string|null>
     */
    private function ownership(?PropertyRepository $repository): array
    {
        return [
            'published_truth_owner' => 'Domain Monitor',
            'runtime_renderer_owner' => 'MoverooCombined',
            'site_repo_owner' => $repository?->repo_name,
            'portfolio_routing_owner' => 'Bossman',
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function brand(WebProperty $property, array $metadata): array
    {
        $brand = $metadata['brand'] ?? [];
        $displayName = $brand['display_name'] ?? $property->site_identity_site_name ?? $property->name;
        $brandKey = $brand['brand_key'] ?? $property->siteKey() ?? $property->slug;

        return array_filter([
            'display_name' => $displayName,
            'brand_key' => $brandKey,
            'legal_name' => $brand['legal_name'] ?? $property->site_identity_legal_name,
            'tagline' => $brand['tagline'] ?? null,
            'mark_text' => $brand['mark_text'] ?? Str::upper(Str::substr((string) $displayName, 0, 1)),
            'logo_url' => $brand['logo_url'] ?? null,
            'logo_alt' => $brand['logo_alt'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function copy(WebProperty $property, ?string $journeyType, array $metadata): array
    {
        $copy = $metadata['copy'] ?? [];
        $displayName = (string) ($metadata['brand']['display_name'] ?? $property->site_identity_site_name ?? $property->name);
        $isVehicle = $journeyType === 'vehicle_quote';

        return [
            'eyebrow' => $copy['eyebrow'] ?? ($isVehicle ? 'Vehicle Quote' : 'Moving Quote'),
            'headline' => $copy['headline'] ?? sprintf('Get your %s quote', $isVehicle ? 'vehicle transport' : 'moving'),
            'subheading' => $copy['subheading'] ?? sprintf('Tell %s what you need and we will prepare the next step.', $displayName),
            'primary_cta_label' => $copy['primary_cta_label'] ?? ($isVehicle ? 'Start your vehicle quote' : 'Start your quote'),
            'secondary_cta_label' => $copy['secondary_cta_label'] ?? 'Contact us',
            'footer_blurb' => $copy['footer_blurb'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function theme(WebProperty $property, array $metadata): array
    {
        $theme = $metadata['theme'] ?? [];

        return array_replace_recursive([
            'theme_key' => $property->siteKey() ?? $property->slug,
            'mode' => 'auto',
            'fonts' => [
                'body_family' => 'Inter',
                'heading_family' => 'Inter',
            ],
            'colors' => [
                'accent' => '#2563eb',
                'accent_strong' => '#1d4ed8',
                'background' => '#ffffff',
                'text' => '#111827',
                'muted_text' => '#4b5563',
                'surface' => '#f8fafc',
                'border' => '#dbeafe',
            ],
            'radius_scale' => 'rounded',
            'shadow_style' => 'soft',
            'exact_tokens' => [],
        ], is_array($theme) ? $theme : []);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, bool>
     */
    private function navigation(?string $journeyType, array $metadata): array
    {
        $navigation = $metadata['navigation'] ?? [];
        $isVehicle = $journeyType === 'vehicle_quote';

        return array_replace([
            'show_household_quote_link' => ! $isVehicle,
            'show_vehicle_quote_link' => $isVehicle,
            'show_booking_link' => ! $isVehicle,
            'show_contact_link' => true,
            'show_customer_portal_link' => ! $isVehicle,
            'show_customer_portal_in_header' => false,
            'show_provider_login_link' => false,
            'show_admin_link' => false,
        ], is_array($navigation) ? $navigation : []);
    }

    /**
     * @return array<string, bool>
     */
    private function behavior(): array
    {
        return [
            'show_phone_publicly' => true,
            'show_email_publicly' => true,
            'prefer_contact_page_over_direct_contact' => false,
            'allow_customer_portal_links' => true,
            'allow_provider_login_links' => false,
            'allow_admin_links' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string|null>
     */
    private function links(WebProperty $property, ?string $journeyType, array $metadata): array
    {
        $links = $metadata['links'] ?? [];
        $isVehicle = $journeyType === 'vehicle_quote';

        return array_filter(array_replace([
            'primary_cta_route' => $isVehicle ? 'vehicle.quote' : 'household.quote',
            'primary_cta_url' => $isVehicle ? '/quote/vehicle' : '/quote/household',
            'household_quote_url' => $isVehicle ? null : ($property->target_household_quote_url ?? '/quote/household'),
            'vehicle_quote_url' => $isVehicle ? ($property->target_vehicle_quote_url ?? '/quote/vehicle') : null,
            'booking_url' => $isVehicle ? null : ($property->target_household_booking_url ?? '/booking/create'),
            'contact_url' => $property->target_contact_us_page_url ?? '/contact',
            'customer_portal_url' => $isVehicle ? null : '/customer/login',
            'support_url' => '/contact',
        ], is_array($links) ? $links : []), fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string|null>
     */
    private function contact(array $metadata): array
    {
        $contact = $metadata['contact'] ?? [];

        return array_filter([
            'public_phone' => $contact['public_phone'] ?? null,
            'public_email' => $contact['public_email'] ?? null,
            'facebook_url' => $contact['facebook_url'] ?? null,
            'instagram_url' => $contact['instagram_url'] ?? null,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function analytics(
        WebProperty $property,
        string $hostname,
        ?string $journeyType,
        ?PropertyAnalyticsSource $analyticsSource,
        ?WebPropertyEventContract $eventAssignment
    ): array {
        $analyticsConfig = $analyticsSource?->provider_config;
        $eventContract = $eventAssignment?->eventContract;

        return [
            'status' => $analyticsSource instanceof PropertyAnalyticsSource ? 'linked' : 'missing',
            'runtime_context_key' => $hostname,
            'property_slug' => $property->slug,
            'site_key' => $property->siteKey(),
            'journey_type' => $journeyType,
            'ga4' => [
                'measurement_id' => is_array($analyticsConfig) ? ($analyticsConfig['measurement_id'] ?? null) : null,
            ],
            'event_contract' => [
                'key' => $eventContract?->key,
                'version' => $eventContract?->version,
                'rollout_status' => $eventAssignment?->rollout_status,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, string|null>
     */
    private function provenance(array $metadata): array
    {
        $provenance = $metadata['provenance'] ?? [];

        return [
            'approved_by' => $provenance['approved_by'] ?? 'domain-monitor',
            'approved_at' => $provenance['approved_at'] ?? now()->toIso8601String(),
            'source' => $provenance['source'] ?? 'domain_monitor',
            'change_ref' => $provenance['change_ref'] ?? 'domain-monitor#208',
            'source_marketing_url' => $provenance['source_marketing_url'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function brandStyleSource(string $hostname): ?array
    {
        $approvedMetadata = $this->brandStyleDrafts->approvedMetadataByHostname();

        return $approvedMetadata[$hostname] ?? null;
    }

    /**
     * @param  array<string, mixed>  $surface
     * @return array<string, mixed>
     */
    private function withBrandStyleSource(string $hostname, array $surface): array
    {
        $brandStyleSource = $this->brandStyleSource($hostname);

        if ($brandStyleSource === null) {
            return $surface;
        }

        return array_merge($surface, [
            'brand_style_source' => $brandStyleSource,
        ]);
    }

    private function defaultSurfaceSlug(WebProperty $property, string $hostname): string
    {
        return $property->slug.'-'.Str::slug(str_replace('.', '-', $hostname)).'-v1';
    }

    private function normalizeHostname(mixed $hostname): ?string
    {
        if (! is_string($hostname) || trim($hostname) === '') {
            return null;
        }

        return Str::of($hostname)
            ->lower()
            ->replaceStart('https://', '')
            ->replaceStart('http://', '')
            ->before('/')
            ->trim('.')
            ->toString();
    }
}
