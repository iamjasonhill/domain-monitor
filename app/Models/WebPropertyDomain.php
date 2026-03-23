<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string $domain_id
 * @property string $usage_type
 * @property bool $is_canonical
 * @property string|null $notes
 */
class WebPropertyDomain extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'domain_id',
        'usage_type',
        'is_canonical',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $link) {
            if (empty($link->id)) {
                $link->id = Str::uuid()->toString();
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
}
