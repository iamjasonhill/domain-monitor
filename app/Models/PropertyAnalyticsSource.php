<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string $provider
 * @property string $external_id
 * @property string|null $external_name
 * @property string|null $workspace_path
 * @property bool $is_primary
 * @property string $status
 * @property string|null $notes
 */
class PropertyAnalyticsSource extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'provider',
        'external_id',
        'external_name',
        'workspace_path',
        'is_primary',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $source) {
            if (empty($source->id)) {
                $source->id = Str::uuid()->toString();
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
}
