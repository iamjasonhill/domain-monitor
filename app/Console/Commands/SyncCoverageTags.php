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
    protected $description = 'Sync fleet coverage and automation checklist tags onto primary domains';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tagConfig = (array) config('domain_monitor.coverage_tags', []);
        $managedTagDefinitions = collect((array) ($tagConfig['tags'] ?? []))
            ->map(fn ($tag): ?array => $this->normalizeTagDefinition($tag))
            ->filter();
        $managedAutomationDefinitions = collect((array) ($tagConfig['automation_tags'] ?? []))
            ->map(fn ($tag): ?array => $this->normalizeTagDefinition($tag))
            ->filter();

        $manualExclusionTag = (array) ($tagConfig['manual_exclusion_tag'] ?? []);
        $manualExclusionDefinition = $this->normalizeTagDefinition($manualExclusionTag) !== null
            ? collect([
                'manual_exclusion' => $this->normalizeTagDefinition($manualExclusionTag),
            ])
            : collect();

        $tagDefinitions = $managedTagDefinitions
            ->values()
            ->concat($managedAutomationDefinitions->values())
            ->concat($manualExclusionDefinition->values());

        if ($managedTagDefinitions->isEmpty()) {
            $this->warn('No coverage tags are configured.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $targetDomains = collect((array) $this->option('domain'))
            ->filter(fn ($domain) => is_string($domain) && $domain !== '')
            ->map(fn (string $domain) => mb_strtolower(trim($domain)))
            ->values();

        $propertyGroups = WebProperty::query()
            ->when(
                $targetDomains->isNotEmpty(),
                fn ($query) => $query->whereHas('primaryDomain', fn ($domainQuery) => $domainQuery->whereIn('domain', $targetDomains->all()))
            )
            ->with([
                'primaryDomain.tags',
                'primaryDomain.latestSeoBaseline',
                'repositories',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain',
            ])
            ->orderBy('primary_domain_id')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (WebProperty $property): string => (string) ($property->primary_domain_id ?? $property->id))
            ->map(fn (Collection $group): ?Collection => $group->isEmpty() ? null : $group)
            ->filter()
            ->values();

        if ($propertyGroups->isEmpty()) {
            $this->warn('No matching web properties found.');

            return self::SUCCESS;
        }

        $tagDefinitionsArray = $tagDefinitions->all();
        $tagIds = $this->ensureTags($tagDefinitionsArray, $dryRun);
        $coverageTagNames = $managedTagDefinitions->pluck('name')->values();
        $automationTagNames = $managedAutomationDefinitions->pluck('name')->values();
        $managedTagNames = $coverageTagNames->merge($automationTagNames)->unique()->values();

        $rows = [];
        $requiredCount = 0;
        $completeCount = 0;
        $gapCount = 0;
        $excludedCount = 0;
        $automationRequiredCount = 0;
        $automationCompleteCount = 0;
        $automationGapCount = 0;
        $manualCsvPendingCount = 0;
        $domainsChanged = 0;

        foreach ($propertyGroups as $group) {
            /** @var Collection<int, WebProperty> $group */
            $property = $this->authoritativePropertyForPrimaryDomain($group);
            if (! $property instanceof WebProperty) {
                continue;
            }

            $domain = $property->primaryDomainModel();
            if (! $domain instanceof Domain) {
                continue;
            }

            $summary = $property->fullCoverageSummary();
            $automationSummary = $this->automationSummaryForPrimaryDomainGroup($group, $property);
            $desiredTagNames = $this->desiredTagNames($summary, $managedTagDefinitions->all());
            $desiredAutomationTagNames = $this->desiredAutomationTagNames(
                $automationSummary,
                $managedAutomationDefinitions->all(),
                $automationSummary['manual_csv_pending']
            );
            $desiredManagedTagNames = $desiredTagNames->merge($desiredAutomationTagNames)->unique()->values();
            $currentDomainTags = $domain->tags()->get();
            $currentManagedTagNames = $currentDomainTags
                ->pluck('name')
                ->intersect($managedTagNames)
                ->values();
            $attachNames = $desiredManagedTagNames->diff($currentManagedTagNames)->values();
            $detachNames = $currentManagedTagNames->diff($desiredManagedTagNames)->values();

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

            if ($automationSummary['required']) {
                $automationRequiredCount++;
            }

            if ($automationSummary['status'] === 'complete') {
                $automationCompleteCount++;
            } elseif ($automationSummary['status'] === 'manual_csv_pending') {
                $automationGapCount++;
                $manualCsvPendingCount++;
            } elseif ($automationSummary['status'] !== 'excluded') {
                $automationGapCount++;
            }

            if ($attachNames->isNotEmpty() || $detachNames->isNotEmpty()) {
                $domainsChanged++;
            }

            $rows[] = [
                $domain->domain,
                $property->slug,
                $summary['label'],
                $automationSummary['label'],
                $desiredTagNames->implode(', '),
                $desiredAutomationTagNames->implode(', '),
                $summary['reason'] ?? '-',
                $automationSummary['reason'] ?? '-',
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

            if ($attachNames->isEmpty() && $detachNames->isEmpty()) {
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
            ['Domain', 'Property', 'Coverage', 'Automation', 'Coverage tags', 'Automation tags', 'Coverage reason', 'Automation reason'],
            $rows
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['properties_considered', (string) $propertyGroups->count()],
                ['coverage_required', (string) $requiredCount],
                ['coverage_complete', (string) $completeCount],
                ['coverage_gaps', (string) $gapCount],
                ['coverage_excluded', (string) $excludedCount],
                ['automation_required', (string) $automationRequiredCount],
                ['automation_complete', (string) $automationCompleteCount],
                ['automation_gaps', (string) $automationGapCount],
                ['manual_csv_pending', (string) $manualCsvPendingCount],
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
     *   status: string,
     *   manual_csv_pending?: bool
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
     * @param  array{
     *   required: bool,
     *   status: string
     * }  $summary
     * @param  array<int|string, array{name: string, priority: int, color: string|null, description: string|null}>  $tagDefinitions
     * @return Collection<int, string>
     */
    private function desiredAutomationTagNames(array $summary, array $tagDefinitions, bool $manualCsvPending = false): Collection
    {
        $requiredTag = $tagDefinitions['required'] ?? null;
        $completeTag = $tagDefinitions['complete'] ?? null;
        $gapTag = $tagDefinitions['gap'] ?? null;
        $manualCsvPendingTag = $tagDefinitions['manual_csv_pending'] ?? null;
        $map = [
            'required' => is_array($requiredTag) ? $requiredTag['name'] : '',
            'complete' => is_array($completeTag) ? $completeTag['name'] : '',
            'gap' => is_array($gapTag) ? $gapTag['name'] : '',
            'manual_csv_pending' => is_array($manualCsvPendingTag) ? $manualCsvPendingTag['name'] : '',
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

        if ($manualCsvPending || $summary['status'] === 'manual_csv_pending') {
            $tagNames[] = $map['manual_csv_pending'];
        }

        /** @var array<int, string> $filteredTagNames */
        $filteredTagNames = array_values(array_filter(
            $tagNames,
            fn (string $name): bool => $name !== ''
        ));

        return collect($filteredTagNames);
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     * @return array{
     *   required: bool,
     *   status: string,
     *   label: string,
     *   reason: string|null,
     *   reasons: array<int, string>,
     *   checks: array<string, mixed>,
     *   manual_csv_pending: bool
     * }
     */
    private function automationSummaryForPrimaryDomainGroup(Collection $properties, WebProperty $authoritativeProperty): array
    {
        $summaries = $properties
            ->map(fn (WebProperty $property): array => $property->automationCoverageSummary())
            ->values();

        $requiredSummaries = $summaries
            ->filter(fn (array $summary): bool => $summary['required'])
            ->values();

        if ($requiredSummaries->isEmpty()) {
            $summary = $authoritativeProperty->automationCoverageSummary();
            $summary['manual_csv_pending'] = false;

            return $summary;
        }

        $manualCsvPending = $requiredSummaries->contains(
            fn (array $summary): bool => $summary['status'] === 'manual_csv_pending'
        );

        $blockingStatuses = [
            'needs_controller',
            'needs_matomo_binding',
            'needs_search_console_mapping',
            'needs_onboarding',
            'import_stale',
            'needs_baseline_sync',
        ];

        $blockingSummary = $requiredSummaries->first(
            fn (array $summary): bool => in_array($summary['status'], $blockingStatuses, true)
        );

        if (is_array($blockingSummary)) {
            return [
                'required' => true,
                'status' => 'gap',
                'label' => $blockingSummary['label'],
                'reason' => $blockingSummary['reason'] ?? null,
                'reasons' => $requiredSummaries->pluck('reason')->filter()->values()->all(),
                'checks' => ['group' => $requiredSummaries->all()],
                'manual_csv_pending' => $manualCsvPending,
            ];
        }

        if ($manualCsvPending) {
            $pendingSummary = $requiredSummaries->first(
                fn (array $summary): bool => $summary['status'] === 'manual_csv_pending'
            );

            return [
                'required' => true,
                'status' => 'manual_csv_pending',
                'label' => $pendingSummary['label'] ?? 'Manual CSV Pending',
                'reason' => $pendingSummary['reason'] ?? null,
                'reasons' => $requiredSummaries->pluck('reason')->filter()->values()->all(),
                'checks' => ['group' => $requiredSummaries->all()],
                'manual_csv_pending' => true,
            ];
        }

        return [
            'required' => true,
            'status' => 'complete',
            'label' => 'Complete',
            'reason' => 'All grouped properties on this primary domain are automation-complete',
            'reasons' => [],
            'checks' => ['group' => $requiredSummaries->all()],
            'manual_csv_pending' => false,
        ];
    }

    /**
     * @param  Collection<int, WebProperty>  $properties
     */
    private function authoritativePropertyForPrimaryDomain(Collection $properties): ?WebProperty
    {
        $canonicalProperties = $properties->filter(
            fn (WebProperty $property): bool => $this->isCanonicalForPrimaryDomain($property)
        );

        $candidates = $canonicalProperties->isNotEmpty() ? $canonicalProperties : $properties;

        $controllerBackedCandidates = $candidates->filter(
            fn (WebProperty $property): bool => $this->hasControllerPath($property)
        );

        if ($controllerBackedCandidates->isNotEmpty()) {
            $candidates = $controllerBackedCandidates;
        }

        /** @var WebProperty|null $property */
        $property = $candidates->sortBy('name')->first();

        return $property;
    }

    private function isCanonicalForPrimaryDomain(WebProperty $property): bool
    {
        if (! $property->primary_domain_id) {
            return false;
        }

        $links = $property->relationLoaded('propertyDomains')
            ? $property->propertyDomains
            : $property->propertyDomains()->get();

        return $links->contains(
            fn ($link): bool => (string) $link->domain_id === (string) $property->primary_domain_id
                && (bool) $link->is_canonical
        );
    }

    private function hasControllerPath(WebProperty $property): bool
    {
        $repositories = $property->relationLoaded('repositories')
            ? $property->repositories
            : $property->repositories()->get();

        return $repositories->contains(
            fn ($repository): bool => is_string($repository->local_path) && trim($repository->local_path) !== ''
        );
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
