<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string|null $domain_id
 * @property string|null $web_property_id
 * @property string|null $property_analytics_source_id
 * @property string $source_provider
 * @property string $matomo_site_id
 * @property string|null $matomo_site_name
 * @property string|null $matomo_main_url
 * @property string $mapping_state
 * @property string|null $property_uri
 * @property string|null $property_type
 * @property \Illuminate\Support\Carbon|null $mapped_at
 * @property \Illuminate\Support\Carbon|null $latest_completed_job_at
 * @property string|null $latest_completed_job_type
 * @property \Illuminate\Support\Carbon|null $latest_completed_range_end
 * @property \Illuminate\Support\Carbon|null $latest_metric_date
 * @property \Illuminate\Support\Carbon $checked_at
 * @property array<string, mixed>|null $raw_payload
 */
class SearchConsoleCoverageStatus extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'web_property_id',
        'property_analytics_source_id',
        'source_provider',
        'matomo_site_id',
        'matomo_site_name',
        'matomo_main_url',
        'mapping_state',
        'property_uri',
        'property_type',
        'mapped_at',
        'latest_completed_job_at',
        'latest_completed_job_type',
        'latest_completed_range_end',
        'latest_metric_date',
        'checked_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'mapped_at' => 'datetime',
            'latest_completed_job_at' => 'datetime',
            'latest_completed_range_end' => 'date',
            'latest_metric_date' => 'date',
            'checked_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $status) {
            if (empty($status->id)) {
                $status->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }

    /**
     * @return BelongsTo<PropertyAnalyticsSource, $this>
     */
    public function propertyAnalyticsSource(): BelongsTo
    {
        return $this->belongsTo(PropertyAnalyticsSource::class);
    }

    public function mappingStateLabel(): string
    {
        return match ($this->mapping_state) {
            'domain_property' => 'Mapped (Domain Property)',
            'url_prefix' => 'Mapped (URL Prefix)',
            'not_mapped' => 'Matomo Only',
            default => str($this->mapping_state)->replace('_', ' ')->title()->toString(),
        };
    }

    public function freshnessState(): string
    {
        if (! $this->latest_metric_date) {
            return 'never_imported';
        }

        return $this->latest_metric_date->lt(now()->subDays(7)->startOfDay())
            ? 'stale'
            : 'recent';
    }

    public function freshnessLabel(): string
    {
        return match ($this->freshnessState()) {
            'recent' => 'Recent',
            'stale' => 'Import Stale',
            default => 'Never Imported',
        };
    }
}
