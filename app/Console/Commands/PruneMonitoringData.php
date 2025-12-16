<?php

namespace App\Console\Commands;

use App\Models\DomainCheck;
use App\Models\DomainEligibilityCheck;
use App\Services\DomainMonitorSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PruneMonitoringData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:prune-monitoring-data
                            {--checks-days= : Retain domain_checks for N days (default from config)}
                            {--eligibility-days= : Retain domain_eligibility_checks for N days (default from config)}
                            {--dry-run : Show counts without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune old monitoring/history records (domain checks and eligibility checks)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $settings = app(DomainMonitorSettings::class);

        $checksDays = (int) ($this->option('checks-days') ?: $settings->pruneDomainChecksDays());
        $eligibilityDays = (int) ($this->option('eligibility-days') ?: $settings->pruneEligibilityChecksDays());
        $dryRun = (bool) $this->option('dry-run');

        $checksCutoff = now()->subDays(max(1, $checksDays));
        $eligibilityCutoff = now()->subDays(max(1, $eligibilityDays));

        $checksQuery = null;
        if (Schema::hasTable('domain_checks')) {
            $checksQuery = DomainCheck::query()->where('created_at', '<', $checksCutoff);
        } else {
            $this->warn('Skipping domain_checks prune: table does not exist.');
        }

        $eligibilityQuery = null;
        if (Schema::hasTable('domain_eligibility_checks')) {
            $eligibilityQuery = DomainEligibilityCheck::query()->where('checked_at', '<', $eligibilityCutoff);
        } else {
            $this->warn('Skipping domain_eligibility_checks prune: table does not exist.');
        }

        $checksCount = $checksQuery?->count() ?? 0;
        $eligibilityCount = $eligibilityQuery?->count() ?? 0;

        $this->info('Prune monitoring data:');
        $this->line("  Domain checks older than {$checksDays} days: {$checksCount}");
        $this->line("  Eligibility checks older than {$eligibilityDays} days: {$eligibilityCount}");

        if ($dryRun) {
            $this->warn('Dry run enabled — no records deleted.');

            return Command::SUCCESS;
        }

        $deletedChecks = $checksQuery?->delete() ?? 0;
        $deletedEligibility = $eligibilityQuery?->delete() ?? 0;

        $this->info("Deleted {$deletedChecks} domain check(s).");
        $this->info("Deleted {$deletedEligibility} eligibility check(s).");

        return Command::SUCCESS;
    }
}
