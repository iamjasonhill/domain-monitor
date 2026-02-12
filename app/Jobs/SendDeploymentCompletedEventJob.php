<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDeploymentCompletedEventJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 15;

    public function __construct(
        public string $domain,
        public int $deploymentId,
        public ?string $gitCommit,
        public string $deployedAt,
    ) {}

    public function handle(): void
    {
        $brainUrl = config('services.brain.base_url');
        $brainKey = config('services.brain.api_key');

        if (! $brainUrl || ! $brainKey) {
            Log::warning('Brain not configured, skipping deployment notification');

            return;
        }

        $response = Http::timeout(5)
            ->async(false)
            ->retry(2, 250)
            ->withHeaders([
                'X-Brain-Key' => $brainKey,
            ])->post("{$brainUrl}/api/v1/events", [
                'event_type' => 'deployment.completed',
                'project' => 'domain-monitor',
                'payload' => [
                    'domain' => $this->domain,
                    'deployment_id' => $this->deploymentId,
                    'git_commit' => $this->gitCommit,
                    'deployed_at' => $this->deployedAt,
                ],
            ]);

        /** @var \Illuminate\Http\Client\Response $response */
        if ($response->successful()) {
            Log::info("Deployment event sent to Brain for {$this->domain}");

            return;
        }

        Log::error("Brain rejected deployment event: {$response->status()} - {$response->body()}");
    }
}
