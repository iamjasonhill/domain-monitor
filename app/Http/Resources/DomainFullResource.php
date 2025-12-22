<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detailed domain resource including platform and tags relationships.
 *
 * @property string $id
 * @property string $domain
 * @property string|null $project_key
 * @property string|null $registrar
 * @property string|null $hosting_provider
 * @property string|null $hosting_admin_url
 * @property string|null $platform
 * @property string|null $domain_status
 * @property bool|null $auto_renew
 * @property array<int, string>|null $nameservers
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property bool $is_active
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DomainFullResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Eager load relationships if not already loaded
        $this->resource->loadMissing(['platform', 'tags']);

        $platformModel = $this->resource->platform;

        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'project_key' => $this->project_key,
            'status' => $this->is_active ? 'active' : 'inactive',
            'is_parked' => $this->resource->isParked(),
            'is_email_only' => $this->resource->isEmailOnly(),

            // Registration & Expiry
            'registrar' => $this->registrar,
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'auto_renew' => $this->auto_renew,
            'domain_status' => $this->domain_status,

            // Hosting
            'hosting' => [
                'provider' => $this->hosting_provider,
                'admin_url' => $this->hosting_admin_url,
            ],

            // DNS
            'dns' => [
                'nameservers' => $this->nameservers ?? [],
            ],

            // Platform (from WebsitePlatform relationship)
            'platform' => $platformModel ? [
                'type' => $platformModel->platform_type,
                'version' => $platformModel->platform_version,
                'confidence' => $platformModel->detection_confidence,
                'admin_url' => $platformModel->admin_url,
                'last_detected' => $platformModel->last_detected?->toIso8601String(),
            ] : [
                'type' => $this->platform, // Fallback to domain.platform column
                'version' => null,
                'confidence' => null,
                'admin_url' => null,
                'last_detected' => null,
            ],

            // Scaffolding / Migration tracking (for WebForge)
            'scaffolding' => [
                'target_platform' => $this->resource->target_platform,
                'migration_tier' => $this->resource->migration_tier,
                'status' => $this->resource->scaffolding_status,
                'scaffolded_at' => $this->resource->scaffolded_at?->toIso8601String(),
                'scaffolded_by' => $this->resource->scaffolded_by,
            ],

            // Tags
            'tags' => $this->resource->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'priority' => $tag->priority,
            ])->values()->all(),

            // Notes
            'notes' => $this->notes,

            // Timestamps
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
