<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use Illuminate\Support\Arr;
use RuntimeException;

class WebPropertyConversionSurfaceSyncService
{
    /**
     * @return array{
     *   status:string,
     *   hostname:string|null,
     *   domain_action:string,
     *   link_action:string,
     *   surface_action:string,
     *   message:string
     * }
     */
    public function syncTargetQuoteSurface(WebProperty $property, bool $persist = true): array
    {
        $hostname = $this->normalizeHostname($property->target_moveroo_subdomain_url);

        if ($hostname === null) {
            return [
                'status' => 'skipped',
                'hostname' => null,
                'domain_action' => 'skipped',
                'link_action' => 'skipped',
                'surface_action' => 'skipped',
                'message' => 'Property has no target quote-subdomain hostname.',
            ];
        }

        $property->loadMissing([
            'primaryDomain',
            'propertyDomains',
            'analyticsSources',
            'eventContractAssignments.eventContract',
            'conversionSurfaces',
        ]);

        $domainPlan = $this->planDomain($property, $hostname);
        $linkPlan = $this->planPropertyDomainLink($property, $domainPlan['domain']);
        $surfacePlan = $this->planConversionSurface($property, $hostname, $domainPlan['domain']);

        if ($surfacePlan['status'] === 'conflict') {
            if ($persist) {
                throw new RuntimeException($surfacePlan['message']);
            }

            return [
                'status' => 'conflict',
                'hostname' => $hostname,
                'domain_action' => $domainPlan['action'],
                'link_action' => $linkPlan['action'],
                'surface_action' => 'conflict',
                'message' => $surfacePlan['message'],
            ];
        }

        if (! $persist) {
            return [
                'status' => $surfacePlan['status'] === 'noop' && $linkPlan['action'] === 'noop' && $domainPlan['action'] === 'noop' ? 'noop' : 'planned',
                'hostname' => $hostname,
                'domain_action' => $domainPlan['action'],
                'link_action' => $linkPlan['action'],
                'surface_action' => $surfacePlan['status'],
                'message' => 'Previewed target quote surface sync.',
            ];
        }

        $domain = $this->persistDomainPlan($domainPlan);
        $this->persistPropertyDomainLinkPlan($property, $domain, $linkPlan);
        $surface = $this->persistConversionSurfacePlan($property, $domain, $surfacePlan);

        return [
            'status' => $surface->wasRecentlyCreated ? 'created' : ($surfacePlan['dirty'] ? 'updated' : 'noop'),
            'hostname' => $hostname,
            'domain_action' => $domainPlan['action'],
            'link_action' => $linkPlan['action'],
            'surface_action' => $surface->wasRecentlyCreated ? 'create' : ($surfacePlan['dirty'] ? 'update' : 'noop'),
            'message' => sprintf('Synced conversion surface for %s.', $hostname),
        ];
    }

    private function normalizeHostname(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);

        if (str_contains($trimmed, '://')) {
            $host = parse_url($trimmed, PHP_URL_HOST);

            return is_string($host) && $host !== '' ? strtolower(rtrim($host, '.')) : null;
        }

