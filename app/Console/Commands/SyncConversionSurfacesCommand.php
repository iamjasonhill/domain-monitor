<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use App\Services\WebPropertyConversionSurfaceSyncService;
use Illuminate\Console\Command;

class SyncConversionSurfacesCommand extends Command
{
    protected $signature = 'conversion-surfaces:sync-target-subdomains
                            {propertySlug? : Optional web property slug}
                            {--dry-run : Report changes without writing them}';

    protected $description = 'Backfill first-class conversion surfaces from target quote-subdomain URLs';

    public function handle(WebPropertyConversionSurfaceSyncService $syncService): int
    {
        $propertySlug = $this->argument('propertySlug');
        $dryRun = (bool) $this->option('dry-run');

        $query = WebProperty::query()
            ->with([
                'primaryDomain',
                'propertyDomains',
                'analyticsSources',
                'eventContractAssignments.eventContract',
                'conversionSurfaces',
            ])
            ->whereNotNull('target_moveroo_subdomain_url')
            ->orderBy('slug');

        if (is_string($propertySlug) && $propertySlug !== '') {
            $query->where('slug', $propertySlug);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            $this->warn('No web properties matched the conversion-surface sync scope.');

            return self::SUCCESS;
        }

        $created = 0;
        $updated = 0;
        $noop = 0;
        $skipped = 0;

        foreach ($properties as $property) {
            $result = $syncService->syncTargetQuoteSurface($property, ! $dryRun);

            $this->line(sprintf(
                '[%s] %s hostname=%s domain=%s link=%s surface=%s',
                $property->slug,
                $result['status'],
                $result['hostname'] ?? 'n/a',
                $result['domain_action'],
                $result['link_action'],
                $result['surface_action'],
            ));

            match ($result['status']) {
                'created' => $created++,
                'updated', 'planned' => $updated++,
                'skipped' => $skipped++,
                default => $noop++,
            };
        }

        $this->newLine();
        $this->info('Conversion surface sync summary');
        $this->line(sprintf('Created: %d', $created));
        $this->line(sprintf('Updated: %d', $updated));
        $this->line(sprintf('No-op: %d', $noop));
        $this->line(sprintf('Skipped: %d', $skipped));

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry run complete. No changes were written.');
        }

        return self::SUCCESS;
    }
}
