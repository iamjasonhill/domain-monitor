<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $domain
 * @property string|null $registrar
 * @property string|null $hosting_provider
 * @property string|null $platform
 * @property string|null $domain_status
 * @property bool|null $auto_renew
 * @property array<int, string>|null $nameservers
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class DomainResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->domain,
            'status' => $this->is_active ? 'active' : 'inactive',
            'expires_at' => $this->expires_at?->format('Y-m-d'),
            'registrar' => $this->registrar,
            'dns_servers' => $this->nameservers ?? [],
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'metadata' => [
                'ssl_expires_at' => null, // TODO: Add SSL expiration from latest SSL check if available
                'monitoring_enabled' => $this->is_active,
                'hosting_provider' => $this->hosting_provider,
                'platform' => $this->platform,
                'domain_status' => $this->domain_status,
                'auto_renew' => $this->auto_renew,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
