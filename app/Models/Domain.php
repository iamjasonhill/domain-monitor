<?php

namespace App\Models;

use App\Services\DomainMonitorSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property \Illuminate\Support\Carbon|null $renewed_at
 * @property string|null $renewed_by
 * @property \Illuminate\Support\Carbon|null $created_at_synergy
 * @property string|null $domain_status
 * @property bool|null $auto_renew
 * @property array<int, string>|null $nameservers
 * @property array<int, array<string, mixed>>|null $nameserver_details
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
 * @property string|null $ip_address
 * @property \Illuminate\Support\Carbon|null $ip_checked_at
 * @property string|null $ip_isp
 * @property string|null $ip_organization
 * @property string|null $ip_as_number
 * @property string|null $ip_country
 * @property string|null $ip_city
 * @property bool|null $ip_hosting_flag
 * @property array<int, string>|null $dkim_selectors
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
        'target_platform',
        'migration_tier',
        'scaffolding_status',
        'scaffolded_at',
        'scaffolded_by',
        'expires_at',
        'renewed_at',
        'renewed_by',
        'created_at_synergy',
        'domain_status',
        'auto_renew',
        'nameservers',
        'nameserver_details',
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
        'ip_address',
        'ip_checked_at',
        'ip_isp',
        'ip_organization',
        'ip_as_number',
        'ip_country',
        'ip_city',
        'ip_hosting_flag',
        'parked_override',
        'parked_override_set_at',
        'dkim_selectors',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'renewed_at' => 'datetime',
            'created_at_synergy' => 'datetime',
            'auto_renew' => 'boolean',
            'nameservers' => 'array',
            'nameserver_details' => 'array',
            'eligibility_valid' => 'boolean',
            'eligibility_last_check' => 'date',
            'last_checked_at' => 'datetime',
            'is_active' => 'boolean',
            'check_frequency_minutes' => 'integer',
            'ip_checked_at' => 'datetime',
            'ip_hosting_flag' => 'boolean',
            'parked_override' => 'boolean',
            'parked_override_set_at' => 'datetime',
            'scaffolded_at' => 'datetime',
            'migration_tier' => 'integer',
            'dkim_selectors' => 'array',
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
     * @return HasMany<DomainCheck, $this>
     */
    public function checks(): HasMany
    {
        return $this->hasMany(DomainCheck::class);
    }

    /**
     * @return HasMany<DomainAlert, $this>
     */
    /**
     * Scope to load the latest status for each check type using subqueries.
     * This avoids N+1 problems and PostgreSQL UUID max() limitations.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeWithLatestCheckStatuses(Builder $query): void
    {
        $checkTypes = [
            'http',
            'ssl',
            'dns',
            'email_security',
            'seo',
            'security_headers',
        ];

        foreach ($checkTypes as $type) {
            $attribute = 'latest_'.$type.'_status';
            $query->addSelect([
                $attribute => DomainCheck::select('status')
                    ->whereColumn('domain_id', 'domains.id')
                    ->where('check_type', $type)
                    ->latest()
                    ->take(1),
            ]);
        }
    }

    /**
     * @return HasMany<DomainAlert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(DomainAlert::class);
    }

    /**
     * @return HasOne<WebsitePlatform, $this>
     */
    public function platform(): HasOne
    {
        return $this->hasOne(WebsitePlatform::class, 'domain_id', 'id');
    }

    /**
     * @return HasMany<DnsRecord, $this>
     */
    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    /**
     * @return HasMany<Subdomain, $this>
     */
    public function subdomains(): HasMany
    {
        return $this->hasMany(Subdomain::class);
    }

    /**
     * @return BelongsToMany<DomainTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DomainTag::class, 'domain_tag', 'domain_id', 'tag_id');
    }

    /**
     * @return HasMany<Deployment, $this>
     */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->orderByDesc('deployed_at');
    }

    /**
     * Scope a query to search domains by domain name, project key, registrar, hosting provider, or notes.
     * Supports case-insensitive search and multiple search terms.
     * Requires minimum 2 characters to avoid performance issues.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $searchTerm = trim($term);
        // Require minimum 2 characters for search to avoid performance issues
        if (empty($searchTerm) || mb_strlen($searchTerm) < 2) {
            return;
        }

        // Escape special LIKE characters
        $searchTerm = str_replace(['%', '_'], ['\%', '\_'], $searchTerm);

        // Split search term into individual words for better matching
        $searchTerms = array_filter(explode(' ', $searchTerm), fn ($word) => ! empty(trim($word)));

        // Get database connection type
        $connection = $query->getModel()->getConnection()->getDriverName();

        $query->where(function ($q) use ($searchTerms, $connection) {
            foreach ($searchTerms as $term) {
                $q->where(function ($subQuery) use ($term, $connection) {
                    if ($connection === 'pgsql') {
                        // PostgreSQL: Use ILIKE for case-insensitive search
                        $subQuery->where('domain', 'ilike', '%'.$term.'%')
                            ->orWhere('project_key', 'ilike', '%'.$term.'%')
                            ->orWhere('registrar', 'ilike', '%'.$term.'%')
                            ->orWhere('hosting_provider', 'ilike', '%'.$term.'%')
                            ->orWhere('notes', 'ilike', '%'.$term.'%')
                            ->orWhere('registrant_name', 'ilike', '%'.$term.'%');
                    } else {
                        // MySQL/SQLite: Use LOWER() for case-insensitive search
                        $lowerTerm = mb_strtolower($term);
                        $subQuery->whereRaw('LOWER(domain) LIKE ?', ['%'.$lowerTerm.'%'])
                            ->orWhereRaw('LOWER(project_key) LIKE ?', ['%'.$lowerTerm.'%'])
                            ->orWhereRaw('LOWER(registrar) LIKE ?', ['%'.$lowerTerm.'%'])
                            ->orWhereRaw('LOWER(hosting_provider) LIKE ?', ['%'.$lowerTerm.'%'])
                            ->orWhereRaw('LOWER(notes) LIKE ?', ['%'.$lowerTerm.'%'])
                            ->orWhereRaw('LOWER(registrant_name) LIKE ?', ['%'.$lowerTerm.'%']);
                    }
                });
            }
        });
    }

    /**
     * Scope a query to filter by active status.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeFilterActive(Builder $query, ?bool $isActive): void
    {
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }
    }

    /**
     * Scope a query to filter domains expiring soon (within 30 days).
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeFilterExpiring(Builder $query, bool $expiring): void
    {
        if ($expiring) {
            $query->where('is_active', true)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', now()->addDays(30)->endOfDay())
                ->where('expires_at', '>', now());
        }
    }

    /**
     * Scope a query to exclude parked domains.
     * Checks both the platform column and the platform relationship.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeExcludeParked(Builder $query, bool $exclude): void
    {
        if ($exclude) {
            $query->where('parked_override', false);

            // Exclude domains where:
            // 1. platform column is 'Parked', OR
            // 2. platform relationship has platform_type = 'Parked'
            $query->where(function ($q) {
                $q->where('platform', '!=', 'Parked')
                    ->orWhereNull('platform');
            })
                ->whereDoesntHave('platform', function ($platformQ) {
                    $platformQ->where('platform_type', 'Parked');
                });
        }
    }

    /**
     * Scope a query to exclude email-only domains.
     * Checks both the platform column and the platform relationship.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeExcludeEmailOnly(Builder $query, bool $exclude): void
    {
        if ($exclude) {
            $query->where(function ($q) {
                $q->where('platform', '!=', 'Email Only')
                    ->orWhereNull('platform');
            })
                ->whereDoesntHave('platform', function ($platformQ) {
                    $platformQ->where('platform_type', 'Email Only');
                });
        }
    }

    public function isParked(): bool
    {
        if ($this->parked_override) {
            return true;
        }

        $platformModel = $this->relationLoaded('platform') ? $this->getRelation('platform') : null;
        $platformType = $platformModel instanceof WebsitePlatform ? $platformModel->platform_type : null;
        $platformType ??= $this->getAttribute('platform');

        return $platformType === 'Parked';
    }

    public function isEmailOnly(): bool
    {
        $platformModel = $this->relationLoaded('platform') ? $this->getRelation('platform') : null;
        $platformType = $platformModel instanceof WebsitePlatform ? $platformModel->platform_type : null;
        $platformType ??= $this->getAttribute('platform');

        return $platformType === 'Email Only';
    }

    /**
     * Scope a query to filter domains with recent failures (within last 7 days).
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeFilterRecentFailures(Builder $query, bool $recentFailures): void
    {
        if ($recentFailures) {
            $hours = app(DomainMonitorSettings::class)->recentFailuresHours();

            $query->whereHas('checks', function ($q) use ($hours) {
                $q->where('status', 'fail')
                    ->where('created_at', '>=', now()->subHours($hours));
            });
        }
    }

    /**
     * Scope a query to filter domains with failed eligibility status.
     *
     * @param  Builder<Domain>  $query
     */
    public function scopeFilterFailedEligibility(Builder $query, bool $failedEligibility): void
    {
        if ($failedEligibility) {
            $query->where('eligibility_valid', false);
        }
    }

    /**
     * @return HasMany<UptimeIncident, $this>
     */
    public function uptimeIncidents(): HasMany
    {
        return $this->hasMany(UptimeIncident::class)->orderByDesc('started_at');
    }
}
