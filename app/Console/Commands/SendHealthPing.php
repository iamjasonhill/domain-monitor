<?php

namespace App\Console\Commands;

use App\Services\BrainEventClient;
use Illuminate\Console\Command;

class SendHealthPing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:ping';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send health.ping event to Brain to indicate the app is alive';

    /**
     * Execute the console command.
     */
    public function handle(BrainEventClient $brain): int
    {
        $brain->sendAsync('health.ping', [
            'site' => config('app.name', 'domain-monitor'),
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env', 'production'),
        ], [
            'severity' => 'info',
            'fingerprint' => 'health.ping:domain-monitor',
            'message' => 'ok',
        ]);

        return Command::SUCCESS;
    }
}
