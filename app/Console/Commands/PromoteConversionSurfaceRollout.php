<?php

namespace App\Console\Commands;

use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyEventContract;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PromoteConversionSurfaceRollout extends Command
{
    protected $signature = 'conversion-surfaces:promote-rollout
                            {propertySlug? : Optional web property slug}
                            {--hostname=* : Limit to one or more hostnames}
                            {--runtime-path= : Limit to a specific runtime path}
                            {--exclude-property=* : Exclude one or more web property slugs}
                            {--surface-status=instrumented : Target conversion-surface rollout status}
                            {--event-contract-status= : Optional target event-contract rollout status}
                            {--evidence-source=codebase : Short evidence source label}
                            {--notes= : Evidence summary to append to matching records}
                            {--evidence-file=* : One or more evidence file paths}
                            {--dry-run : Report changes without writing them}}';

    protected $description = 'Promote conversion surfaces and related event contracts to a higher rollout state with evidence notes';

    /**
     * @var array<string, int>
     */
    private array $statusRank = [
        'defined' => 0,
        'instrumented' => 1,
        'verified' => 2,
    ];

    public function handle(): int
    {
        $propertySlug = $this->argument('propertySlug');
        $hostnames = $this->stringList($this->option('hostname'));
        $runtimePath = $this->nullableString($this->option('runtime-path'));
        $excludeProperties = $this->stringList($this->option('exclude-property'));
        $surfaceStatus = $this->normalizeStatus((string) $this->option('surface-status'));
        $eventContractStatus = $this->normalizeStatus($this->option('event-contract-status'));
        $evidenceSource = $this->nullableString($this->option('evidence-source')) ?? 'codebase';
        $notes = $this->nullableString($this->option('notes'));
        $evidenceFiles = $this->stringList($this->option('evidence-file'));
        $dryRun = (bool) $this->option('dry-run');

        if ($surfaceStatus === null) {
            $this->error('Surface status must be one of: defined, instrumented, verified.');

            return self::FAILURE;
        }

        if ($this->option('event-contract-status') !== null && $eventContractStatus === null) {
            $this->error('Event contract status must be one of: defined, instrumented, verified.');

            return self::FAILURE;
        }

        $query = WebPropertyConversionSurface::query()
            ->with(['webProperty', 'eventContractAssignment'])
            ->orderBy('hostname');

        if (is_string($propertySlug) && $propertySlug !== '') {
            $query->whereHas('webProperty', fn (Builder $builder) => $builder->where('slug', $propertySlug));
        }

        if ($hostnames !== []) {
            $query->whereIn('hostname', $hostnames);
        }

        if ($runtimePath !== null) {
            $query->where('runtime_path', $runtimePath);
        }

        if ($excludeProperties !== []) {
            $query->whereHas('webProperty', fn (Builder $builder) => $builder->whereNotIn('slug', $excludeProperties));
        }

        $surfaces = $query->get();

        if ($surfaces->isEmpty()) {
            $this->warn('No conversion surfaces matched the rollout-promotion scope.');

            return self::SUCCESS;
        }

        $surfaceUpdates = 0;
        $surfaceNoops = 0;
        $eventContractUpdates = 0;
        $eventContractNoops = 0;
        $updatedEventContractIds = [];
        $evidenceNote = $this->buildEvidenceNote($evidenceSource, $notes, $evidenceFiles);

        foreach ($surfaces as $surface) {
            $surfacePropertySlug = $surface->webProperty instanceof \App\Models\WebProperty
                ? $surface->webProperty->slug
                : 'unknown-property';
            $surfaceChanged = false;
            $surfaceAction = 'noop';

            if ($this->shouldPromote($surface->rollout_status, $surfaceStatus)) {
                $surface->rollout_status = $surfaceStatus;
                $surfaceChanged = true;
                $surfaceAction = 'promote';

                if ($surfaceStatus === 'verified' && ! $surface->verified_at instanceof Carbon) {
                    $surface->verified_at = now();
                }
            }

            if ($evidenceNote !== null) {
                $nextNotes = $this->appendNote($surface->notes, $evidenceNote);
                if ($nextNotes !== $surface->notes) {
                    $surface->notes = $nextNotes;
                    $surfaceChanged = true;
                    $surfaceAction = $surfaceAction === 'noop' ? 'note' : $surfaceAction.'+note';
                }
            }

            $this->line(sprintf(
                '[surface] %s <- %s (%s)',
                $surface->hostname,
                $surfacePropertySlug,
                $surfaceAction
            ));

            if ($surfaceChanged) {
                $surfaceUpdates++;

                if (! $dryRun) {
                    $surface->save();
                }
            } else {
                $surfaceNoops++;
            }

            if ($eventContractStatus === null) {
                continue;
            }

            $assignment = $surface->eventContractAssignment;
            if (! $assignment instanceof WebPropertyEventContract) {
                $assignment = $surface->webProperty?->primaryEventContractAssignment();
            }

            if (! $assignment instanceof WebPropertyEventContract) {
                $this->line(sprintf(
                    '[event-contract] %s <- skipped (no assignment)',
                    $surfacePropertySlug
                ));

                continue;
            }

            if (in_array($assignment->id, $updatedEventContractIds, true)) {
                continue;
            }

            $assignmentChanged = false;
            $assignmentAction = 'noop';

            if ($this->shouldPromote($assignment->rollout_status, $eventContractStatus)) {
                $assignment->rollout_status = $eventContractStatus;
                $assignmentChanged = true;
                $assignmentAction = 'promote';

                if ($eventContractStatus === 'verified' && ! $assignment->verified_at instanceof Carbon) {
                    $assignment->verified_at = now();
                }
            }

            if ($evidenceNote !== null) {
                $nextNotes = $this->appendNote($assignment->notes, $evidenceNote);
                if ($nextNotes !== $assignment->notes) {
                    $assignment->notes = $nextNotes;
                    $assignmentChanged = true;
                    $assignmentAction = $assignmentAction === 'noop' ? 'note' : $assignmentAction.'+note';
                }
            }

            $this->line(sprintf(
                '[event-contract] %s <- %s (%s)',
                $surfacePropertySlug,
                $eventContractStatus,
                $assignmentAction
            ));

            if ($assignmentChanged) {
                $eventContractUpdates++;
                $updatedEventContractIds[] = $assignment->id;

                if (! $dryRun) {
                    $assignment->save();
                }
            } else {
                $eventContractNoops++;
            }
        }

        $this->newLine();
        $this->info('Conversion surface rollout promotion summary');
        $this->line(sprintf('Surface updates: %d', $surfaceUpdates));
        $this->line(sprintf('Surface no-op: %d', $surfaceNoops));
        $this->line(sprintf('Event contract updates: %d', $eventContractUpdates));
        $this->line(sprintf('Event contract no-op: %d', $eventContractNoops));

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No changes were written.');
        }

        return self::SUCCESS;
    }

    private function shouldPromote(?string $currentStatus, string $targetStatus): bool
    {
        return ($this->statusRank[$currentStatus ?? 'defined'] ?? -1) < ($this->statusRank[$targetStatus] ?? -1);
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $normalized = $this->nullableString($value);

        if ($normalized === null) {
            return null;
        }

        return array_key_exists($normalized, $this->statusRank) ? $normalized : null;
    }

    /**
     * @param  array<int, mixed>|mixed  $values
     * @return array<int, string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            $single = $this->nullableString($values);

            return $single !== null ? [$single] : [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $value): ?string => $this->nullableString($value),
            $values
        )));
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<int, string>  $evidenceFiles
     */
    private function buildEvidenceNote(string $evidenceSource, ?string $notes, array $evidenceFiles): ?string
    {
        $parts = [];

        if ($notes !== null) {
            $parts[] = $notes;
        }

        if ($evidenceFiles !== []) {
            $parts[] = 'Files: '.implode(', ', $evidenceFiles);
        }

        if ($parts === []) {
            return null;
        }

        return sprintf(
            '[%s][source=%s] %s',
            now()->toDateString(),
            $evidenceSource,
            implode(' | ', $parts)
        );
    }

    private function appendNote(?string $currentNotes, string $note): string
    {
        $existing = $this->nullableString($currentNotes);

        if ($existing === null) {
            return $note;
        }

        if (str_contains($existing, $note)) {
            return $existing;
        }

        return $existing."\n".$note;
    }
}
