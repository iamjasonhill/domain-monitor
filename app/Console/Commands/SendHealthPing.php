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
            $this->warn('Brain not configured (BRAIN_BASE_URL or BRAIN_API_KEY missing)');
            Log::warning('Brain not configured, skipping health ping', [
                'base_url_set' => ! empty($baseUrl),
                'api_key_set' => ! empty($apiKey),
            ]);

            return Command::SUCCESS; // Don't fail the scheduled task if Brain isn't configured
        }

        $this->info("Sending heartbeat to Brain at {$baseUrl}...");

        try {
            // Send synchronously to ensure heartbeat arrives on time
            // Heartbeats are critical and should not be queued
            // Brain expects 'health.heartbeat' event type for heartbeat monitoring
            $result = $brain->send('health.heartbeat', [
                'site' => config('app.name', 'domain-monitor'),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env', 'production'),
            ], [
                'severity' => 'info',
                'fingerprint' => 'health.heartbeat:domain-monitor',
                'message' => 'ok',
            ]);

            if ($result === null) {
                $this->error('Failed to send heartbeat to Brain - check logs for details');
                Log::warning('Failed to send health heartbeat to Brain', [
                    'base_url' => $baseUrl,
                ]);
                // Still return SUCCESS to prevent scheduled task from failing
                // The Brain service will detect missed heartbeats and alert
            } else {
                $this->info('Heartbeat sent successfully to Brain');
                Log::debug('Health heartbeat sent to Brain', [
                    'result' => $result,
                ]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Exception sending heartbeat: {$e->getMessage()}");
            Log::error('Exception sending health heartbeat to Brain', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return SUCCESS to prevent scheduled task from failing
            // The Brain service will detect missed heartbeats and alert
            return Command::SUCCESS;
        }
    }
}
