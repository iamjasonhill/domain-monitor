<?php

namespace App\Console\Commands;

use App\Jobs\TestQueueJob;
use Illuminate\Console\Command;

class TestQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:test {--message= : Custom message for the test job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a test job to verify queue is working';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $message = $this->option('message') ?? 'Test queue job dispatched at '.now()->toDateTimeString();

        TestQueueJob::dispatch($message);

        $this->info('Test job dispatched successfully!');
        $this->info('Check Horizon dashboard to see the job being processed.');
        $this->info('Check logs for: "TestQueueJob executed"');

        return Command::SUCCESS;
    }
}
