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
 * @property bool|null $transfer_lock
 * @property bool|null $renewal_required
 * @property bool|null $can_renew
 * @property array<int, string>|null $nameservers
 * @property string|null $dns_config_name
 * @property int|null $dns_config_id
 * @property string|null $registrant_name
 * @property string|null $registrant_id_type
 * @property string|null $registrant_id
 * @property string|null $eligibility_type
 * @property bool|null $eligibility_valid
 * @property \Illuminate\Support\Carbon|null $eligibility_last_check
 * @property string|null $au_policy_id
 * @property string|null $au_policy_desc
 * @property string|null $au_compliance_reason
 * @property string|null $au_association_id
 * @property string|null $domain_roid
 * @property string|null $registry_id
 * @property string|null $id_protect
 * @property array<int, mixed>|null $categories
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
            'transfer_lock' => $this->transfer_lock,
            'renewal_required' => $this->renewal_required,
            'can_renew' => $this->can_renew,

            // Hosting
            'hosting' => [
                'provider' => $this->hosting_provider,
                'admin_url' => $this->hosting_admin_url,
            ],

            // DNS
            'dns' => [
                'nameservers' => $this->nameservers ?? [],
                'config_name' => $this->dns_config_name,
                'config_id' => $this->dns_config_id,
            ],

            // Australian Domain Information
            'au_domain' => $this->registrant_name ? [
                'registrant_name' => $this->registrant_name,
                'registrant_id_type' => $this->registrant_id_type,
                'registrant_id' => $this->registrant_id,
                'eligibility_type' => $this->eligibility_type,
                'eligibility_valid' => $this->eligibility_valid,
                'eligibility_last_check' => $this->eligibility_last_check?->format('Y-m-d'),
                'policy_id' => $this->au_policy_id,
                'policy_desc' => $this->au_policy_desc,
                'compliance_reason' => $this->au_compliance_reason,
                'association_id' => $this->au_association_id,
                'domain_roid' => $this->domain_roid,
                'registry_id' => $this->registry_id,
            ] : null,

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

            // Additional Information
            'id_protect' => $this->id_protect,
            'categories' => $this->categories ?? [],

            // Timestamps
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
