<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain
 * @property string|null $project_key
 * @property string|null $registrar
 * @property string|null $hosting_provider
 * @property string|null $platform
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $last_checked_at
 * @property int $check_frequency_minutes
 * @property string|null $notes
 * @property bool $is_active
 *
 * @method static \Database\Factories\DomainFactory factory()
 */
class Domain extends Model
{
    /** @use HasFactory<\Database\Factories\DomainFactory> */
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain',
        'project_key',
        'registrar',
        'hosting_provider',
        'platform',
        'expires_at',
        'last_checked_at',
        'check_frequency_minutes',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
            'check_frequency_minutes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $domain) {
            if (empty($domain->id)) {
                $domain->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return HasMany<DomainCheck, Domain>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(DomainCheck::class);
    }

    /**
     * @return HasMany<DomainAlert, Domain>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(DomainAlert::class);
    }
}
