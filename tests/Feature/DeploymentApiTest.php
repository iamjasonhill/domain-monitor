<?php

namespace Tests\Feature;

use App\Jobs\SendDeploymentCompletedEventJob;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeploymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_deployment_endpoint_queues_brain_notification_job(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        Queue::fake();
        Http::fake();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->postJson('/api/deployments', [
            'domain' => $domain->domain,
            'commit' => '0123456789abcdef0123456789abcdef01234567',
            'notes' => 'release',
        ]);

        $response->assertCreated();

        Queue::assertPushed(SendDeploymentCompletedEventJob::class, function (SendDeploymentCompletedEventJob $job) use ($domain) {
            return $job->domain === $domain->domain
                && $job->deploymentId > 0
                && $job->gitCommit === '0123456789abcdef0123456789abcdef01234567';
        });

        Http::assertNothingSent();
    }
}
