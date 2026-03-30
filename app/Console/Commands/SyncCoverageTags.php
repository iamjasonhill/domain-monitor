<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\DomainTag;
use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class SyncCoverageTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coverage:sync-tags
                            {--dry-run : Preview tag changes without writing them}
                            {--domain=* : Limit sync to one or more primary domain names}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync fleet coverage tags onto primary domains using repository, Matomo, and Search Console coverage state';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tagConfig = (array) config('domain_monitor.coverage_tags', []);
        $managedTagDefinitions = collect((array) ($tagConfig['tags'] ?? []))
            ->map(fn ($tag): ?array => $this->normalizeTagDefinition($tag))
            ->filter();

        $manualExclusionTag = (array) ($tagConfig['manual_exclusion_tag'] ?? []);
        $manualExclusionDefinition = $this->normalizeTagDefinition($manualExclusionTag) !== null
            ? collect([
                'manual_exclusion' => $this->normalizeTagDefinition($manualExclusionTag),
            ])
            : collect();

        $tagDefinitions = $managedTagDefinitions->merge($manualExclusionDefinition);

        if ($managedTagDefinitions->isEmpty()) {
            $this->warn('No coverage tags are configured.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $targetDomains = collect((array) $this->option('domain'))
            ->filter(fn ($domain) => is_string($domain) && $domain !== '')
            ->map(fn (string $domain) => mb_strtolower(trim($domain)))
            ->values();

        $tagDefinitionsArray = $tagDefinitions->all();
        $tagIds = $this->ensureTags($tagDefinitionsArray, $dryRun);

        $properties = WebProperty::query()
            ->with([
                'primaryDomain.tags',
                'primaryDomain.latestSeoBaseline',
                'repositories',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain',
            ])
            ->orderBy('name')
            ->get()
            ->filter(function (WebProperty $property) use ($targetDomains): bool {
                if ($targetDomains->isEmpty()) {
                    return true;
                }

                $domainName = $property->primaryDomainName();

                return is_string($domainName) && $targetDomains->contains(mb_strtolower($domainName));
            })
            ->values();

        if ($properties->isEmpty()) {
            $this->warn('No matching web properties found.');

            return self::SUCCESS;
        }

        $coverageTagNames = $managedTagDefinitions->pluck('name')->values();

        $rows = [];
        $requiredCount = 0;
        $completeCount = 0;
        $gapCount = 0;
        $excludedCount = 0;
        $domainsChanged = 0;

        foreach ($properties as $property) {
            $domain = $property->primaryDomainModel();
            if (! $domain instanceof Domain) {
                continue;
            }

            $summary = $property->fullCoverageSummary();
            $desiredTagNames = $this->desiredTagNames($summary, $managedTagDefinitions->all());
            $currentCoverageTagNames = $domain->tags
                ->pluck('name')
                ->intersect($coverageTagNames)
                ->values();

            $attachNames = $desiredTagNames->diff($currentCoverageTagNames)->values();
            $detachNames = $currentCoverageTagNames->diff($desiredTagNames)->values();

            if ($summary['required']) {
                $requiredCount++;
            }

            if ($summary['status'] === 'complete') {
                $completeCount++;
            } elseif ($summary['status'] === 'gap') {
                $gapCount++;
            } else {
                $excludedCount++;
            }

            if ($attachNames->isNotEmpty() || $detachNames->isNotEmpty()) {
                $domainsChanged++;
            }

            $rows[] = [
                $domain->domain,
                $property->slug,
                $summary['label'],
                $desiredTagNames->implode(', '),
                $summary['reason'] ?? '-',
            ];

            if ($dryRun) {
                if ($attachNames->isNotEmpty()) {
                    $this->line(sprintf(
                        '[dry-run] %s attach %s',
                        $domain->domain,
                        $attachNames->implode(', ')
                    ));
                }

                if ($detachNames->isNotEmpty()) {
                    $this->line(sprintf(
                        '[dry-run] %s detach %s',
                        $domain->domain,
                        $detachNames->implode(', ')
                    ));
                }

                continue;
            }

            if ($attachNames->isNotEmpty()) {
                $domain->tags()->syncWithoutDetaching(
                    $attachNames
                        ->map(fn (string $name): string => $tagIds[$name])
                        ->all()
                );
            }

            if ($detachNames->isNotEmpty()) {
                $domain->tags()->detach(
                    $detachNames
                        ->map(fn (string $name): string => $tagIds[$name])
                        ->all()
                );
            }
        }

        $this->table(
            ['Domain', 'Property', 'Coverage', 'Desired tags', 'Reason'],
            $rows
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['properties_considered', (string) $properties->count()],
                ['coverage_required', (string) $requiredCount],
                ['coverage_complete', (string) $completeCount],
                ['coverage_gaps', (string) $gapCount],
                ['coverage_excluded', (string) $excludedCount],
                ['domains_changed', (string) $domainsChanged],
                ['dry_run', $dryRun ? 'true' : 'false'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int|string, array{name: string, priority: int, color: string|null, description: string|null}>  $tagDefinitions
     * @return array<string, string>
     */
    private function ensureTags(array $tagDefinitions, bool $dryRun): array
    {
        $tagIds = [];

        foreach ($tagDefinitions as $definition) {
            $existingTag = DomainTag::withTrashed()->where('name', $definition['name'])->first();

            if ($dryRun) {
                $tagIds[$definition['name']] = $existingTag instanceof DomainTag ? $existingTag->id : $definition['name'];

                continue;
            }

            $tag = $existingTag ?? new DomainTag(['name' => $definition['name']]);
            $tag->fill([
                'priority' => $definition['priority'],
                'color' => $definition['color'],
                'description' => $definition['description'],
            ]);
            $tag->save();

            if ($tag->trashed()) {
                $tag->restore();
            }

            $tagIds[$definition['name']] = $tag->id;
        }

        return $tagIds;
    }

    /**
     * @param  array{
     *   required: bool,
     *   status: string
     * }  $summary
     * @param  array<int|string, array{name: string, priority: int, color: string|null, description: string|null}>  $tagDefinitions
     * @return Collection<int, string>
     */
    private function desiredTagNames(array $summary, array $tagDefinitions): Collection
    {
        $requiredTag = $tagDefinitions['required'] ?? null;
        $completeTag = $tagDefinitions['complete'] ?? null;
        $gapTag = $tagDefinitions['gap'] ?? null;
        $map = [
            'required' => is_array($requiredTag) ? $requiredTag['name'] : '',
            'complete' => is_array($completeTag) ? $completeTag['name'] : '',
            'gap' => is_array($gapTag) ? $gapTag['name'] : '',
        ];

        if (! $summary['required']) {
            return collect();
        }

        $tagNames = [$map['required']];

        if ($summary['status'] === 'complete') {
            $tagNames[] = $map['complete'];
        } else {
            $tagNames[] = $map['gap'];
        }

        /** @var array<int, string> $filteredTagNames */
        $filteredTagNames = array_values(array_filter(
            $tagNames,
            fn (string $name): bool => $name !== ''
        ));

        return collect($filteredTagNames);
    }

    /**
     * @return array{name: string, priority: int, color: string|null, description: string|null}|null
     */
    private function normalizeTagDefinition(mixed $tag): ?array
    {
        if (! is_array($tag) || ! is_string($tag['name'] ?? null)) {
            return null;
        }

        return [
            'name' => $tag['name'],
            'priority' => (int) ($tag['priority'] ?? 0),
            'color' => is_string($tag['color'] ?? null) ? $tag['color'] : null,
            'description' => is_string($tag['description'] ?? null) ? $tag['description'] : null,
        ];
    }
}
