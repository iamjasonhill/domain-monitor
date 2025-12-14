<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Services\PlatformDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DetectPlatformJob implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $domainId
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PlatformDetector $detector): void
    {
        $domain = Domain::find($this->domainId);

        if (! $domain) {
            Log::warning('DetectPlatformJob: Domain not found', [
                'domain_id' => $this->domainId,
            ]);

            return;
        }

        try {
            $result = $detector->detect($domain->domain);

            $domain->platform()->updateOrCreate(
                ['domain_id' => $domain->id],
                [
                    'platform_type' => $result['platform_type'],
                    'platform_version' => $result['platform_version'],
                    'admin_url' => $result['admin_url'],
                    'detection_confidence' => $result['detection_confidence'],
                    'last_detected' => now(),
                ]
            );

            // Also update the platform string field on the domain for filtering
            $domain->update(['platform' => $result['platform_type']]);

            Log::info('Platform detection completed', [
                'domain' => $domain->domain,
                'platform' => $result['platform_type'],
                'confidence' => $result['detection_confidence'],
            ]);
        } catch (\Exception $e) {
            Log::error('Platform detection failed', [
                'domain' => $domain->domain,
                'domain_id' => $this->domainId,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
