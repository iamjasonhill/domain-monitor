<?php

namespace App\Http\Resources;

use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyEventContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WebPropertyConversionSurface
 */
class RuntimeAnalyticsContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $property = $this->webProperty;
        $analyticsSource = $this->resolvedAnalyticsSource($property);
        $analyticsConfig = $analyticsSource?->provider_config;
        $eventAssignment = $this->resolvedEventContractAssignment($property);
        $eventContract = $eventAssignment?->eventContract;

        return [
            'hostname' => $this->hostname,
            'property_slug' => $property?->slug,
            'site_key' => $property?->siteKey(),
            'journey_type' => $this->journey_type,
            'runtime' => [
                'driver' => $this->runtime_driver,
                'label' => $this->runtime_label,
                'path' => $this->runtime_path,
            ],
            'ga4' => [
                'provider' => $analyticsSource?->provider,
                'property_id' => is_array($analyticsConfig) ? ($analyticsConfig['property_id'] ?? null) : null,
                'stream_id' => is_array($analyticsConfig) ? ($analyticsConfig['stream_id'] ?? null) : null,
                'measurement_id' => is_array($analyticsConfig) ? ($analyticsConfig['measurement_id'] ?? null) : null,
                'bigquery_project' => is_array($analyticsConfig) ? ($analyticsConfig['bigquery_project'] ?? null) : null,
            ],
            'event_contract' => [
                'key' => $eventContract?->key,
                'version' => $eventContract?->version,
                'rollout_status' => $eventAssignment?->rollout_status,
            ],
            'conversion_surface' => [
                'rollout_status' => $this->rollout_status,
                'verified_at' => $this->verified_at?->toIso8601String(),
            ],
        ];
    }

    private function resolvedAnalyticsSource(?WebProperty $property): ?PropertyAnalyticsSource
    {
        if ($this->analytics_binding_mode !== 'inherits_property' && $this->analyticsSource instanceof PropertyAnalyticsSource) {
            return $this->analyticsSource;
        }

        return $property?->primaryAnalyticsSource('ga4');
    }

    private function resolvedEventContractAssignment(?WebProperty $property): ?WebPropertyEventContract
    {
        if ($this->event_contract_binding_mode !== 'inherits_property' && $this->eventContractAssignment instanceof WebPropertyEventContract) {
            return $this->eventContractAssignment;
        }

        return $property?->primaryEventContractAssignment();
    }
}
