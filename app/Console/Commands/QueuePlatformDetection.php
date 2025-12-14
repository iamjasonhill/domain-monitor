<?php

namespace App\Console\Commands;

use App\Jobs\DetectPlatformJob;
use App\Models\Domain;
use Illuminate\Console\Command;

class QueuePlatformDetection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:queue-platform-detection 
                            {--hours=24 : Only queue domains not checked in the last N hours}
                            {--all : Queue all active domains regardless of last check time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queue platform detection jobs for domains that need checking';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $allOption = $this->option('all');

        $query = Domain::where('is_active', true);

        if (! $allOption) {
            // Only queue domains that haven't been checked recently
            // Check domains table platform field or website_platforms last_detected
            $query->where(function ($q) use ($hours) {
                $q->whereNull('platform')
                    ->orWhereDoesntHave('platform')
                    ->orWhereHas('platform', function ($platformQuery) use ($hours) {
                        $platformQuery->whereNull('last_detected')
                            ->orWhere('last_detected', '<', now()->subHours($hours));
                    });
            });
        }

        $domains = $query->get();

        if ($domains->isEmpty()) {
            $this->info('No domains need platform detection.');

            return Command::SUCCESS;
        }

        $this->info("Queueing platform detection for {$domains->count()} domain(s)...");
        $this->newLine();

        $bar = $this->output->createProgressBar($domains->count());
        $bar->start();

        $queuedCount = 0;
        foreach ($domains as $domain) {
            DetectPlatformJob::dispatch($domain->id);
            $queuedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Queued {$queuedCount} platform detection job(s).");
        $this->info('Jobs will be processed by Horizon queue workers.');

        return Command::SUCCESS;
    }
}
