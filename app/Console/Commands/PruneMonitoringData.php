<?php

namespace App\Console\Commands;

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
                            {--alerts-days= : Retain domain_alerts for N days (default from config)}
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
        $alertsDays = (int) ($this->option('alerts-days') ?: $settings->pruneAlertsDays());
        $dryRun = (bool) $this->option('dry-run');

        $checksCutoff = now()->subDays(max(1, $checksDays));
        $eligibilityCutoff = now()->subDays(max(1, $eligibilityDays));
        $alertsCutoff = now()->subDays(max(1, $alertsDays));

        $checksQuery = null;
        if (Schema::hasTable((new \App\Models\DomainCheck)->getTable())) {
            $checksQuery = \App\Models\DomainCheck::query()->where('created_at', '<', $checksCutoff);
        } else {
            $this->warn('Skipping domain_checks prune: table does not exist.');
        }

        $eligibilityQuery = null;
        if (Schema::hasTable((new \App\Models\DomainEligibilityCheck)->getTable())) {
            $eligibilityQuery = \App\Models\DomainEligibilityCheck::query()->where('checked_at', '<', $eligibilityCutoff);
        } else {
            $this->warn('Skipping domain_eligibility_checks prune: table does not exist.');
        }

        $alertsQuery = null;
        if (Schema::hasTable((new \App\Models\DomainAlert)->getTable())) {
            $alertsQuery = \App\Models\DomainAlert::query()->where('created_at', '<', $alertsCutoff);
        } else {
            $this->warn('Skipping domain_alerts prune: table does not exist.');
        }

        $checksCount = $checksQuery?->count() ?? 0;
        $eligibilityCount = $eligibilityQuery?->count() ?? 0;
        $alertsCount = $alertsQuery?->count() ?? 0;

        $this->info('Prune monitoring data:');
        $this->line("  Domain checks older than {$checksDays} days: {$checksCount}");
        $this->line("  Eligibility checks older than {$eligibilityDays} days: {$eligibilityCount}");
        $this->line("  Domain alerts older than {$alertsDays} days: {$alertsCount}");

        if ($dryRun) {
            $this->warn('Dry run enabled — no records deleted.');

            return Command::SUCCESS;
        }

        $deletedChecks = $checksQuery?->delete() ?? 0;
        $deletedEligibility = $eligibilityQuery?->delete() ?? 0;
        $deletedAlerts = $alertsQuery?->delete() ?? 0;

        $this->info("Deleted {$deletedChecks} domain check(s).");
        $this->info("Deleted {$deletedEligibility} eligibility check(s).");
        $this->info("Deleted {$deletedAlerts} alert record(s).");

        return Command::SUCCESS;
    }
}
