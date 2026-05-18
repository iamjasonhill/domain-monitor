<?php

namespace App\Services;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyEventContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RuntimeAnalyticsContextFeedBuilder
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function build(?string $hostname = null, ?string $runtimePath = null, ?string $siteKey = null): Collection
    {
        $normalizedHostname = $this->normalizeHostname($hostname);
        $normalizedRuntimePath = $this->normalizeRuntimePath($runtimePath);
        $normalizedSiteKey = $this->normalizeSiteKey($siteKey);

        $properties = WebProperty::query()
            ->where('status', 'active')
            ->with([
                'analyticsSources',
                'eventContractAssignments.eventContract',
                'conversionSurfaces.analyticsSource',
                'conversionSurfaces.eventContractAssignment.eventContract',
                'conversionSurfaces.domain',
                'primaryDomain',
                'propertyDomains.domain',
            ])
            ->orderBy('slug')
            ->get();

        $contexts = $properties
            ->flatMap(fn (WebProperty $property): array => $this->contextsForProperty($property))
            ->values();

        if ($normalizedHostname !== null) {
            $contexts = $contexts->where('hostname', $normalizedHostname)->values();
        }

        if ($normalizedRuntimePath !== null) {
            $contexts = $contexts->filter(
                fn (array $context): bool => (($context['runtime']['path'] ?? null) === $normalizedRuntimePath)
            )->values();
        }

        if ($normalizedSiteKey !== null) {
            $contexts = $contexts->filter(
                fn (array $context): bool => (($context['site_key'] ?? null) === $normalizedSiteKey)
            )->values();
        }

        return collect(
            $contexts
                ->sortBy(fn (array $context): string => (string) ($context['hostname'] ?? ''))
                ->values()
                ->all()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contextsForProperty(WebProperty $property): array
    {
        $contexts = [];

        $propertyAnalyticsSource = $property->primaryAnalyticsSource('ga4');
        $propertyEventAssignment = $property->primaryEventContractAssignment();
        $defaultRuntime = $this->defaultRuntimeFromProperty($property);
        $includedHostnames = [];

        foreach ($property->conversionSurfaces as $surface) {
            $hostname = $this->normalizeHostname($surface->hostname);

            if ($hostname === null) {
                continue;
            }

            $includedHostnames[$hostname] = true;

            $contexts[] = $this->conversionSurfaceContextRecord(
                property: $property,
                surface: $surface,
                fallbackAnalyticsSource: $propertyAnalyticsSource,
                fallbackEventAssignment: $propertyEventAssignment
            );
        }

        $hostnamePolicies = $property->hostnameLinkPolicySummary()['hostnames'];

        foreach ($hostnamePolicies as $hostnamePolicy) {
            $hostname = $this->normalizeHostname($hostnamePolicy['hostname'] ?? null);

            if ($hostname === null || isset($includedHostnames[$hostname])) {
                continue;
            }

            $classification = $this->classificationFromPolicy($hostnamePolicy);

            $contexts[] = [
                'hostname' => $hostname,
                'property_slug' => $property->slug,
                'site_key' => $property->siteKey(),
                'journey_type' => $this->journeyTypeFromPolicy($hostnamePolicy),
                'runtime' => $defaultRuntime,
                'ga4' => $this->ga4Summary($propertyAnalyticsSource),
                'event_contract' => $this->eventContractSummary($propertyEventAssignment),
                'conversion_surface' => [
                    'rollout_status' => null,
                    'verified_at' => null,
                ],
                'host_classification' => [
                    'class' => $classification['class'],
                    'decision' => $classification['decision'],
                    'reason' => $classification['reason'],
                    'provenance' => 'hostname_link_policy',
                    'role' => $hostnamePolicy['role'] ?? null,
                    'property_kind' => $hostnamePolicy['property_kind'] ?? null,
                ],
            ];
        }

        foreach ($this->hostOverridesForProperty($property->slug) as $hostOverride) {
            $hostname = $this->normalizeHostname($hostOverride['hostname'] ?? null);

            if ($hostname === null || isset($includedHostnames[$hostname])) {
                continue;
            }

            $includedHostnames[$hostname] = true;

            $contexts[] = [
                'hostname' => $hostname,
                'property_slug' => $property->slug,
                'site_key' => $property->siteKey(),
                'journey_type' => $this->normalizeText($hostOverride['journey_type'] ?? null),
                'runtime' => $defaultRuntime,
                'ga4' => $this->ga4Summary($propertyAnalyticsSource),
                'event_contract' => $this->eventContractSummary($propertyEventAssignment),
                'conversion_surface' => [
                    'rollout_status' => null,
                    'verified_at' => null,
                ],
                'host_classification' => [
                    'class' => $this->normalizeText($hostOverride['class'] ?? null) ?? 'retired_or_unknown',
                    'decision' => $this->normalizeText($hostOverride['decision'] ?? null) ?? 'excluded',
                    'reason' => $this->normalizeText($hostOverride['reason'] ?? null) ?? 'runtime_host_override',
                    'provenance' => 'runtime_host_override',
                    'role' => null,
                    'property_kind' => null,
                ],
            ];
        }

        return $contexts;
    }

    /**
     * @return array<string, mixed>
     */
    private function conversionSurfaceContextRecord(
        WebProperty $property,
        WebPropertyConversionSurface $surface,
        ?PropertyAnalyticsSource $fallbackAnalyticsSource,
        ?WebPropertyEventContract $fallbackEventAssignment
    ): array {
        $analyticsSource = $surface->analytics_binding_mode !== 'inherits_property'
            && $surface->analyticsSource instanceof PropertyAnalyticsSource
            ? $surface->analyticsSource
            : $fallbackAnalyticsSource;

        $eventAssignment = $surface->event_contract_binding_mode !== 'inherits_property'
            && $surface->eventContractAssignment instanceof WebPropertyEventContract
            ? $surface->eventContractAssignment
            : $fallbackEventAssignment;

        return [
            'hostname' => $this->normalizeHostname($surface->hostname),
            'property_slug' => $property->slug,
            'site_key' => $property->siteKey(),
            'journey_type' => $surface->journey_type,
            'runtime' => [
                'driver' => $surface->runtime_driver,
                'label' => $surface->runtime_label,
                'path' => $surface->runtime_path,
            ],
            'ga4' => $this->ga4Summary($analyticsSource),
            'event_contract' => $this->eventContractSummary($eventAssignment),
            'conversion_surface' => [
                'rollout_status' => $surface->rollout_status,
                'verified_at' => $surface->verified_at?->toIso8601String(),
            ],
            'host_classification' => [
                'class' => 'conversion_host',
                'decision' => 'exported',
                'reason' => 'first_class_conversion_surface',
                'provenance' => 'conversion_surface',
                'role' => $surface->surface_type === 'portal_subdomain'
                    ? 'customer_portal_hostname'
                    : 'quote_or_app_hostname',
                'property_kind' => 'quote_conversion_surface',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $hostnamePolicy
     * @return array{class: string, decision: string, reason: string}
     */
    private function classificationFromPolicy(array $hostnamePolicy): array
    {
        $role = $hostnamePolicy['role'] ?? null;
        $propertyKind = $hostnamePolicy['property_kind'] ?? null;
        $expectedLinks = is_array($hostnamePolicy['expected_links'] ?? null)
            ? $hostnamePolicy['expected_links']
            : [];

        $hasRuntimeAttributionLinks = collect($expectedLinks)
            ->map(fn (mixed $slot): ?string => is_array($slot) ? ($slot['status'] ?? null) : null)
            ->filter(fn (mixed $status): bool => in_array($status, ['required', 'optional'], true))
            ->isNotEmpty();

        if ($role === 'marketing_domain' && $propertyKind === 'normal_marketing_site' && $hasRuntimeAttributionLinks) {
            return [
                'class' => 'public_brand_root_host',
                'decision' => 'exported',
                'reason' => 'marketing_domain_with_runtime_attribution',
            ];
        }

        if ($propertyKind === 'quote_conversion_surface' && $hasRuntimeAttributionLinks) {
            return [
                'class' => 'conversion_host',
                'decision' => 'exported',
                'reason' => 'quote_or_portal_host_with_runtime_attribution',
            ];
        }

        if ($propertyKind === 'operational_app_shell_apex' && $hasRuntimeAttributionLinks) {
            return [
                'class' => 'login_customer_provider_app_shell_host',
                'decision' => 'exported',
                'reason' => 'operational_app_shell_host_with_runtime_attribution',
            ];
        }

        return [
            'class' => 'retired_or_unknown',
            'decision' => 'excluded',
            'reason' => 'no_runtime_attribution_links',
        ];
    }

    /**
     * @param  array<string, mixed>  $hostnamePolicy
     */
    private function journeyTypeFromPolicy(array $hostnamePolicy): ?string
    {
        $propertyKind = $hostnamePolicy['property_kind'] ?? null;

        if ($propertyKind === 'quote_conversion_surface') {
            return 'mixed_quote';
        }

        if ($propertyKind === 'operational_app_shell_apex') {
            return 'app_shell';
        }

        return null;
    }

    /**
     * @return array{driver: string|null, label: string|null, path: string|null}
     */
    private function defaultRuntimeFromProperty(WebProperty $property): array
    {
        $surface = $property->conversionSurfaces
            ->first(fn (WebPropertyConversionSurface $candidate): bool => is_string($candidate->runtime_path) && $candidate->runtime_path !== '')
            ?? $property->conversionSurfaces->first();

        if (! $surface instanceof WebPropertyConversionSurface) {
            $defaultRuntime = config('domain_monitor.conversion_surfaces.default_quote_surface');

            if (! is_array($defaultRuntime)) {
                return [
                    'driver' => null,
                    'label' => null,
                    'path' => null,
                ];
            }

            return [
                'driver' => $this->normalizeText($defaultRuntime['runtime_driver'] ?? null),
                'label' => $this->normalizeText($defaultRuntime['runtime_label'] ?? null),
                'path' => $this->normalizeText($defaultRuntime['runtime_path'] ?? null),
            ];
        }

        return [
            'driver' => $surface->runtime_driver,
            'label' => $surface->runtime_label,
            'path' => $surface->runtime_path,
        ];
    }

    /**
     * @return array{
     *   provider: string|null,
     *   property_id: string|null,
     *   stream_id: string|null,
     *   measurement_id: string|null,
     *   bigquery_project: string|null
     * }
     */
    private function ga4Summary(?PropertyAnalyticsSource $source): array
    {
        $config = $source?->provider_config;

        return [
            'provider' => $source?->provider,
            'property_id' => is_array($config) ? ($config['property_id'] ?? null) : null,
            'stream_id' => is_array($config) ? ($config['stream_id'] ?? null) : null,
            'measurement_id' => is_array($config) ? ($config['measurement_id'] ?? null) : null,
            'bigquery_project' => is_array($config) ? ($config['bigquery_project'] ?? null) : null,
        ];
    }

    /**
     * @return array{key: string|null, version: string|null, rollout_status: string|null}
     */
    private function eventContractSummary(?WebPropertyEventContract $assignment): array
    {
        $contract = $assignment?->eventContract;

        return [
            'key' => $contract?->key,
            'version' => $contract?->version,
            'rollout_status' => $assignment?->rollout_status,
        ];
    }

    private function normalizeHostname(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = Str::lower(trim($value, ". \t\n\r\0\x0B"));

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeRuntimePath(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeSiteKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hostOverridesForProperty(string $propertySlug): array
    {
        $overrides = config('domain_monitor.runtime_analytics.host_overrides', []);

        if (! is_array($overrides)) {
            return [];
        }

        return collect($overrides)
            ->filter(fn (mixed $override): bool => is_array($override))
            ->filter(function (array $override) use ($propertySlug): bool {
                $overrideSlug = $this->normalizeText($override['property_slug'] ?? null);

                return $overrideSlug === $propertySlug;
            })
            ->values()
            ->all();
    }

    private function normalizeText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
