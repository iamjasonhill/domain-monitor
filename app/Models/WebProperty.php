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

    public function primaryDomainModel(): ?Domain
    {
        $primaryDomain = $this->relationLoaded('primaryDomain') ? $this->primaryDomain : null;

        if ($primaryDomain instanceof Domain) {
            return $primaryDomain;
        }

        return $this->canonicalDomainLink()?->domain;
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
            'uptime',
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

    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function coverageEligibility(): array
    {
        if ($this->status !== 'active') {
            return ['eligible' => false, 'reason' => 'property is not active'];
        }

        if ($this->property_type === 'domain_asset') {
            return ['eligible' => false, 'reason' => 'property is a domain asset'];
        }

        $domain = $this->primaryDomainModel();
        if (! $domain instanceof Domain) {
            return ['eligible' => false, 'reason' => 'no primary domain linked'];
        }

        $manualExclusionTagName = (string) config('domain_monitor.coverage_tags.manual_exclusion_tag.name', '');
        if ($manualExclusionTagName !== '') {
            $domainTags = $domain->relationLoaded('tags')
                ? $domain->tags
                : $domain->tags()->get();

            if ($domainTags->contains(fn (DomainTag $tag): bool => $tag->name === $manualExclusionTagName)) {
                return ['eligible' => false, 'reason' => 'primary domain is manually excluded from fleet coverage'];
            }
        }

        if (! $domain->is_active) {
            return ['eligible' => false, 'reason' => 'primary domain is inactive'];
        }

        if ($domain->isParkedForHosting()) {
            return ['eligible' => false, 'reason' => 'primary domain is parked'];
        }

        if ($domain->isEmailOnly()) {
            return ['eligible' => false, 'reason' => 'primary domain is email-only'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * @return array{status: string, label: string, reason: string|null}
     */
    public function repositoryCoverageSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        if (! $eligibility['eligible']) {
            return [
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
            ];
        }

        $repositories = $this->relationLoaded('repositories')
            ? $this->repositories
            : $this->repositories()->get();

        $primaryRepository = $repositories
            ->sortByDesc(fn (PropertyRepository $repository) => $repository->is_primary)
            ->first();

        if (! $primaryRepository instanceof PropertyRepository) {
            return [
                'status' => 'needs_repository',
                'label' => 'Needs repository',
                'reason' => 'eligible property has no linked repository/controller surface',
            ];
        }

        return [
            'status' => 'covered',
            'label' => 'Covered',
            'reason' => sprintf(
                'primary repository %s is linked',
                $primaryRepository->repo_name
            ),
        ];
    }

    public function primaryAnalyticsSource(string $provider): ?PropertyAnalyticsSource
    {
        $sources = $this->relationLoaded('analyticsSources')
            ? $this->analyticsSources
            : $this->analyticsSources()->get();

        return $sources
            ->where('provider', $provider)
            ->sortByDesc(fn (PropertyAnalyticsSource $source) => $source->is_primary)
            ->first();
    }

    /**
     * @return array{eligible: bool, reason: string|null}
     */
    public function matomoEligibility(): array
    {
        if ($this->status !== 'active') {
            return ['eligible' => false, 'reason' => 'property is not active'];
        }

        if ($this->property_type === 'domain_asset') {
            return ['eligible' => false, 'reason' => 'property is a domain asset'];
        }

        $domain = $this->primaryDomainModel();
        if (! $domain instanceof Domain) {
            return ['eligible' => false, 'reason' => 'no primary domain linked'];
        }

        if (! $domain->is_active) {
            return ['eligible' => false, 'reason' => 'primary domain is inactive'];
        }

        if ($domain->isParkedForHosting()) {
            return ['eligible' => false, 'reason' => 'primary domain is parked'];
        }

        if ($domain->isEmailOnly()) {
            return ['eligible' => false, 'reason' => 'primary domain is email-only'];
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * @return array{status: string, label: string, reason: string|null}
     */
    public function matomoCoverageSummary(): array
    {
        $eligibility = $this->matomoEligibility();
        if (! $eligibility['eligible']) {
            return [
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
            ];
        }

        $source = $this->primaryAnalyticsSource('matomo');
        if (! $source instanceof PropertyAnalyticsSource) {
            return [
                'status' => 'needs_binding',
                'label' => 'Needs Matomo',
                'reason' => 'eligible property has no Matomo binding yet',
            ];
        }

        $audit = $source->relationLoaded('latestInstallAudit')
            ? $source->latestInstallAudit
            : $source->latestInstallAudit()->first();

        if (! $audit instanceof AnalyticsInstallAudit) {
            return [
                'status' => 'bound_unverified',
                'label' => 'Bound, not verified',
                'reason' => 'Matomo is linked but no install audit has been imported yet',
            ];
        }

        if ($audit->install_verdict === 'installed_match') {
            return [
                'status' => 'covered',
                'label' => 'Covered',
                'reason' => $audit->summary,
            ];
        }

        return [
            'status' => 'bound_attention',
            'label' => 'Needs attention',
            'reason' => $audit->summary,
        ];
    }

    /**
     * @return array{status: string, label: string, reason: string|null}
     */
    public function searchConsoleCoverageSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        if (! $eligibility['eligible']) {
            return [
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
            ];
        }

        $matomoSource = $this->primaryAnalyticsSource('matomo');
        if (! $matomoSource instanceof PropertyAnalyticsSource) {
            return [
                'status' => 'needs_matomo',
                'label' => 'Needs Matomo',
                'reason' => 'Search Console coverage depends on a primary Matomo binding',
            ];
        }

        $coverage = $matomoSource->relationLoaded('latestSearchConsoleCoverage')
            ? $matomoSource->latestSearchConsoleCoverage
            : $matomoSource->latestSearchConsoleCoverage()->first();

        if (! $coverage instanceof SearchConsoleCoverageStatus || $coverage->mapping_state === 'not_mapped') {
            return [
                'status' => 'needs_property',
                'label' => 'Needs Search Console',
                'reason' => 'Matomo is linked but no Search Console property is mapped yet',
            ];
        }

        if ($coverage->freshnessState() === 'never_imported') {
            return [
                'status' => 'needs_import',
                'label' => 'Needs import',
                'reason' => 'Search Console property is mapped but no data has been imported yet',
            ];
        }

        if ($coverage->freshnessState() === 'stale') {
            return [
                'status' => 'stale_import',
                'label' => 'Import stale',
                'reason' => 'Search Console coverage import is stale',
            ];
        }

        if ($coverage->mapping_state === 'url_prefix') {
            return [
                'status' => 'url_prefix_only',
                'label' => 'URL prefix only',
                'reason' => 'Search Console is mapped as a URL prefix instead of a domain property',
            ];
        }

        return [
            'status' => 'covered',
            'label' => 'Covered',
            'reason' => sprintf(
                'domain property is mapped and fresh for %s',
                $coverage->property_uri ?? $this->primaryDomainName() ?? $this->slug
            ),
        ];
    }

    /**
     * @return array{
     *   required: bool,
     *   status: string,
     *   label: string,
     *   reason: string|null,
     *   reasons: array<int, string>,
     *   checks: array<string, array{status: string, label: string, reason: string|null}>
     * }
     */
    public function fullCoverageSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        $checks = [
            'repository' => $this->repositoryCoverageSummary(),
            'matomo' => $this->matomoCoverageSummary(),
            'search_console' => $this->searchConsoleCoverageSummary(),
        ];

        if (! $eligibility['eligible']) {
            return [
                'required' => false,
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
                'reasons' => array_filter([$eligibility['reason']]),
                'checks' => $checks,
            ];
        }

        $gapReasons = collect($checks)
            ->reject(fn (array $check): bool => $check['status'] === 'covered')
            ->pluck('reason')
            ->filter(fn ($reason): bool => is_string($reason) && $reason !== '')
            ->values()
            ->all();

        if ($gapReasons === []) {
            return [
                'required' => true,
                'status' => 'complete',
                'label' => 'Complete',
                'reason' => 'repository, Matomo, and Search Console coverage are all in place',
                'reasons' => [],
                'checks' => $checks,
            ];
        }

        return [
            'required' => true,
            'status' => 'gap',
            'label' => 'Gap',
            'reason' => $gapReasons[0],
            'reasons' => $gapReasons,
            'checks' => $checks,
        ];
    }

    /**
     * @return array{status: string, label: string, reason: string|null}
     */
    public function baselineSyncSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        if (! $eligibility['eligible']) {
            return [
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
            ];
        }

        $searchConsole = $this->searchConsoleCoverageSummary();
        if ($searchConsole['status'] !== 'covered') {
            return [
                'status' => 'blocked',
                'label' => 'Blocked',
                'reason' => $searchConsole['reason'],
            ];
        }

        $primaryDomain = $this->primaryDomainModel();
        $latestBaseline = $primaryDomain && $primaryDomain->relationLoaded('latestSeoBaseline')
            ? $primaryDomain->latestSeoBaseline
            : $primaryDomain?->latestSeoBaseline()->first();

        if (! $latestBaseline instanceof DomainSeoBaseline) {
            return [
                'status' => 'needs_sync',
                'label' => 'Needs baseline sync',
                'reason' => 'Search Console data is ready, but no Domain Monitor SEO baseline has been synced yet',
            ];
        }

        if ($latestBaseline->captured_at->lt(now()->subDays(30))) {
            return [
                'status' => 'stale',
                'label' => 'Baseline stale',
                'reason' => 'The latest Domain Monitor SEO baseline is older than 30 days',
            ];
        }

        return [
            'status' => 'covered',
            'label' => 'Covered',
            'reason' => sprintf(
                'latest SEO baseline captured %s',
                $latestBaseline->captured_at->toDateString()
            ),
        ];
    }

    /**
     * @return array{status: string, label: string, reason: string|null}
     */
    public function manualCsvCoverageSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        if (! $eligibility['eligible']) {
            return [
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
            ];
        }

        $baseline = $this->baselineSyncSummary();
        if ($baseline['status'] !== 'covered') {
            return [
                'status' => 'blocked',
                'label' => 'Blocked',
                'reason' => $baseline['reason'],
            ];
        }

        $primaryDomain = $this->primaryDomainModel();
        $latestBaseline = $primaryDomain && $primaryDomain->relationLoaded('latestSeoBaseline')
            ? $primaryDomain->latestSeoBaseline
            : $primaryDomain?->latestSeoBaseline()->first();

        if (! $latestBaseline instanceof DomainSeoBaseline) {
            return [
                'status' => 'blocked',
                'label' => 'Blocked',
                'reason' => 'No SEO baseline is available yet',
            ];
        }

        if ($latestBaseline->import_method === 'matomo_plus_manual_csv') {
            return [
                'status' => 'covered',
                'label' => 'Covered',
                'reason' => 'Manual Search Console CSV evidence has been imported',
            ];
        }

        return [
            'status' => 'pending',
            'label' => 'Manual CSV pending',
            'reason' => 'Automation is in place, but no manual Search Console CSV evidence has been uploaded yet',
        ];
    }

    /**
     * @return array{
     *   required: bool,
     *   status: string,
     *   label: string,
     *   reason: string|null,
     *   reasons: array<int, string>,
     *   checks: array<string, array{status: string, label: string, reason: string|null}>
     * }
     */
    public function automationCoverageSummary(): array
    {
        $eligibility = $this->coverageEligibility();
        $checks = [
            'repository' => $this->repositoryCoverageSummary(),
            'matomo' => $this->matomoCoverageSummary(),
            'search_console' => $this->searchConsoleCoverageSummary(),
            'baseline_sync' => $this->baselineSyncSummary(),
            'manual_csv' => $this->manualCsvCoverageSummary(),
        ];

        if (! $eligibility['eligible']) {
            return [
                'required' => false,
                'status' => 'excluded',
                'label' => 'Excluded',
                'reason' => $eligibility['reason'],
                'reasons' => array_filter([$eligibility['reason']]),
                'checks' => $checks,
            ];
        }

        $repository = $checks['repository'];
        if ($repository['status'] !== 'covered') {
            return [
                'required' => true,
                'status' => 'needs_controller',
                'label' => 'Needs controller',
                'reason' => $repository['reason'],
                'reasons' => array_filter([$repository['reason']]),
                'checks' => $checks,
            ];
        }

        $matomo = $checks['matomo'];
        if ($matomo['status'] !== 'covered') {
            return [
                'required' => true,
                'status' => 'needs_matomo_binding',
                'label' => 'Needs Matomo',
                'reason' => $matomo['reason'],
                'reasons' => array_filter([$matomo['reason']]),
                'checks' => $checks,
            ];
        }

        $searchConsole = $checks['search_console'];
        if (in_array($searchConsole['status'], ['needs_matomo', 'needs_property', 'url_prefix_only'], true)) {
            return [
                'required' => true,
                'status' => 'needs_search_console_mapping',
                'label' => 'Needs Search Console',
                'reason' => $searchConsole['reason'],
                'reasons' => array_filter([$searchConsole['reason']]),
                'checks' => $checks,
            ];
        }

        if ($searchConsole['status'] === 'needs_import') {
            return [
                'required' => true,
                'status' => 'needs_onboarding',
                'label' => 'Needs onboarding',
                'reason' => $searchConsole['reason'],
                'reasons' => array_filter([$searchConsole['reason']]),
                'checks' => $checks,
            ];
        }

        if ($searchConsole['status'] === 'stale_import') {
            return [
                'required' => true,
                'status' => 'import_stale',
                'label' => 'Import stale',
                'reason' => $searchConsole['reason'],
                'reasons' => array_filter([$searchConsole['reason']]),
                'checks' => $checks,
            ];
        }

        $baseline = $checks['baseline_sync'];
        if (in_array($baseline['status'], ['needs_sync', 'stale'], true)) {
            return [
                'required' => true,
                'status' => 'needs_baseline_sync',
                'label' => 'Needs baseline sync',
                'reason' => $baseline['reason'],
                'reasons' => array_filter([$baseline['reason']]),
                'checks' => $checks,
            ];
        }

        $manualCsv = $checks['manual_csv'];
        if ($manualCsv['status'] === 'pending') {
            return [
                'required' => true,
                'status' => 'manual_csv_pending',
                'label' => 'Manual CSV pending',
                'reason' => $manualCsv['reason'],
                'reasons' => array_filter([$manualCsv['reason']]),
                'checks' => $checks,
            ];
        }

        return [
            'required' => true,
            'status' => 'complete',
            'label' => 'Complete',
            'reason' => 'All automatic coverage checks are in place and only optional manual evidence remains complete',
            'reasons' => [],
            'checks' => $checks,
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
