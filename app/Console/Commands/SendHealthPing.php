<?php

namespace App\Console\Commands;

use App\Services\BrainEventClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
        // Check if Brain is configured
        $baseUrl = config('services.brain.base_url');
        $apiKey = config('services.brain.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            Log::warning('Brain not configured, skipping health ping');

            return Command::SUCCESS; // Don't fail the scheduled task if Brain isn't configured
        }

        try {
            // Send synchronously to ensure heartbeat arrives on time
            // Heartbeats are critical and should not be queued
            $result = $brain->send('health.ping', [
                'site' => config('app.name', 'domain-monitor'),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env', 'production'),
            ], [
                'severity' => 'info',
                'fingerprint' => 'health.ping:domain-monitor',
                'message' => 'ok',
            ]);

            if ($result === null) {
                Log::warning('Failed to send health ping to Brain');
                // Still return SUCCESS to prevent scheduled task from failing
                // The Brain service will detect missed heartbeats and alert
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Exception sending health ping to Brain', [
                'error' => $e->getMessage(),
            ]);

            // Return SUCCESS to prevent scheduled task from failing
            // The Brain service will detect missed heartbeats and alert
            return Command::SUCCESS;
        }
    }
}
