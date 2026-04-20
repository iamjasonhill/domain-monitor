<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string|null $domain_id
 * @property string|null $property_analytics_source_id
 * @property string|null $web_property_event_contract_id
 * @property string $hostname
 * @property string $surface_type
 * @property string|null $journey_type
 * @property string|null $runtime_driver
 * @property string|null $runtime_label
 * @property string|null $runtime_path
 * @property string|null $tenant_key
 * @property string $analytics_binding_mode
 * @property string $event_contract_binding_mode
 * @property string $rollout_status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $notes
 */
class WebPropertyConversionSurface extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'domain_id',
        'property_analytics_source_id',
        'web_property_event_contract_id',
        'hostname',
        'surface_type',
        'journey_type',
        'runtime_driver',
        'runtime_label',
        'runtime_path',
        'tenant_key',
        'analytics_binding_mode',
        'event_contract_binding_mode',
        'rollout_status',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $surface) {
            if (empty($surface->id)) {
                $surface->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<PropertyAnalyticsSource, $this>
     */
    public function analyticsSource(): BelongsTo
    {
        return $this->belongsTo(PropertyAnalyticsSource::class, 'property_analytics_source_id');
    }

    /**
     * @return BelongsTo<WebPropertyEventContract, $this>
     */
    public function eventContractAssignment(): BelongsTo
    {
        return $this->belongsTo(WebPropertyEventContract::class, 'web_property_event_contract_id');
    }
}
