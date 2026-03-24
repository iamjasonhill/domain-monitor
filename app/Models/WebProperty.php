<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string $property_type
 * @property string $status
 * @property string|null $primary_domain_id
 * @property string|null $production_url
 * @property string|null $staging_url
 * @property string|null $platform
 * @property string|null $target_platform
 * @property string|null $owner
 * @property int|null $priority
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\WebPropertyFactory factory()
 */
class WebProperty extends Model
{
    /** @use HasFactory<\Database\Factories\WebPropertyFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'slug',
        'name',
        'property_type',
        'status',
        'primary_domain_id',
        'production_url',
        'staging_url',
        'platform',
        'target_platform',
        'owner',
        'priority',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $property) {
            if (empty($property->id)) {
                $property->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function primaryDomain(): BelongsTo
    {
        return $this->belongsTo(Domain::class, 'primary_domain_id');
    }

    /**
     * @return HasMany<WebPropertyDomain, $this>
     */
    public function propertyDomains(): HasMany
    {
        return $this->hasMany(WebPropertyDomain::class)->orderByDesc('is_canonical');
    }

    /**
     * @return BelongsToMany<Domain, $this>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'web_property_domains', 'web_property_id', 'domain_id')
            ->withPivot(['id', 'usage_type', 'is_canonical', 'notes'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<PropertyRepository, $this>
     */
    public function repositories(): HasMany
    {
        return $this->hasMany(PropertyRepository::class)->orderByDesc('is_primary');
    }

    /**
     * @return HasMany<PropertyAnalyticsSource, $this>
     */
    public function analyticsSources(): HasMany
    {
        return $this->hasMany(PropertyAnalyticsSource::class)->orderByDesc('is_primary');
    }

    /**
     * @return Collection<int, WebPropertyDomain>
     */
    public function orderedDomainLinks(): Collection
    {
        /** @var Collection<int, WebPropertyDomain> $links */
        $links = $this->relationLoaded('propertyDomains')
            ? $this->propertyDomains
            : $this->propertyDomains()->with(['domain.tags', 'domain.deployments.domain', 'domain.alerts'])->get();

        return $links->sortByDesc(fn (WebPropertyDomain $link) => $link->is_canonical)->values();
    }

    public function canonicalDomainLink(): ?WebPropertyDomain
    {
        return $this->orderedDomainLinks()->first(
            fn (WebPropertyDomain $link) => $link->is_canonical && $link->domain
        ) ?? $this->orderedDomainLinks()->first(fn (WebPropertyDomain $link) => $link->domain !== null);
    }

