<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class RefreshAutomationCoverage extends Command
{
    protected $signature = 'analytics:refresh-automation-coverage
                            {--domain= : Optional domain to refresh only one property}
                            {--skip-tags : Skip coverage tag sync after refreshing automation state}
                            {--skip-should-fix : Skip the should-fix queue refresh after refreshing automation state}';

    protected $description = 'Refresh Search Console automation coverage, sync baseline-ready domains, and update downstream fleet state.';

    public function handle(): int
    {
        $domainFilter = $this->normalizeDomain((string) $this->option('domain'));

        $this->info('Syncing Search Console coverage from Matomo...');

        $coverageExitCode = Artisan::call(
            'analytics:sync-search-console-coverage',
            $domainFilter ? ['--domain' => $domainFilter] : []
        );

        if ($coverageExitCode !== 0) {
            $this->error('Search Console coverage refresh failed.');

            return self::FAILURE;
        }

        $baselineDomains = $this->domainsNeedingBaselineSync($domainFilter);
        $baselineSynced = 0;
        $baselineFailed = 0;

        if ($baselineDomains->isEmpty()) {
            $this->info('No domains currently need a Search Console baseline sync.');
        }

        foreach ($baselineDomains as $domainName) {
            $this->line(sprintf('Syncing Search Console baseline for %s', $domainName));

            $baselineExitCode = Artisan::call('analytics:sync-search-console-baseline', [
                '--domain' => $domainName,
            ]);

            if ($baselineExitCode !== 0) {
                $baselineFailed++;
                $this->warn(sprintf('Baseline sync failed for %s.', $domainName));

                continue;
            }

            $baselineSynced++;
        }

        if (! $this->option('skip-tags')) {
            $this->info('Syncing coverage tags...');

            $tagExitCode = Artisan::call(
                'coverage:sync-tags',
                $domainFilter ? ['--domain' => $domainFilter] : []
            );

            if ($tagExitCode !== 0) {
                $this->error('Coverage tag sync failed.');

                return self::FAILURE;
            }
        }

        if (! $this->option('skip-should-fix')) {
            $this->info('Refreshing should-fix queue...');

            $queueExitCode = Artisan::call('domains:refresh-should-fix');

            if ($queueExitCode !== 0) {
                $this->error('Should-fix queue refresh failed.');

                return self::FAILURE;
            }
        }

        $this->info(sprintf(
            'Automation refresh finished: %d baseline sync(s) completed, %d failed.',
            $baselineSynced,
            $baselineFailed,
        ));

        return $baselineFailed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function domainsNeedingBaselineSync(?string $domainFilter): Collection
    {
        return WebProperty::query()
            ->with([
                'primaryDomain',
                'primaryDomain.tags',
                'primaryDomain.latestSeoBaseline',
                'repositories',
                'analyticsSources',
                'analyticsSources.latestInstallAudit',
                'analyticsSources.latestSearchConsoleCoverage',
                'propertyDomains.domain.tags',
            ])
            ->when(
                $domainFilter,
                fn ($query) => $query->whereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('domain', $domainFilter))
            )
            ->orderBy('name')
            ->get()
            ->filter(fn (WebProperty $property): bool => $property->automationCoverageSummary()['status'] === 'needs_baseline_sync')
            ->map(fn (WebProperty $property): ?string => $property->primaryDomainName())
            ->filter(fn (?string $domainName): bool => is_string($domainName) && $domainName !== '')
            ->unique()
            ->values();
    }

    private function normalizeDomain(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized !== '' ? $normalized : null;
    }
}
