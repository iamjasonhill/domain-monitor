<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use App\Services\FleetTechnicalSeoAuditRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RunFleetTechnicalSeoEstateAuditCommand extends Command
{
    private const PROFILE_SMOKE = 'fleet_technical_seo_smoke';

    private const PROFILE_DEEP = 'fleet_technical_seo_deep';

    private const DEFAULT_URL_CAP = 25;

    /**
     * @var array<string, int>
     */
    private const PROFILE_URL_CAPS = [
        self::PROFILE_SMOKE => 3,
        self::PROFILE_DEEP => 25,
    ];

    protected $signature = 'monitoring:run-fleet-technical-seo-estate-audit
                            {--profile= : Optional scheduled audit profile: fleet_technical_seo_smoke or fleet_technical_seo_deep}
                            {--property=* : Web property slug(s) to include}
                            {--domain=* : Domain selector(s) to resolve to web properties}
                            {--limit=5 : Conservative maximum number of eligible properties to audit}
                            {--url-cap= : Maximum URLs to include in each bounded per-property audit}
                            {--dry-run : List selected properties without creating audit runs}
                            {--continue-on-failure : Continue auditing remaining properties after one property fails}';

    protected $description = 'Run conservative Fleet technical SEO audits across eligible web properties sequentially.';

    public function handle(FleetTechnicalSeoAuditRunner $runner): int
    {
        try {
            $profile = $this->profile();
        } catch (\InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $urlCap = $this->urlCap($profile);
        $dryRun = (bool) $this->option('dry-run');
        $continueOnFailure = (bool) $this->option('continue-on-failure');
        $properties = $this->selectedProperties($limit, $profile);

        if ($properties->isEmpty()) {
            $this->warn('No eligible web properties matched the Fleet technical SEO estate audit scope.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Selected %d eligible web %s for Fleet technical SEO estate audit%s.',
            $properties->count(),
            $properties->count() === 1 ? 'property' : 'properties',
            $profile !== null ? ' profile ['.$profile.']' : ''
        ));

        if ($dryRun) {
            foreach ($properties as $property) {
                $this->line(sprintf(
                    '[dry-run] %s (%s) profile=%s url_cap=%d',
                    $property->slug,
                    $property->primaryDomainName() ?? 'no-domain',
                    $profile ?? 'operator_requested_estate',
                    $urlCap
                ));
            }

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($properties as $property) {
            $this->line(sprintf('Running Fleet technical SEO audit for [%s]...', $property->slug));

            try {
                $run = $runner->run($property, $urlCap, $profile ?? 'operator_requested_estate');
                $counts = $run->summary_counts ?? [];

                $this->info(sprintf(
                    'Completed [%s]. Run [%s]. pass=%d fail=%d manual_review=%d unknown=%d not_applicable=%d skipped_due_to_limit=%d',
                    $property->slug,
                    $run->id,
                    (int) ($counts['pass'] ?? 0),
                    (int) ($counts['fail'] ?? 0),
                    (int) ($counts['manual_review'] ?? 0),
                    (int) ($counts['unknown'] ?? 0),
                    (int) ($counts['not_applicable'] ?? 0),
                    (int) ($counts['not_checked_due_to_limit'] ?? 0)
                ));
            } catch (\Throwable $exception) {
                $failures++;
                $this->error(sprintf('Failed [%s]: %s', $property->slug, $exception->getMessage()));

                if (! $continueOnFailure) {
                    return self::FAILURE;
                }
            }
        }

        if ($failures > 0) {
            $this->error(sprintf('Fleet technical SEO estate audit completed with %d failed %s.', $failures, $failures === 1 ? 'property' : 'properties'));

            return self::FAILURE;
        }

        $this->info('Fleet technical SEO estate audit complete.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, WebProperty>
     */
    private function selectedProperties(int $limit, ?string $profile): Collection
    {
        $propertyOptions = $this->optionStrings('property');
        $domainOptions = $this->optionStrings('domain');
        $usesExplicitSelectors = $propertyOptions !== [] || $domainOptions !== [];

        /** @var Collection<int, WebProperty> $properties */
        $properties = WebProperty::query()
            ->with([
                'primaryDomain',
                'primaryDomain.tags',
                'propertyDomains.domain',
                'conversionSurfaces',
                'fleetTechnicalSeoAuditRuns' => fn ($query) => $profile !== null
                    ? $query->where('trigger_type', $profile)->whereNotNull('finished_at')
                    : $query,
            ])
            ->when(
                $propertyOptions !== [],
                fn (Builder $query) => $query->whereIn('slug', $propertyOptions)
            )
            ->when(
                $domainOptions !== [],
                fn (Builder $query) => $query->where(function (Builder $selectorQuery) use ($domainOptions): void {
                    $selectorQuery
                        ->whereHas(
                            'propertyDomains.domain',
                            fn (Builder $domainQuery) => $domainQuery->whereIn('domain', $domainOptions)
                        )
                        ->orWhereHas(
                            'primaryDomain',
                            fn (Builder $domainQuery) => $domainQuery->whereIn('domain', $domainOptions)
                        );
                })
            )
            ->orderBy('slug')
            ->get()
            ->filter(fn (WebProperty $property): bool => $property->coverageEligibility()['eligible'])
            ->when(
                $profile !== null && ! $usesExplicitSelectors,
                fn (Collection $properties): Collection => $properties
                    ->sortBy(fn (WebProperty $property): int => $this->profileFreshnessSortValue($property))
            )
            ->take($limit)
            ->values();

        return $properties;
    }

    private function profile(): ?string
    {
        $profile = $this->option('profile');

        if ($profile === null || trim((string) $profile) === '') {
            return null;
        }

        $profile = trim((string) $profile);
        if (! array_key_exists($profile, self::PROFILE_URL_CAPS)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown Fleet technical SEO audit profile [%s]. Expected one of: %s.',
                $profile,
                implode(', ', array_keys(self::PROFILE_URL_CAPS))
            ));
        }

        return $profile;
    }

    private function urlCap(?string $profile): int
    {
        $option = $this->option('url-cap');
        if (is_numeric($option)) {
            return max(1, (int) $option);
        }

        if ($profile !== null) {
            return self::PROFILE_URL_CAPS[$profile];
        }

        return self::DEFAULT_URL_CAP;
    }

    private function profileFreshnessSortValue(WebProperty $property): int
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FleetTechnicalSeoAuditRun> $runs */
        $runs = $property->getRelation('fleetTechnicalSeoAuditRuns');
        $latestRun = $runs->first();

        return $latestRun?->finished_at?->getTimestamp() ?? 0;
    }

    /**
     * @return array<int, string>
     */
    private function optionStrings(string $name): array
    {
        $values = $this->option($name);

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->unique()
            ->values()
            ->all();
    }
}
