<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BootstrapWebProperties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'web-properties:bootstrap
                            {--dry-run : Preview changes without writing them}
                            {--domain=* : Limit bootstrap to one or more domain names}
                            {--refresh-links : Refresh repository and analytics links for already-linked properties}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Bootstrap web_properties from the existing domain inventory';

    /**
     * @var array<string, mixed>
     */
    private array $bootstrapConfig = [];

    /**
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $repoIndex = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->bootstrapConfig = (array) config('domain_monitor.web_property_bootstrap', []);
        $this->repoIndex = $this->buildRepoIndex();

        $targetDomains = collect((array) $this->option('domain'))
            ->filter(fn ($domain) => is_string($domain) && $domain !== '')
            ->map(fn (string $domain) => mb_strtolower(trim($domain)))
            ->values();

        $domains = Domain::query()
            ->where('is_active', true)
            ->when(
                $targetDomains->isNotEmpty(),
                fn ($query) => $query->whereIn('domain', $targetDomains->all())
            )
            ->orderBy('domain')
            ->get();

        if ($domains->isEmpty()) {
            $this->warn('No matching active domains found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $refreshLinks = (bool) $this->option('refresh-links');

        $createdProperties = 0;
        $linkedDomains = 0;
        $attachedRepositories = 0;
        $attachedAnalytics = 0;
        $refreshedTargetOverrides = 0;
        $skippedDomains = 0;

        foreach ($domains as $domain) {
            $domainName = mb_strtolower($domain->domain);
            $existingLink = WebPropertyDomain::query()
                ->where('domain_id', $domain->id)
                ->with('webProperty')
                ->first();

            $override = $this->domainOverride($domainName);

            if ($existingLink instanceof WebPropertyDomain) {
                $skippedDomains++;

                if ($refreshLinks && $existingLink->webProperty instanceof WebProperty) {
                    if ($this->syncPropertyTargetOverrides($existingLink->webProperty, $override, $dryRun)) {
                        $refreshedTargetOverrides++;
                    }

                    [$repoAttached, $analyticsAttached] = $this->syncPropertyLinks(
                        $existingLink->webProperty,
                        $domain,
                        $override,
                        $dryRun
                    );

                    $attachedRepositories += $repoAttached;
                    $attachedAnalytics += $analyticsAttached;
                }

                continue;
            }

            $propertyAttributes = $this->makePropertyAttributes($domain, $override);

            if ($dryRun) {
                $this->line(sprintf(
                    '[dry-run] would create property %s for %s',
                    $propertyAttributes['slug'],
                    $domain->domain
                ));

                [$repoAttached, $analyticsAttached] = $this->syncPropertyLinks(
                    new WebProperty($propertyAttributes),
                    $domain,
                    $override,
                    true
                );

                $createdProperties++;
                $linkedDomains++;
                $attachedRepositories += $repoAttached;
                $attachedAnalytics += $analyticsAttached;

                continue;
            }

            $property = WebProperty::create($propertyAttributes);

            WebPropertyDomain::create([
                'web_property_id' => $property->id,
                'domain_id' => $domain->id,
                'usage_type' => 'primary',
                'is_canonical' => true,
                'notes' => 'Bootstrapped from domain inventory.',
            ]);

            [$repoAttached, $analyticsAttached] = $this->syncPropertyLinks(
                $property,
                $domain,
                $override,
                false
            );

            $createdProperties++;
            $linkedDomains++;
            $attachedRepositories += $repoAttached;
            $attachedAnalytics += $analyticsAttached;
        }

        $summary = [
            'domains_considered' => $domains->count(),
            'properties_created' => $createdProperties,
            'domain_links_created' => $linkedDomains,
            'repositories_attached' => $attachedRepositories,
            'analytics_sources_attached' => $attachedAnalytics,
            'property_target_overrides_refreshed' => $refreshedTargetOverrides,
            'domains_skipped' => $skippedDomains,
            'dry_run' => $dryRun,
        ];

        $this->table(
            ['Metric', 'Value'],
            collect($summary)->map(fn ($value, $key) => [$key, (string) $value])->all()
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array{0:int,1:int}
     */
    private function syncPropertyLinks(WebProperty $property, Domain $domain, array $override, bool $dryRun): array
    {
        $repoAttached = 0;
        $analyticsAttached = 0;

        $repository = $this->resolveRepository($domain, $override);
        if (is_array($repository) && $this->syncRepositoryLink($property, $repository, $dryRun)) {
            $repoAttached++;
        }

        $analyticsSources = $this->resolveAnalyticsSources($override);
        foreach ($analyticsSources as $analyticsSource) {
            if (! $this->shouldAttachAnalytics($property, $analyticsSource['provider'], $analyticsSource['external_id'])) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] would attach analytics %s:%s to %s',
                    $analyticsSource['provider'],
                    $analyticsSource['external_id'],
                    $property->slug
                ));
            } else {
                PropertyAnalyticsSource::create([
                    'web_property_id' => $property->id,
                    ...$analyticsSource,
                ]);
            }

            $analyticsAttached++;
        }

        return [$repoAttached, $analyticsAttached];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function syncPropertyTargetOverrides(WebProperty $property, array $override, bool $dryRun): bool
    {
        $refreshableKeys = [
            'target_household_quote_url',
            'target_household_booking_url',
            'target_vehicle_quote_url',
            'target_vehicle_booking_url',
            'target_moveroo_subdomain_url',
            'target_contact_us_page_url',
            'target_legacy_bookings_replacement_url',
            'target_legacy_payments_replacement_url',
        ];

        $changedAttributes = [];

        foreach ($refreshableKeys as $key) {
            if (! array_key_exists($key, $override)) {
                continue;
            }

            $rawValue = $override[$key];

            if ($rawValue !== null && ! is_string($rawValue)) {
                continue;
            }

            $normalizedValue = is_string($rawValue) && trim($rawValue) !== ''
                ? trim($rawValue)
                : null;

            if ($property->getAttribute($key) === $normalizedValue) {
                continue;
            }

            $changedAttributes[$key] = $normalizedValue;
        }

        if ($changedAttributes === []) {
            return false;
        }

        if ($dryRun) {
            $this->line(sprintf(
                '  [dry-run] would refresh target overrides on %s: %s',
                $property->slug,
                implode(', ', array_keys($changedAttributes))
            ));

            return true;
        }

        $property->fill($changedAttributes);
        $property->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function makePropertyAttributes(Domain $domain, array $override): array
    {
        $slug = (string) ($override['slug'] ?? $this->defaultSlugForDomain($domain->domain));
        $propertyType = (string) ($override['property_type'] ?? $this->inferPropertyType($domain));
        $notes = trim(collect([
            'Bootstrapped from domain inventory. Review grouping and links.',
            $override['notes'] ?? null,
        ])->filter()->implode(' '));

        return [
            'slug' => $slug,
            'name' => (string) ($override['name'] ?? $domain->domain),
            'site_key' => $override['site_key'] ?? null,
            'property_type' => $propertyType,
            'status' => $domain->is_active ? 'active' : 'inactive',
            'primary_domain_id' => $domain->id,
            'production_url' => (string) ($override['production_url'] ?? "https://{$domain->domain}"),
            'staging_url' => $override['staging_url'] ?? null,
            'platform' => $override['platform'] ?? $domain->platform,
            'target_platform' => $override['target_platform'] ?? null,
            'owner' => $override['owner'] ?? null,
            'priority' => $override['priority'] ?? null,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    private function inferPropertyType(Domain $domain): string
    {
        if ($domain->dns_config_name === 'Parked') {
            return 'domain_asset';
        }

        return 'website';
    }

    private function defaultSlugForDomain(string $domainName): string
    {
        return Str::slug(str_replace('.', '-', mb_strtolower($domainName)));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildRepoIndex(): array
    {
        $websitesRoot = (string) data_get($this->bootstrapConfig, 'websites_root', '');
        if ($websitesRoot === '' || ! File::isDirectory($websitesRoot)) {
            return [];
        }

        /** @var Collection<int, string> $directories */
        $directories = collect(File::directories($websitesRoot));

        return $directories
            ->filter(fn (string $path) => File::exists($path.'/package.json'))
            ->map(function (string $path): array {
                $repoName = basename($path);
                $packageJson = json_decode((string) File::get($path.'/package.json'), true);
                $repositoryRemote = $this->detectRepositoryRemote($path, is_array($packageJson) ? $packageJson : []);

                return [
                    'match_key' => $this->normalizeRepoKey($repoName),
                    'repo_name' => $repoName,
                    'repo_provider' => $repositoryRemote['repo_provider'],
                    'repo_url' => $repositoryRemote['repo_url'],
                    'local_path' => $path,
                    'default_branch' => null,
                    'deployment_branch' => null,
                    'framework' => $this->detectFramework($repoName, is_array($packageJson) ? $packageJson : []),
                    'is_primary' => true,
                    'is_controller' => false,
                    'deployment_provider' => null,
                    'deployment_project_name' => null,
                    'deployment_project_id' => null,
                    'notes' => 'Auto-matched from local websites inventory.',
                ];
            })
            ->filter(fn (array $repository) => $repository['match_key'] !== '')
            ->groupBy('match_key')
            ->map(fn (Collection $items) => $items->values()->all())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $packageJson
     * @return array{repo_provider:string, repo_url:string|null}
     */
    private function detectRepositoryRemote(string $path, array $packageJson): array
    {
        $packageRepository = $packageJson['repository'] ?? null;
        $packageRepositoryUrl = is_string($packageRepository)
            ? $packageRepository
            : (is_array($packageRepository) ? ($packageRepository['url'] ?? null) : null);

        $normalizedPackageUrl = $this->normalizeRepositoryUrl(is_string($packageRepositoryUrl) ? $packageRepositoryUrl : null);
        if (is_string($normalizedPackageUrl)) {
            return [
                'repo_provider' => $this->repositoryProviderForUrl($normalizedPackageUrl),
                'repo_url' => $normalizedPackageUrl,
            ];
        }

        $gitConfigPath = $path.'/.git/config';
        if (! File::exists($gitConfigPath)) {
            return [
                'repo_provider' => 'local_only',
                'repo_url' => null,
            ];
        }

        $gitConfig = (string) File::get($gitConfigPath);
        $originSection = preg_split('/^\s*\[remote "origin"\]\s*$/m', $gitConfig, 2)[1] ?? null;
        if (! is_string($originSection)) {
            return [
                'repo_provider' => 'local_only',
                'repo_url' => null,
            ];
        }

        $originBlock = preg_split('/^\s*\[/', $originSection, 2)[0] ?? $originSection;
        if (! preg_match('/^\s*url\s*=\s*(.+)\s*$/m', $originBlock, $matches)) {
            return [
                'repo_provider' => 'local_only',
                'repo_url' => null,
            ];
        }

        $normalizedGitUrl = $this->normalizeRepositoryUrl(trim($matches[1]));

        return [
            'repo_provider' => is_string($normalizedGitUrl)
                ? $this->repositoryProviderForUrl($normalizedGitUrl)
                : 'local_only',
            'repo_url' => $normalizedGitUrl,
        ];
    }

    private function normalizeRepositoryUrl(?string $repositoryUrl): ?string
    {
        if (! is_string($repositoryUrl)) {
            return null;
        }

        $normalized = trim($repositoryUrl);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/^git\\+/', '', $normalized) ?? $normalized;

        if (preg_match('/^[^@\\s]+@([^:\\/\\s]+):(.+?)(?:\\.git)?$/', $normalized, $matches)) {
            return sprintf('https://%s/%s', $matches[1], $matches[2]);
        }

        if (preg_match('/^github:(.+?)(?:\\.git)?$/', $normalized, $matches)) {
            return 'https://github.com/'.$matches[1];
        }

        if (preg_match('/^ssh:\\/\\/[^@\\s]+@([^\\/\\s:]+)(?::\\d+)?\\/(.+?)(?:\\.git)?$/', $normalized, $matches)) {
            return sprintf('https://%s/%s', $matches[1], $matches[2]);
        }

        if (preg_match('/^https?:\\/\\//', $normalized) !== 1) {
            return null;
        }

        $parts = parse_url($normalized);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        $path = preg_replace('/\\.git$/', '', $parts['path']) ?? $parts['path'];

        return sprintf('%s://%s%s', $parts['scheme'], $parts['host'], $path);
    }

    private function repositoryProviderForUrl(string $repositoryUrl): string
    {
        $host = parse_url($repositoryUrl, PHP_URL_HOST);

        return match ($host) {
            'github.com' => 'github',
            'gitlab.com' => 'gitlab',
            'bitbucket.org' => 'bitbucket',
            default => 'git',
        };
    }

    private function normalizeRepoKey(string $repoName): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower($repoName)) ?: [];

        $filtered = collect($tokens)
            ->filter()
            ->reject(fn (string $token) => in_array($token, [
                'astro',
                'website',
                'site',
                'new',
                'app',
                'com',
                'net',
                'org',
                'au',
            ], true))
            ->values();

        return $filtered->implode('');
    }

    private function normalizeDomainKey(string $domainName): string
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower($domainName)) ?: [];

        $filtered = collect($tokens)
            ->filter()
            ->reject(fn (string $token) => in_array($token, [
                'com',
                'net',
                'org',
                'au',
                'click',
            ], true))
            ->values();

        return $filtered->implode('');
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>|null
     */
    private function resolveRepository(Domain $domain, array $override): ?array
    {
        $repoOverride = data_get($override, 'repository');
        if (is_array($repoOverride)) {
            return [
                'repo_name' => (string) ($repoOverride['repo_name'] ?? basename((string) ($repoOverride['local_path'] ?? 'repository'))),
                'repo_provider' => (string) ($repoOverride['repo_provider'] ?? 'local_only'),
                'repo_url' => $repoOverride['repo_url'] ?? null,
                'local_path' => $repoOverride['local_path'] ?? null,
                'default_branch' => $repoOverride['default_branch'] ?? null,
                'deployment_branch' => $repoOverride['deployment_branch'] ?? null,
                'framework' => $repoOverride['framework'] ?? null,
                'is_primary' => (bool) ($repoOverride['is_primary'] ?? true),
                'is_controller' => (bool) ($repoOverride['is_controller'] ?? false),
                'deployment_provider' => $repoOverride['deployment_provider'] ?? null,
                'deployment_project_name' => $repoOverride['deployment_project_name'] ?? null,
                'deployment_project_id' => $repoOverride['deployment_project_id'] ?? null,
                'notes' => $repoOverride['notes'] ?? 'Mapped from bootstrap override.',
            ];
        }

        $domainKey = $this->normalizeDomainKey($domain->domain);
        $matches = $this->repoIndex[$domainKey] ?? [];

        return count($matches) === 1 ? $matches[0] : null;
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<int, array<string, mixed>>
     */
    private function resolveAnalyticsSources(array $override): array
    {
        $sources = data_get($override, 'analytics_sources', []);

        if (! is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->filter(fn ($source) => is_array($source) && ! empty($source['provider']) && ! empty($source['external_id']))
            ->reject(fn (array $source): bool => $this->isLegacyMatomoSource($source))
            ->map(function (array $source): array {
                return [
                    'provider' => (string) $source['provider'],
                    'external_id' => (string) $source['external_id'],
                    'external_name' => $source['external_name'] ?? null,
                    'workspace_path' => $source['workspace_path'] ?? null,
                    'is_primary' => (bool) ($source['is_primary'] ?? true),
                    'status' => (string) ($source['status'] ?? 'active'),
                    'notes' => $source['notes'] ?? 'Mapped from bootstrap override.',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function isLegacyMatomoSource(array $source): bool
    {
        if (($source['provider'] ?? null) !== 'matomo') {
            return false;
        }

        return ! (bool) data_get($this->bootstrapConfig, 'attach_legacy_matomo_sources', false);
    }

    /**
     * @return array<string, mixed>
     */
    private function domainOverride(string $domainName): array
    {
        $overrides = (array) data_get($this->bootstrapConfig, 'overrides', []);

        $override = $overrides[$domainName] ?? [];

        return is_array($override) ? $override : [];
    }

    /**
     * @param  array<string, mixed>  $repository
     */
    private function syncRepositoryLink(WebProperty $property, array $repository, bool $dryRun): bool
    {
        if (! is_string($repository['repo_name'] ?? null)) {
            return false;
        }

        if (! $property->exists) {
            return true;
        }

        $existingRepository = $property->repositories()
            ->where('repo_name', $repository['repo_name'])
            ->first();

        if (! $existingRepository instanceof PropertyRepository) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] would attach repo %s to %s',
                    $repository['repo_name'],
                    $property->slug
                ));
            } else {
                PropertyRepository::create([
                    'web_property_id' => $property->id,
                    ...$repository,
                ]);
            }

            return true;
        }

        $fillableKeys = (new PropertyRepository)->getFillable();

        $changedAttributes = collect($repository)
            ->only($fillableKeys)
            ->filter(fn ($value, $key) => $existingRepository->getAttribute($key) !== $value)
            ->all();

        if ($changedAttributes === []) {
            return false;
        }

        if ($dryRun) {
            $this->line(sprintf(
                '  [dry-run] would refresh repo %s on %s',
                $repository['repo_name'],
                $property->slug
            ));

            return true;
        }

        $existingRepository->fill($changedAttributes);
        $existingRepository->save();

        return true;
    }

    private function shouldAttachAnalytics(WebProperty $property, string $provider, string $externalId): bool
    {
        if (! $property->exists) {
            return true;
        }

        return ! $property->analyticsSources()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $packageJson
     */
    private function detectFramework(string $repoName, array $packageJson): ?string
    {
        $repoName = mb_strtolower($repoName);
        $dependencies = array_merge(
            (array) ($packageJson['dependencies'] ?? []),
            (array) ($packageJson['devDependencies'] ?? [])
        );

        if (str_contains($repoName, 'astro') || array_key_exists('astro', $dependencies)) {
            return 'Astro';
        }

        if (array_key_exists('next', $dependencies)) {
            return 'Next.js';
        }

        if (array_key_exists('react', $dependencies)) {
            return 'React';
        }

        return null;
    }
}