    public function primaryDomainName(): ?string
    {
        $primaryDomain = $this->relationLoaded('primaryDomain') ? $this->primaryDomain : null;

        if ($primaryDomain?->domain) {
            return $primaryDomain->domain;
        }

        return $this->canonicalDomainLink()?->domain?->domain;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function domainSummaries(): array
    {
        return $this->orderedDomainLinks()
            ->map(function (WebPropertyDomain $link): array {
                $domain = $link->domain;
                $platformRelation = $domain && $domain->relationLoaded('platform')
                    ? $domain->getRelation('platform')
                    : null;
                $platformType = $platformRelation instanceof WebsitePlatform
                    ? $platformRelation->platform_type
                    : $domain?->getAttribute('platform');

                return [
                    'id' => $domain?->id,
                    'domain' => $domain?->domain,
                    'usage_type' => $link->usage_type,
                    'is_canonical' => $link->is_canonical,
                    'status' => $domain?->is_active ? 'active' : 'inactive',
                    'platform' => $platformType,
                    'hosting_provider' => $domain?->hosting_provider,
                    'notes' => $link->notes,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function repositorySummaries(): array
    {
        $repositories = $this->relationLoaded('repositories') ? $this->repositories : $this->repositories()->get();

        return $repositories->map(fn (PropertyRepository $repository): array => [
            'id' => $repository->id,
            'repo_name' => $repository->repo_name,
            'repo_provider' => $repository->repo_provider,
            'repo_url' => $repository->repo_url,
            'local_path' => $repository->local_path,
            'default_branch' => $repository->default_branch,
            'deployment_branch' => $repository->deployment_branch,
            'framework' => $repository->framework,
            'is_primary' => $repository->is_primary,
            'notes' => $repository->notes,
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function analyticsSourceSummaries(): array
    {
        $sources = $this->relationLoaded('analyticsSources') ? $this->analyticsSources : $this->analyticsSources()->get();

        return $sources->map(fn (PropertyAnalyticsSource $source): array => [
            'id' => $source->id,
            'provider' => $source->provider,
            'external_id' => $source->external_id,
            'external_name' => $source->external_name,
            'workspace_path' => $source->workspace_path,
            'is_primary' => $source->is_primary,
            'status' => $source->status,
            'notes' => $source->notes,
            'install_audit' => $this->analyticsInstallAuditSummaryFor($source),
        ])->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tagSummaries(): array
    {
        return $this->orderedDomainLinks()
            ->flatMap(fn (WebPropertyDomain $link) => $link->domain ? $link->domain->tags : [])
            ->unique('id')
            ->values()
            ->map(fn (DomainTag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
                'priority' => $tag->priority,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function deploymentSummary(): array
    {
        $latestDeployment = $this->orderedDomainLinks()
            ->flatMap(fn (WebPropertyDomain $link) => $link->domain ? $link->domain->deployments : [])
            ->sortByDesc('deployed_at')
            ->first();

        if (! $latestDeployment instanceof Deployment) {
            return [
                'latest_deployment' => null,
            ];
        }

        return [
            'latest_deployment' => [
                'id' => $latestDeployment->id,
                'domain' => $latestDeployment->domain?->domain,
                'git_commit' => $latestDeployment->git_commit,
                'notes' => $latestDeployment->notes,
                'deployed_at' => $latestDeployment->deployed_at->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function healthSummary(): array
    {
        $checkTypes = [
            'http',
            'ssl',
            'dns',
            'email_security',
            'seo',
            'security_headers',
            'reputation',
            'broken_links',
        ];

        $primaryDomain = $this->canonicalDomainLink()?->domain;
        $checks = [];
        $overallRank = 0;
        $perDomain = [];
        $activeAlerts = 0;

        foreach ($this->orderedDomainLinks() as $link) {
            $domain = $link->domain;
            if (! $domain) {
                continue;
            }

            $domainChecks = [];
            foreach ($checkTypes as $checkType) {
                $attribute = 'latest_'.$checkType.'_status';
                $status = $domain->{$attribute} ?? 'unknown';
                $domainChecks[$checkType] = $status;
                $overallRank = max($overallRank, $this->statusRank($status));

                if ($primaryDomain && $primaryDomain->is($domain)) {
                    $checks[$checkType] = $status;
                }
            }

            $activeAlerts += $domain->alerts->count();

            $perDomain[] = [
                'domain' => $domain->domain,
                'usage_type' => $link->usage_type,
                'is_canonical' => $link->is_canonical,
                'checks' => $domainChecks,
                'active_alerts_count' => $domain->alerts->count(),
            ];
        }

        return [
            'overall_status' => $this->statusLabel($overallRank),
            'primary_domain' => $primaryDomain?->domain,
            'checks' => $checks,
            'active_alerts_count' => $activeAlerts,
            'per_domain' => $perDomain,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function brainSummary(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'property_type' => $this->property_type,
            'status' => $this->status,
            'primary_domain' => $this->primaryDomainName(),
            'production_url' => $this->production_url,
            'staging_url' => $this->staging_url,
            'platform' => $this->platform,
            'target_platform' => $this->target_platform,
            'owner' => $this->owner,
            'priority' => $this->priority,
            'notes' => $this->notes,
            'domains' => $this->domainSummaries(),
            'repositories' => $this->repositorySummaries(),
            'analytics_sources' => $this->analyticsSourceSummaries(),
            'health_summary' => $this->healthSummary(),
            'deployment_summary' => $this->deploymentSummary(),
            'tags' => $this->tagSummaries(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function statusRank(?string $status): int
    {
        return match ($status) {
            'fail' => 3,
            'warn' => 2,
            'ok' => 1,
            default => 0,
        };
    }

    private function statusLabel(int $rank): string
    {
        return match ($rank) {
            3 => 'fail',
            2 => 'warn',
            1 => 'ok',
            default => 'unknown',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function analyticsInstallAuditSummaryFor(PropertyAnalyticsSource $source): ?array
    {
        $audit = $source->relationLoaded('latestInstallAudit')
            ? $source->latestInstallAudit
            : $source->latestInstallAudit()->first();

        if (! $audit instanceof AnalyticsInstallAudit) {
            return null;
        }

        return [
            'install_verdict' => $audit->install_verdict,
            'expected_tracker_host' => $audit->expected_tracker_host,
            'best_url' => $audit->best_url,
            'detected_site_ids' => $audit->detected_site_ids ?? [],
            'detected_tracker_hosts' => $audit->detected_tracker_hosts ?? [],
            'summary' => $audit->summary,
            'checked_at' => $audit->checked_at?->toIso8601String(),
        ];
    }
}
