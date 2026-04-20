<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string $analytics_event_contract_id
 * @property bool $is_primary
 * @property string $rollout_status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $notes
 */
class WebPropertyEventContract extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'analytics_event_contract_id',
        'is_primary',
        'rollout_status',
        'verified_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $assignment) {
            if (empty($assignment->id)) {
                $assignment->id = Str::uuid()->toString();
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
     * @return BelongsTo<AnalyticsEventContract, $this>
     */
    public function eventContract(): BelongsTo
    {
        return $this->belongsTo(AnalyticsEventContract::class, 'analytics_event_contract_id');
    }
}
