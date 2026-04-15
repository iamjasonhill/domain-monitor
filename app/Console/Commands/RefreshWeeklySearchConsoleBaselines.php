<?php

namespace App\Console\Commands;

use App\Models\WebProperty;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

class RefreshWeeklySearchConsoleBaselines extends Command
{
    protected $signature = 'analytics:refresh-weekly-search-console-baselines
                            {--domain= : Optional primary domain to refresh}
                            {--dry-run : Only list the domains that would refresh}';

    protected $description = 'Refresh weekly Search Console baseline snapshots for active Matomo-mapped properties.';

    public function handle(): int
    {
        if (! $this->matomoConfigured()) {
            $this->error('Matomo API credentials are not configured. Set MATOMO_BASE_URL and MATOMO_TOKEN_AUTH.');

            return self::FAILURE;
        }

        $domainFilter = $this->normalizeDomain((string) $this->option('domain'));
        $dryRun = (bool) $this->option('dry-run');
        $domains = $this->eligibleDomains($domainFilter);

        if ($domains->isEmpty()) {
            $this->info('No active Matomo-mapped properties currently need a weekly Search Console baseline sync.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            foreach ($domains as $domainName) {
                $this->line(sprintf('[dry-run] %s', $domainName));
            }

            $this->info(sprintf('Listed %d domain(s) for weekly Search Console baseline refresh.', $domains->count()));

            return self::SUCCESS;
        }

        $processed = 0;
        $failed = 0;

        foreach ($domains as $domainName) {
            $this->line(sprintf('Refreshing weekly Search Console baseline for %s', $domainName));

            $exitCode = Artisan::call('analytics:sync-search-console-baseline', [
                '--domain' => $domainName,
                '--baseline-type' => 'weekly_checkpoint',
                '--captured-by' => 'domain-monitor-weekly',
                '--notes' => 'Scheduled weekly Search Console baseline snapshot.',
            ]);

            if ($exitCode !== 0) {
                $failed++;
                $this->warn(sprintf('Weekly baseline sync failed for %s.', $domainName));

                continue;
            }

            $processed++;
        }

        $this->info(sprintf(
            'Weekly Search Console baseline refresh finished: %d completed, %d failed.',
            $processed,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, non-empty-string>
     */
    private function eligibleDomains(?string $domainFilter): Collection
    {
        return WebProperty::query()
            ->with(['primaryDomain', 'analyticsSources'])
            ->where('status', 'active')
            ->whereHas('analyticsSources', function ($query): void {
                $query->where('provider', 'matomo')
                    ->where('status', 'active');
            })
            ->when(
                $domainFilter,
                fn ($query) => $query->whereHas('primaryDomain', fn ($domainQuery) => $domainQuery->where('domain', $domainFilter))
            )
            ->orderBy('name')
            ->get()
            ->map(fn (WebProperty $property): ?string => $property->primaryDomainName())
            ->filter(fn (?string $domainName): bool => is_string($domainName) && $domainName !== '')
            ->unique()
            ->values();
    }

    private function matomoConfigured(): bool
    {
        return filled(config('services.matomo.base_url')) && filled(config('services.matomo.token_auth'));
    }

    private function normalizeDomain(string $value): ?string
    {
        $normalized = trim(mb_strtolower($value));

        return $normalized !== '' ? $normalized : null;
    }
}
