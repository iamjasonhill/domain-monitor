<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use App\Services\FleetTechnicalSeoAuditRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RunFleetTechnicalSeoEstateAuditCommand extends Command
{
    protected $signature = 'monitoring:run-fleet-technical-seo-estate-audit
                            {--property=* : Web property slug(s) to include}
                            {--domain=* : Domain selector(s) to resolve to web properties}
                            {--limit=5 : Conservative maximum number of eligible properties to audit}
                            {--url-cap=25 : Maximum URLs to include in each bounded per-property audit}
                            {--dry-run : List selected properties without creating audit runs}
                            {--continue-on-failure : Continue auditing remaining properties after one property fails}';

    protected $description = 'Run conservative Fleet technical SEO audits across eligible web properties sequentially.';

    public function handle(FleetTechnicalSeoAuditRunner $runner): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $urlCap = max(1, (int) $this->option('url-cap'));
        $dryRun = (bool) $this->option('dry-run');
        $continueOnFailure = (bool) $this->option('continue-on-failure');
        $properties = $this->selectedProperties($limit);

        if ($properties->isEmpty()) {
            $this->warn('No eligible web properties matched the Fleet technical SEO estate audit scope.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Selected %d eligible web %s for Fleet technical SEO estate audit.',
            $properties->count(),
            $properties->count() === 1 ? 'property' : 'properties'
        ));

        if ($dryRun) {
            foreach ($properties as $property) {
                $this->line(sprintf('[dry-run] %s (%s)', $property->slug, $property->primaryDomainName() ?? 'no-domain'));
            }

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($properties as $property) {
            $this->line(sprintf('Running Fleet technical SEO audit for [%s]...', $property->slug));

            try {
                $run = $runner->run($property, $urlCap, 'operator_requested_estate');
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
    private function selectedProperties(int $limit): Collection
    {
        $propertyOptions = $this->optionStrings('property');
        $domainOptions = $this->optionStrings('domain');

        /** @var Collection<int, WebProperty> $properties */
        $properties = WebProperty::query()
            ->with(['primaryDomain', 'primaryDomain.tags', 'propertyDomains.domain', 'conversionSurfaces'])
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
            ->take($limit)
            ->values();

        return $properties;
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
