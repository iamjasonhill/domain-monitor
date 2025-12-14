<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string|null $platform_type
 * @property string|null $platform_version
 * @property string|null $admin_url
 * @property string|null $detection_confidence
 * @property \Illuminate\Support\Carbon|null $last_detected
 */
class WebsitePlatform extends Model
{
    /** @use HasFactory<\Database\Factories\WebsitePlatformFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'platform_type',
        'platform_version',
        'admin_url',
        'detection_confidence',
        'last_detected',
    ];

    protected function casts(): array
    {
        return [
            'last_detected' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $platform) {
            if (empty($platform->id)) {
                $platform->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, WebsitePlatform>
     *
     * @phpstan-ignore-next-line
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
