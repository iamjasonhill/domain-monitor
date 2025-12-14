<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain
 * @property string|null $project_key
 * @property string|null $registrar
 * @property string|null $hosting_provider
 * @property string|null $hosting_admin_url
 * @property string|null $platform
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at_synergy
 * @property string|null $domain_status
 * @property bool|null $auto_renew
 * @property array|null $nameservers
 * @property string|null $dns_config_name
 * @property string|null $registrant_name
 * @property string|null $registrant_id_type
 * @property string|null $registrant_id
 * @property string|null $eligibility_type
 * @property bool|null $eligibility_valid
 * @property \Illuminate\Support\Carbon|null $eligibility_last_check
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
        'hosting_admin_url',
        'platform',
        'expires_at',
        'created_at_synergy',
        'domain_status',
        'auto_renew',
        'nameservers',
        'dns_config_name',
        'registrant_name',
        'registrant_id_type',
        'registrant_id',
        'eligibility_type',
        'eligibility_valid',
        'eligibility_last_check',
        'last_checked_at',
        'check_frequency_minutes',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at_synergy' => 'datetime',
            'auto_renew' => 'boolean',
            'nameservers' => 'array',
            'eligibility_valid' => 'boolean',
            'eligibility_last_check' => 'date',
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

    /**
     * @return HasOne<WebsitePlatform, Domain>
     */
    public function platform(): HasOne
    {
        return $this->hasOne(WebsitePlatform::class);
    }
}
