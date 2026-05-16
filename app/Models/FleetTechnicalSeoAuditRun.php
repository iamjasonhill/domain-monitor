<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string $trigger_type
 * @property int|null $url_cap
 * @property array<int, string> $execution_modes
 * @property string|null $catalog_version
 * @property string|null $catalog_checksum
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property array<string, int>|null $summary_counts
 * @property WebProperty $webProperty
 */
class FleetTechnicalSeoAuditRun extends Model
{
    /** @use HasFactory<\Database\Factories\FleetTechnicalSeoAuditRunFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'trigger_type',
        'url_cap',
        'execution_modes',
        'catalog_version',
        'catalog_checksum',
        'started_at',
        'finished_at',
        'summary_counts',
    ];

    protected function casts(): array
    {
        return [
            'url_cap' => 'integer',
            'execution_modes' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'summary_counts' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            if (empty($run->id)) {
                $run->id = Str::uuid()->toString();
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
     * @return HasMany<FleetTechnicalSeoAuditResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(FleetTechnicalSeoAuditResult::class)
            ->orderBy('check_id')
            ->orderBy('target_type');
    }
}
