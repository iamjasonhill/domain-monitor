<?php

namespace App\Console\Commands;

use App\Services\FleetPropertyContextRefreshService;
use Illuminate\Console\Command;

class RefreshFleetContextCommand extends Command
{
    protected $signature = 'domains:refresh-fleet-context
                            {--property= : Web property slug to refresh}
                            {--force-search-console-api-enrichment : Force Search Console API enrichment refresh even if current enrichment is fresh}
                            {--stale-days= : Override the Search Console enrichment staleness threshold in days}';

    protected $description = 'Refresh the Fleet assessment context for a single web property';

    public function handle(FleetPropertyContextRefreshService $service): int
    {
        $propertySlug = $this->option('property');

        if (! is_string($propertySlug) || trim($propertySlug) === '') {
            $this->error('Please provide --property=<slug>.');

            return self::INVALID;
        }

        $staleDays = $this->validatedStaleDays();

        if ($this->option('stale-days') !== null && $staleDays === null) {
            $this->error('The --stale-days option must be an integer between 1 and 30.');

            return self::INVALID;
        }

        try {
            $summary = $service->refresh(
                trim($propertySlug),
                (bool) $this->option('force-search-console-api-enrichment'),
                $staleDays,
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $summary['success'] ? self::SUCCESS : self::FAILURE;
    }

    private function validatedStaleDays(): ?int
    {
        $value = $this->option('stale-days');

        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $days = (int) $value;

        if ($days < 1 || $days > 30) {
            return null;
        }

        return $days;
    }
}
