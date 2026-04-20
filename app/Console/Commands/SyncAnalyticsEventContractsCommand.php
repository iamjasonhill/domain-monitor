<?php

namespace App\Console\Commands;

use App\Models\AnalyticsEventContract;
use App\Models\WebProperty;
use App\Models\WebPropertyEventContract;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class SyncAnalyticsEventContractsCommand extends Command
{
    protected $signature = 'analytics:sync-event-contracts
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Sync analytics event contracts and property assignments from configuration';

    public function handle(): int
    {
        $contracts = config('analytics_event_contracts.contracts', []);
        $dryRun = (bool) $this->option('dry-run');

        if (! is_array($contracts) || $contracts === []) {
            $this->error('No analytics event contracts were found in config/analytics_event_contracts.php.');

            return self::FAILURE;
        }

        $contractCreates = 0;
        $contractUpdates = 0;
        $assignmentCreates = 0;
        $assignmentUpdates = 0;

        foreach ($contracts as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $attributes = $this->contractAttributes($key, $definition);
            $contract = AnalyticsEventContract::query()->where('key', $key)->first();

            if (! $contract instanceof AnalyticsEventContract) {
                $contractCreates++;
                $this->line(sprintf('[create-contract] %s', $key));

                if (! $dryRun) {
                    $contract = AnalyticsEventContract::query()->create($attributes);
                } else {
                    continue;
                }
            } elseif ($this->contractNeedsUpdate($contract, $attributes)) {
                $contractUpdates++;
                $this->line(sprintf('[update-contract] %s', $key));

                if (! $dryRun) {
                    $contract->forceFill($attributes)->save();
                }
            }

            foreach ($this->matchedProperties($definition) as $property) {
                $assignmentAttributes = $this->assignmentAttributes($definition);

                $assignment = WebPropertyEventContract::query()
                    ->where('web_property_id', $property->id)
                    ->where('analytics_event_contract_id', $contract->id)
                    ->first();

                if (! $assignment instanceof WebPropertyEventContract) {
                    $assignmentCreates++;
                    $this->line(sprintf(
                        '[create-assignment] %s <- %s',
                        $property->slug,
                        $key
                    ));

                    if (! $dryRun) {
                        WebPropertyEventContract::query()->create([
                            'web_property_id' => $property->id,
                            'analytics_event_contract_id' => $contract->id,
                            ...$assignmentAttributes,
                        ]);
                    }

                    continue;
                }

                if ($this->assignmentNeedsUpdate($assignment, $assignmentAttributes)) {
                    $assignmentUpdates++;
                    $this->line(sprintf(
                        '[update-assignment] %s <- %s',
                        $property->slug,
                        $key
                    ));

                    if (! $dryRun) {
                        $assignment->forceFill($assignmentAttributes)->save();
                    }
                }
            }
        }

        $this->newLine();
        $this->info('Analytics event contract sync summary');
        $this->line(sprintf('Contracts created: %d', $contractCreates));
        $this->line(sprintf('Contracts updated: %d', $contractUpdates));
        $this->line(sprintf('Assignments created: %d', $assignmentCreates));
        $this->line(sprintf('Assignments updated: %d', $assignmentUpdates));

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No changes were written.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function contractAttributes(string $key, array $definition): array
    {
        return [
            'key' => $key,
            'name' => (string) Arr::get($definition, 'name', $key),
            'version' => (string) Arr::get($definition, 'version', 'v1'),
            'contract_type' => (string) Arr::get($definition, 'contract_type', 'ga4_web'),
            'status' => (string) Arr::get($definition, 'status', 'active'),
            'scope' => $this->nullableString(Arr::get($definition, 'scope')),
            'source_repo' => $this->nullableString(Arr::get($definition, 'source_repo')),
            'source_path' => $this->nullableString(Arr::get($definition, 'source_path')),
            'contract' => is_array(Arr::get($definition, 'contract')) ? Arr::get($definition, 'contract') : null,
            'notes' => $this->nullableString(Arr::get($definition, 'notes')),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function assignmentAttributes(array $definition): array
    {
        $assignment = is_array(Arr::get($definition, 'assignment')) ? Arr::get($definition, 'assignment') : [];

        return [
            'is_primary' => true,
            'rollout_status' => (string) Arr::get($assignment, 'rollout_status', 'defined'),
            'notes' => $this->nullableString(Arr::get($assignment, 'notes')),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function contractNeedsUpdate(AnalyticsEventContract $contract, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ($contract->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function assignmentNeedsUpdate(WebPropertyEventContract $assignment, array $attributes): bool
    {
        foreach ($attributes as $key => $value) {
            if ($assignment->{$key} !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return \Illuminate\Support\Collection<int, WebProperty>
     */
    private function matchedProperties(array $definition)
    {
        $assignment = is_array(Arr::get($definition, 'assignment')) ? Arr::get($definition, 'assignment') : [];
        $slugs = $this->stringList(Arr::get($assignment, 'property_slugs'));
        $excludeSlugs = $this->stringList(Arr::get($assignment, 'exclude_slugs'));
        $matchMode = $this->nullableString(Arr::get($assignment, 'match'));

        $query = WebProperty::query()->orderBy('slug');

        if ($matchMode === 'ga4_properties') {
            $query->whereHas('analyticsSources', fn ($analyticsQuery) => $analyticsQuery->where('provider', 'ga4'));
        }

        if ($slugs !== []) {
            $query->whereIn('slug', $slugs);
        }

        if ($excludeSlugs !== []) {
            $query->whereNotIn('slug', $excludeSlugs);
        }

        return $query->get();
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?string => $this->nullableString($item),
            $value
        )));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