        return strtolower(rtrim($trimmed, '.'));
    }

    /**
     * @return array{action:string, domain:Domain, dirty:bool}
     */
    private function planDomain(WebProperty $property, string $hostname): array
    {
        $domain = Domain::withTrashed()->firstWhere('domain', $hostname);
        $action = 'noop';

        if (! $domain instanceof Domain) {
            $domain = new Domain(['domain' => $hostname]);
            $action = 'create';
        }

        $runtimeConfig = $this->quoteSurfaceDefaults();
        $desiredAttributes = [
            'platform' => $runtimeConfig['runtime_driver'],
            'hosting_provider' => $property->primaryDomain?->hosting_provider,
            'check_frequency_minutes' => $domain->check_frequency_minutes ?: 60,
            'is_active' => true,
        ];

        $dirty = $domain->trashed();
        $domain->fill(array_filter(
            $desiredAttributes,
            static fn (mixed $value): bool => $value !== null
        ));

        if ($action === 'noop' && $domain->isDirty()) {
            $action = 'update';
            $dirty = true;
        }

        if ($domain->trashed()) {
            $action = $action === 'create' ? 'create' : 'restore';
            $dirty = true;
        }

        return [
            'action' => $action,
            'domain' => $domain,
            'dirty' => $dirty || $action !== 'noop',
        ];
    }

    /**
     * @param  array{action:string, domain:Domain, dirty:bool}  $domainPlan
     */
    private function persistDomainPlan(array $domainPlan): Domain
    {
        /** @var Domain $domain */
        $domain = $domainPlan['domain'];

        if ($domain->trashed()) {
            $domain->restore();
        }

        if (! $domain->exists || $domain->isDirty()) {
            $domain->save();
        }

        return $domain;
    }

    /**
     * @return array{action:string}
     */
    private function planPropertyDomainLink(WebProperty $property, Domain $domain): array
    {
        $existingLink = WebPropertyDomain::query()
            ->where('web_property_id', $property->id)
            ->where('domain_id', $domain->id)
            ->first();

        if (! $existingLink instanceof WebPropertyDomain) {
            return ['action' => 'create'];
        }

        if ($existingLink->usage_type !== 'subdomain') {
            return ['action' => 'update'];
        }

        return ['action' => 'noop'];
    }

    /**
     * @param  array{action:string}  $linkPlan
     */
    private function persistPropertyDomainLinkPlan(WebProperty $property, Domain $domain, array $linkPlan): void
    {
        $existingLink = WebPropertyDomain::query()
            ->where('web_property_id', $property->id)
            ->where('domain_id', $domain->id)
            ->first();

        if (! $existingLink instanceof WebPropertyDomain) {
            WebPropertyDomain::query()->create([
                'web_property_id' => $property->id,
                'domain_id' => $domain->id,
                'usage_type' => 'subdomain',
                'is_canonical' => false,
                'notes' => 'Linked from conversion surface sync.',
            ]);

            return;
        }

        if ($linkPlan['action'] === 'update') {
            $existingLink->forceFill([
                'usage_type' => 'subdomain',
                'is_canonical' => false,
            ])->save();
        }
    }

    /**
     * @return array{
     *   status:string,
     *   message:string,
     *   surface:WebPropertyConversionSurface,
     *   dirty:bool
     * }
     */
    private function planConversionSurface(WebProperty $property, string $hostname, Domain $domain): array
    {
        $existingSurface = WebPropertyConversionSurface::query()
            ->where('hostname', $hostname)
            ->first();

        if ($existingSurface instanceof WebPropertyConversionSurface && $existingSurface->web_property_id !== $property->id) {
            return [
                'status' => 'conflict',
                'message' => sprintf(
                    'Hostname %s is already assigned to property %s.',
                    $hostname,
                    $existingSurface->web_property_id
                ),
                'surface' => $existingSurface,
                'dirty' => false,
            ];
        }

        $surface = $existingSurface ?? new WebPropertyConversionSurface([
            'web_property_id' => $property->id,
            'hostname' => $hostname,
        ]);

        $defaults = $this->quoteSurfaceDefaults();
        $surface->fill([
            'domain_id' => $domain->id,
            'surface_type' => (string) Arr::get($defaults, 'surface_type', 'quote_subdomain'),
            'journey_type' => (string) Arr::get($defaults, 'journey_type', 'mixed_quote'),
            'runtime_driver' => $this->nullableString(Arr::get($defaults, 'runtime_driver')),
            'runtime_label' => $this->nullableString(Arr::get($defaults, 'runtime_label')),
            'runtime_path' => $this->nullableString(Arr::get($defaults, 'runtime_path')),
            'tenant_key' => $surface->tenant_key ?: $property->slug,
            'analytics_binding_mode' => (string) Arr::get($defaults, 'analytics_binding_mode', 'inherits_property'),
            'event_contract_binding_mode' => (string) Arr::get($defaults, 'event_contract_binding_mode', 'inherits_property'),
            'rollout_status' => $surface->rollout_status ?: (string) Arr::get($defaults, 'rollout_status', 'defined'),
            'notes' => $surface->notes ?: $this->nullableString(Arr::get($defaults, 'notes')),
        ]);

        return [
            'status' => $surface->exists ? ($surface->isDirty() ? 'update' : 'noop') : 'create',
            'message' => 'Prepared conversion surface payload.',
            'surface' => $surface,
            'dirty' => $surface->isDirty(),
        ];
    }

    /**
     * @param  array{
     *   status:string,
     *   message:string,
     *   surface:WebPropertyConversionSurface,
     *   dirty:bool
     * }  $surfacePlan
     */
    private function persistConversionSurfacePlan(WebProperty $property, Domain $domain, array $surfacePlan): WebPropertyConversionSurface
    {
        /** @var WebPropertyConversionSurface $surface */
        $surface = $surfacePlan['surface'];
        $surface->web_property_id = $property->id;
        $surface->domain_id = $domain->id;

        if (! $surface->exists || $surface->isDirty()) {
            $surface->save();
        }

        return $surface;
    }

    /**
     * @return array<string, mixed>
     */
    private function quoteSurfaceDefaults(): array
    {
        $defaults = config('domain_monitor.conversion_surfaces.default_quote_surface', []);

        return is_array($defaults) ? $defaults : [];
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
