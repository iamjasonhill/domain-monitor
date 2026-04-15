<?php

namespace App\Models;

use App\Services\PropertyExecutionReadinessResolver;
use App\Services\WebPropertyCanonicalOriginSummaryBuilder;
use App\Services\WebPropertyGscEvidenceSummaryBuilder;
use App\Services\WebPropertySeoBaselineSummaryBuilder;
use App\Services\WebPropertySiteIdentitySummaryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $slug
 * @property string $name
 * @property string|null $site_identity_site_name
 * @property string|null $site_identity_legal_name
 * @property string $property_type
 * @property string $status
 * @property string|null $primary_domain_id
 * @property string|null $production_url
 * @property string|null $staging_url
 * @property string|null $canonical_origin_scheme
 * @property string|null $canonical_origin_host
 * @property string $canonical_origin_policy
 * @property bool $canonical_origin_enforcement_eligible
 * @property array<int, string>|null $canonical_origin_excluded_subdomains
 * @property bool $canonical_origin_sitemap_policy_known
 * @property string|null $platform
 * @property string|null $target_platform
 * @property string|null $owner
 * @property int|null $priority
 * @property string|null $notes
 * @property string|null $current_household_quote_url
 * @property string|null $current_household_booking_url
 * @property string|null $current_vehicle_quote_url
 * @property string|null $current_vehicle_booking_url
 * @property string|null $target_household_quote_url
 * @property string|null $target_household_booking_url
 * @property string|null $target_vehicle_quote_url
 * @property string|null $target_vehicle_booking_url
 * @property string|null $target_moveroo_subdomain_url
 * @property string|null $target_contact_us_page_url
 * @property string|null $target_legacy_bookings_replacement_url
 * @property string|null $target_legacy_payments_replacement_url
 * @property array<string, mixed>|null $legacy_moveroo_endpoint_scan
 * @property \Illuminate\Support\Carbon|null $conversion_links_scanned_at
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
        'site_identity_site_name',
        'site_identity_legal_name',
        'property_type',
        'status',
        'primary_domain_id',
        'production_url',
        'staging_url',
        'canonical_origin_scheme',
        'canonical_origin_host',
        'canonical_origin_policy',
        'canonical_origin_enforcement_eligible',
        'canonical_origin_excluded_subdomains',
        'canonical_origin_sitemap_policy_known',
        'platform',
        'target_platform',
        'owner',
        'priority',
        'notes',
        'current_household_quote_url',
        'current_household_booking_url',
        'current_vehicle_quote_url',
        'current_vehicle_booking_url',
        'target_household_quote_url',
        'target_household_booking_url',
        'target_vehicle_quote_url',
        'target_vehicle_booking_url',
        'target_moveroo_subdomain_url',
        'target_contact_us_page_url',
        'target_legacy_bookings_replacement_url',
        'target_legacy_payments_replacement_url',
        'conversion_links_scanned_at',
        'legacy_moveroo_endpoint_scan',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'canonical_origin_enforcement_eligible' => 'boolean',
            'canonical_origin_excluded_subdomains' => 'array',
            'canonical_origin_sitemap_policy_known' => 'boolean',
            'conversion_links_scanned_at' => 'datetime',
            'legacy_moveroo_endpoint_scan' => 'array',
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
     * @return HasMany<DomainSeoBaseline, $this>
     */
    public function seoBaselines(): HasMany
    {
        return $this->hasMany(DomainSeoBaseline::class)
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return HasMany<SearchConsoleIssueSnapshot, $this>
     */
    public function searchConsoleIssueSnapshots(): HasMany
    {
        return $this->hasMany(SearchConsoleIssueSnapshot::class)
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at');
    }

    /**
     * @return HasOne<DomainSeoBaseline, $this>
     */
    public function latestSeoBaselineForProperty(): HasOne
    {
        return $this->hasOne(DomainSeoBaseline::class)
            ->orderByDesc('captured_at')
            ->orderByDesc('created_at');
    }

    public function latestPropertySeoBaselineRecord(): ?DomainSeoBaseline
    {
        $latestBaseline = $this->relationLoaded('latestSeoBaselineForProperty')
            ? $this->latestSeoBaselineForProperty
            : $this->latestSeoBaselineForProperty()->first();

        return $latestBaseline instanceof DomainSeoBaseline ? $latestBaseline : null;
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

    public function searchConsolePropertyUri(): ?string
    {
        $matomoSource = $this->primaryAnalyticsSource('matomo');
        $coverage = $matomoSource instanceof PropertyAnalyticsSource
            ? ($matomoSource->relationLoaded('latestSearchConsoleCoverage')
                ? $matomoSource->latestSearchConsoleCoverage
                : $matomoSource->latestSearchConsoleCoverage()->first())
            : null;

        if ($coverage instanceof SearchConsoleCoverageStatus && is_string($coverage->property_uri) && $coverage->property_uri !== '') {
            return $coverage->property_uri;
        }

        return $this->latestPropertySeoBaselineRecord()?->search_console_property_uri;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function domainSummaries(bool $includeExternalLinkDetails = true): array
    {
        return $this->orderedDomainLinks()
            ->map(function (WebPropertyDomain $link) use ($includeExternalLinkDetails): array {
                $domain = $link->domain;
                $platformRelation = $domain && $domain->relationLoaded('platform')
                    ? $domain->getRelation('platform')
                    : null;
                $platformType = $platformRelation instanceof WebsitePlatform
                    ? $platformRelation->platform_type
                    : $domain?->getAttribute('platform');

                $summary = [
                    'id' => $domain?->id,
                    'domain' => $domain?->domain,
                    'usage_type' => $link->usage_type,
                    'email_usage' => $domain?->emailUsage() ?? Domain::EMAIL_USAGE_NONE,
                    'email_expected' => $domain?->emailExpected() ?? false,
                    'email_sending_expected' => $domain?->emailSendingExpected() ?? false,
                    'email_receiving_expected' => $domain?->emailReceivingExpected() ?? false,
                    'is_canonical' => $link->is_canonical,
                    'status' => $domain?->is_active ? 'active' : 'inactive',
                    'platform' => $platformType,
                    'hosting_provider' => $domain?->hosting_provider,
                    'notes' => $link->notes,
                ];

                if ($includeExternalLinkDetails) {
                    $summary['external_links_scan'] = $domain?->externalLinksSummary() ?? Domain::emptyExternalLinksSummary();
                }

                return $summary;
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
            'is_controller' => $repository->is_controller,
            'deployment_provider' => $repository->deployment_provider,
            'deployment_project_name' => $repository->deployment_project_name,
            'deployment_project_id' => $repository->deployment_project_id,
            'notes' => $repository->notes,
        ])->values()->all();
    }

    public function controllerRepository(): ?PropertyRepository
    {
        return app(PropertyExecutionReadinessResolver::class)->controllerRepository($this);
    }

    /**
     * @return array{
     *   control_state:string,
     *   execution_surface:string|null,
     *   fleet_managed:bool,
     *   controller_repo:string|null,
     *   controller_repo_url:string|null,
     *   controller_local_path:string|null,
     *   deployment_provider:string|null,
     *   deployment_project_name:string|null,
     *   deployment_project_id:string|null
     * }
     */
    public function executionReadinessSummary(): array
    {
        return app(PropertyExecutionReadinessResolver::class)->executionReadinessForProperty($this);
    }

    public function isFleetManagedExecutionSurface(?string $executionSurface): bool
    {
        if (! is_string($executionSurface) || $executionSurface === '') {
            return false;
        }

        if (in_array($executionSurface, ['fleet_wordpress_controlled', 'astro_repo_controlled'], true)) {
            return true;
        }

        if ($executionSurface !== 'repository_controlled') {
            return false;
        }

        $domain = $this->primaryDomainName();
        $configuredAllowlist = config('domain_monitor.fleet_focus.repository_controlled_domains', []);
        $allowlist = is_array($configuredAllowlist)
            ? array_values(array_map(
                static fn (string $value): string => strtolower($value),
                array_filter($configuredAllowlist, static fn (mixed $value): bool => is_string($value) && $value !== '')
            ))
            : [];

        return is_string($domain) && in_array(strtolower($domain), $allowlist, true);
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

    public function isFleetFocus(): bool
    {
        $tagName = (string) config('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        if ($tagName === '') {
            return false;
        }

        $primaryDomain = $this->relationLoaded('primaryDomain')
            ? $this->primaryDomain
            : $this->primaryDomain()->with('tags')->first();

        if ($this->domainHasFleetFocusTag($primaryDomain, $tagName)) {
            return true;
        }

        return $this->orderedDomainLinks()->contains(function (WebPropertyDomain $link) use ($tagName): bool {
            return $link->is_canonical
                && $this->domainHasFleetFocusTag($link->domain, $tagName);
        });
    }

    /**
     * @param  Builder<WebProperty>  $query
     */
    public function scopeFleetFocus(Builder $query, bool $value = true): void
    {
        $tagName = (string) config('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        if ($tagName === '') {
            return;
        }

        $callback = function (Builder $builder) use ($tagName): void {
            $builder
                ->whereHas('primaryDomain.tags', fn (Builder $tagQuery) => $tagQuery->where('name', $tagName))
                ->orWhereHas('propertyDomains', function (Builder $linkQuery) use ($tagName): void {
                    $linkQuery
                        ->where('is_canonical', true)
                        ->whereHas('domain.tags', fn (Builder $tagQuery) => $tagQuery->where('name', $tagName));
                });
        };

        if ($value) {
            $query->where($callback);

            return;
        }

        $query->whereNot($callback);
    }

    private function domainHasFleetFocusTag(?Domain $domain, string $tagName): bool
    {
        if (! $domain instanceof Domain) {
            return false;
        }

        $tags = $domain->relationLoaded('tags')
            ? $domain->tags
            : $domain->tags()->get();

        return $tags->contains(fn (DomainTag $tag): bool => $tag->name === $tagName);
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
     * @return array{
     *   current: array{
     *     household_quote: string|null,
     *     household_booking: string|null,
     *     vehicle_quote: string|null,
     *     vehicle_booking: string|null
     *   },
     *   target: array{
     *     household_quote: string|null,
     *     household_booking: string|null,
     *     vehicle_quote: string|null,
     *     vehicle_booking: string|null,
     *     moveroo_subdomain: string|null,
     *     contact_us_page: string|null,
     *     legacy_bookings_replacement: string|null,
     *     legacy_payments_replacement: string|null
     *   },
     *   legacy_endpoints: array{
     *     legacy_booking_endpoint: array<string, mixed>|null,
     *     legacy_payment_endpoint: array<string, mixed>|null
     *   },
     *   scanned_at: string|null
     * }
     */
    public function conversionLinkSummary(): array
    {
        return [
            'current' => $this->currentConversionLinkSummary(),
            'target' => $this->targetConversionLinkSummary(),
            'legacy_endpoints' => $this->legacyEndpointConversionLinkSummary(),
            'scanned_at' => $this->conversion_links_scanned_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *   household_quote: string|null,
     *   household_booking: string|null,
     *   vehicle_quote: string|null,
     *   vehicle_booking: string|null
     * }
     */
    private function currentConversionLinkSummary(): array
    {
        return [
            'household_quote' => $this->current_household_quote_url,
            'household_booking' => $this->current_household_booking_url,
            'vehicle_quote' => $this->current_vehicle_quote_url,
            'vehicle_booking' => $this->current_vehicle_booking_url,
        ];
    }

    /**
     * @return array{
     *   household_quote: string|null,
     *   household_booking: string|null,
     *   vehicle_quote: string|null,
     *   vehicle_booking: string|null,
     *   moveroo_subdomain: string|null,
     *   contact_us_page: string|null,
     *   legacy_bookings_replacement: string|null,
     *   legacy_payments_replacement: string|null
     * }
     */
    private function targetConversionLinkSummary(): array
    {
        return [
            'household_quote' => $this->target_household_quote_url,
            'household_booking' => $this->target_household_booking_url,
            'vehicle_quote' => $this->targetVehicleQuoteTargetUrl(),
            'vehicle_booking' => $this->target_vehicle_booking_url,
            'moveroo_subdomain' => $this->target_moveroo_subdomain_url,
            'contact_us_page' => $this->target_contact_us_page_url,
            'legacy_bookings_replacement' => $this->target_legacy_bookings_replacement_url,
            'legacy_payments_replacement' => $this->target_legacy_payments_replacement_url,
        ];
    }

    private function targetVehicleQuoteTargetUrl(): ?string
    {
        if (is_string($this->target_vehicle_quote_url) && trim($this->target_vehicle_quote_url) !== '') {
            return $this->target_vehicle_quote_url;
        }

        if (! is_string($this->target_moveroo_subdomain_url) || trim($this->target_moveroo_subdomain_url) === '') {
            return null;
        }

        $parts = parse_url(trim($this->target_moveroo_subdomain_url));

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = strtolower((string) $parts['scheme']).'://'.Str::lower((string) $parts['host']);

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin.'/quote/vehicle';
    }

    /**
     * @return array{
     *   legacy_booking_endpoint: array<string, mixed>|null,
     *   legacy_payment_endpoint: array<string, mixed>|null
     * }
     */
    private function legacyEndpointConversionLinkSummary(): array
    {
        return [
            'legacy_booking_endpoint' => $this->legacyMoverooEndpointSummary(
                'legacy_booking_endpoint',
                $this->target_legacy_bookings_replacement_url,
            ),
            'legacy_payment_endpoint' => $this->legacyMoverooEndpointSummary(
                'legacy_payment_endpoint',
                $this->target_legacy_payments_replacement_url,
            ),
        ];
    }

    /**
     * @return array{
     *   scheme: string|null,
     *   host: string|null,
     *   base_url: string|null,
     *   policy: 'known'|'unknown',
     *   scope: 'property_only',
     *   enforcement_eligible: bool,
     *   owned_subdomains: array<int, string>,
     *   excluded_subdomains: array<int, string>,
     *   sitemap_policy_known: bool
     * }
     */
    public function canonicalOriginSummary(): array
    {
        return app(WebPropertyCanonicalOriginSummaryBuilder::class)->build($this);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function legacyMoverooEndpointSummary(string $classification, ?string $preferredReplacement): ?array
    {
        $scan = $this->legacy_moveroo_endpoint_scan;
        $entry = is_array($scan) && is_array($scan[$classification] ?? null)
            ? $scan[$classification]
            : null;

        if ($entry === null) {
            return null;
        }

        return [
            'classification' => $classification,
            'found_on' => is_string($entry['found_on'] ?? null) ? $entry['found_on'] : null,
            'url' => is_string($entry['url'] ?? null) ? $entry['url'] : null,
            'resolved_url' => $this->sanitizeLegacyResolvedUrl($entry['resolved_url'] ?? null),
            'resolved_status' => is_numeric($entry['resolved_status'] ?? null) ? (int) $entry['resolved_status'] : null,
            'resolved_host_changed' => is_bool($entry['resolved_host_changed'] ?? null) ? $entry['resolved_host_changed'] : null,
            'preferred_replacement' => $preferredReplacement,
        ];
    }

    private function sanitizeLegacyResolvedUrl(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $parts = parse_url($value);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $sanitizedUrl = strtolower((string) $parts['scheme']).'://'.$parts['host'];

        if (isset($parts['port'])) {
            $sanitizedUrl .= ':'.$parts['port'];
        }

        $path = (string) ($parts['path'] ?? '/');

        return $sanitizedUrl.($path !== '' ? $path : '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function brainSummary(bool $includeFullExternalLinks = true): array
    {
        $executionReadiness = $this->executionReadinessSummary();

        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'property_type' => $this->property_type,
            'status' => $this->status,
            'primary_domain' => $this->primaryDomainName(),
            'site_identity' => $this->siteIdentitySummary(),
            'production_url' => $this->production_url,
            'staging_url' => $this->staging_url,
            'canonical_origin' => $this->canonicalOriginSummary(),
            'platform' => $this->platform,
            'target_platform' => $this->target_platform,
            'owner' => $this->owner,
            'priority' => $this->priority,
            // Fleet consumers use a stable, explicitly named alias for manual work ordering.
            'fleet_priority' => $this->priority,
            'is_fleet_focus' => $this->isFleetFocus(),
            'notes' => $this->notes,
            'domains' => $this->domainSummaries($includeFullExternalLinks),
            'repositories' => $this->repositorySummaries(),
            'analytics_sources' => $this->analyticsSourceSummaries(),
            'health_summary' => $this->healthSummary(),
            'deployment_summary' => $this->deploymentSummary(),
            'tags' => $this->tagSummaries(),
            'control_state' => $executionReadiness['control_state'],
            'execution_surface' => $executionReadiness['execution_surface'],
            'fleet_managed' => $executionReadiness['fleet_managed'],
            'controller_repo' => $executionReadiness['controller_repo'],
            'controller_repo_url' => $executionReadiness['controller_repo_url'],
            'controller_local_path' => $executionReadiness['controller_local_path'],
            'deployment_provider' => $executionReadiness['deployment_provider'],
            'deployment_project_name' => $executionReadiness['deployment_project_name'],
            'deployment_project_id' => $executionReadiness['deployment_project_id'],
            'conversion_links' => $this->conversionLinkSummary(),
            'gsc_evidence_summary' => $this->gscEvidenceSummary(),
            'seo_baseline_summary' => $this->seoBaselineSummary(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *   site_name: string|null,
     *   legal_name: string|null,
     *   primary_domain: string|null,
     *   quote_portal: string|null,
     *   contact_page: string|null
     * }
     */
    public function siteIdentitySummary(): array
    {
        return app(WebPropertySiteIdentitySummaryBuilder::class)->build($this);
    }

    /**
     * @return array{
     *   has_issue_detail: bool,
     *   issue_detail_snapshot_count: int,
     *   latest_issue_detail_captured_at: string|null,
     *   has_api_enrichment: bool,
     *   api_snapshot_count: int,
     *   latest_api_captured_at: string|null
     * }
     */
    public function gscEvidenceSummary(): array
    {
        return app(WebPropertyGscEvidenceSummaryBuilder::class)->build($this);
    }

    /**
     * @return array{
     *   has_baseline: bool,
     *   latest: array{
     *     captured_at: string|null,
     *     baseline_type: string|null,
     *     indexed_pages: int|null,
     *     not_indexed_pages: int|null,
     *     clicks: float|null,
     *     impressions: float|null,
     *     ctr: float|null,
     *     average_position: float|null
     *   },
     *   trend: array{
     *     window: string,
     *     point_count: int,
     *     indexed_pages_delta: int|null,
     *     not_indexed_pages_delta: int|null,
     *     points: array<int, array{
     *       captured_at: string|null,
     *       baseline_type: string|null,
     *       indexed_pages: int|null,
     *       not_indexed_pages: int|null,
     *       clicks: float|null,
     *       impressions: float|null
     *     }>
     *   }
     * }
     */
    public function seoBaselineSummary(): array
    {
        return app(WebPropertySeoBaselineSummaryBuilder::class)->build($this);
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

        $latestBaseline = $this->latestPropertySeoBaselineRecord();

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
