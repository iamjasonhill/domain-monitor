<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $subdomain
 * @property string $full_domain
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $ip_checked_at
 * @property string|null $ip_isp
 * @property string|null $ip_organization
 * @property string|null $ip_as_number
 * @property string|null $ip_country
 * @property string|null $ip_city
 * @property bool|null $ip_hosting_flag
 * @property string|null $hosting_provider
 * @property string|null $hosting_admin_url
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Domain $domain
 */
class Subdomain extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'subdomain',
        'full_domain',
        'ip_address',
        'ip_checked_at',
        'ip_isp',
        'ip_organization',
        'ip_as_number',
        'ip_country',
        'ip_city',
        'ip_hosting_flag',
        'hosting_provider',
        'hosting_admin_url',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'ip_checked_at' => 'datetime',
            'ip_hosting_flag' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $subdomain) {
            if (empty($subdomain->id)) {
                $subdomain->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, Subdomain>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
