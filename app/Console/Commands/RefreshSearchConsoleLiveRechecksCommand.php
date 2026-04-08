<?php

namespace App\Console\Commands;

use App\Services\SearchConsoleIssueLiveRecheckService;
use Illuminate\Console\Command;

class RefreshSearchConsoleLiveRechecksCommand extends Command
{
    protected $signature = 'analytics:refresh-search-console-live-rechecks
                            {--property= : Refresh one property slug only}
                            {--limit= : Max properties to refresh in one run}
                            {--captured-by= : Optional captured_by value}
                            {--dry-run : Only list the properties that would refresh}';

    protected $description = 'Refresh live Search Console sitemap and URL rechecks for eligible properties';

    public function handle(SearchConsoleIssueLiveRecheckService $service): int
    {
        $limit = $this->validatedLimit();

        if ($this->option('limit') !== null && $limit === null) {
            $this->error('The --limit option must be a positive integer.');

            return self::INVALID;
        }

        $result = $service->run(
            is_string($this->option('property')) ? $this->option('property') : null,
            $limit,
            is_string($this->option('captured-by')) && $this->option('captured-by') !== ''
                ? $this->option('captured-by')
                : null,
            (bool) $this->option('dry-run'),
        );

        if ($result['candidate_count'] === 0) {
            $this->info('No eligible properties currently need Search Console live rechecks.');

            return self::SUCCESS;
        }

        foreach ($result['properties'] as $propertyResult) {
            if ($result['dry_run']) {
                $this->line(sprintf(
                    '[dry-run] %s (latest live recheck: %s)',
                    $propertyResult['property_slug'],
                    $propertyResult['latest_live_captured_at'] ?? 'never'
                ));

                continue;
            }

            $this->line(sprintf(
                '%s %s (%d URLs checked)%s',
                ucfirst((string) ($propertyResult['status'] ?? 'skipped')),
                $propertyResult['property_slug'],
                (int) ($propertyResult['checked_url_count'] ?? 0),
                ($propertyResult['reason'] ?? null) ? ' ['.$propertyResult['reason'].']' : ''
            ));
        }

        foreach ($result['errors'] as $error) {
            $this->warn(sprintf('%s: %s', $error['property_slug'], $error['message']));
        }

        $this->info(sprintf(
            '%s %d of %d candidate properties for Search Console live rechecks.',
            $result['dry_run'] ? 'Listed' : 'Processed',
            $result['processed_count'],
            $result['candidate_count']
        ));

        if ($result['errors'] !== []) {
            return self::FAILURE;
        }

        $hasFailure = collect($result['properties'])->contains(
            fn (array $propertyResult): bool => ($propertyResult['status'] ?? null) === 'failed'
        );

        return $hasFailure ? self::FAILURE : self::SUCCESS;
    }

    private function validatedLimit(): ?int
    {
        $value = $this->option('limit');

        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $limit = (int) $value;

        return $limit > 0 ? $limit : null;
    }
}
