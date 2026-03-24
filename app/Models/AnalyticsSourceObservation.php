<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $provider
 * @property string $external_id
 * @property string|null $external_name
 * @property string|null $expected_tracker_host
 * @property string $install_verdict
 * @property string|null $best_url
 * @property array<int, string>|null $detected_site_ids
 * @property array<int, string>|null $detected_tracker_hosts
 * @property string|null $summary
 * @property \Illuminate\Support\Carbon|null $checked_at
 * @property string|null $matched_property_analytics_source_id
 * @property string|null $matched_web_property_id
 * @property array<string, mixed>|null $raw_payload
 */
class AnalyticsSourceObservation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'external_id',
        'external_name',
        'expected_tracker_host',
        'install_verdict',
        'best_url',
        'detected_site_ids',
        'detected_tracker_hosts',
        'summary',
        'checked_at',
        'matched_property_analytics_source_id',
        'matched_web_property_id',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'detected_site_ids' => 'array',
            'detected_tracker_hosts' => 'array',
            'checked_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $observation) {
            if (empty($observation->id)) {
                $observation->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<PropertyAnalyticsSource, $this>
     */
    public function matchedPropertyAnalyticsSource(): BelongsTo
    {
        return $this->belongsTo(PropertyAnalyticsSource::class, 'matched_property_analytics_source_id');
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function matchedWebProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class, 'matched_web_property_id');
    }
}
