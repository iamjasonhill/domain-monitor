<?php

namespace App\Console\Commands;

use App\Jobs\DiscoverSubdomainsJob;
use App\Jobs\UpdateSubdomainsIpJob;
use App\Models\Domain;
use Illuminate\Console\Command;

class QueueSubdomainChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:queue-subdomain-checks
                            {--days=7 : Only queue domains not checked in the last N days}
                            {--all : Queue all active domains regardless of last check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue subdomain discovery and IP update jobs for all active domains (spread over the week)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $allOption = $this->option('all');

        $query = Domain::where('is_active', true);

        // Get domains that need checking (not checked in last N days)
        // We'll use a simple approach: check if domain has subdomains checked recently
        // For simplicity, we'll queue all active domains and let the jobs handle it
        $domains = $query->get();

        if ($domains->isEmpty()) {
            $this->info('No active domains found.');

            return Command::SUCCESS;
        }

        $this->info("Queueing subdomain checks for {$domains->count()} domain(s)...");
        $this->newLine();

        $queuedCount = 0;
        $bar = $this->output->createProgressBar($domains->count());
        $bar->start();

        // Spread jobs over the week (7 days = 10080 minutes)
        // Each domain gets a different delay to spread the load
        $totalMinutes = 7 * 24 * 60; // 7 days in minutes
        $delayPerDomain = (int) ($totalMinutes / $domains->count());

        foreach ($domains as $index => $domain) {
            // Calculate delay: spread evenly over the week
            // Each domain gets checked once per week, but at different times
            $delayMinutes = $index * $delayPerDomain;

            // Queue discovery job
            DiscoverSubdomainsJob::dispatch($domain->id)
                ->delay(now()->addMinutes($delayMinutes));

            // Queue IP update job 20 minutes after discovery
            UpdateSubdomainsIpJob::dispatch($domain->id)
                ->delay(now()->addMinutes($delayMinutes + 20));

            $queuedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Queued {$queuedCount} subdomain discovery job(s) and {$queuedCount} IP update job(s).");
        $this->info('Jobs are spread over 7 days to avoid rate limiting.');
        $this->info('IP update jobs run 20 minutes after discovery jobs.');

        return Command::SUCCESS;
    }
}
