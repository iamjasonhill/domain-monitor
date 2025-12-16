<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\BrainEventClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainCheckAlertingTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_alert_is_sent_only_on_third_consecutive_failure_and_then_recovers(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create();

        $brain = $this->mock(BrainEventClient::class);

        $brain->shouldReceive('sendAsync')->once()->withArgs(function (string $eventType, array $payload, array $options) use ($domain): bool {
            $this->assertSame('domain.check.http', $eventType);
            $this->assertSame($domain->id, $payload['domain_id']);
            $this->assertSame('http', $payload['check_type']);
            $this->assertSame('warn', $payload['status']);
            $this->assertSame('triggered', $payload['metadata']['alert_state'] ?? null);
            $this->assertSame(3, $payload['metadata']['threshold'] ?? null);
            $this->assertSame(3, $payload['metadata']['consecutive_failures'] ?? null);

            return true;
        });

        $brain->shouldReceive('sendAsync')->once()->withArgs(function (string $eventType, array $payload, array $options) use ($domain): bool {
            $this->assertSame('domain.check.http', $eventType);
            $this->assertSame($domain->id, $payload['domain_id']);
            $this->assertSame('http', $payload['check_type']);
            $this->assertSame('ok', $payload['status']);
            $this->assertSame('recovered', $payload['metadata']['alert_state'] ?? null);

            return true;
        });

        $this->createCheck($domain->id, 'http', 'fail'); // 1st failure
        $this->createCheck($domain->id, 'http', 'warn'); // 2nd failure
        $this->createCheck($domain->id, 'http', 'warn'); // 3rd failure -> triggers

        $this->createCheck($domain->id, 'http', 'fail'); // still active -> no re-trigger
        $this->createCheck($domain->id, 'http', 'ok'); // recovery -> sends recovered
    }

    public function test_ssl_alert_is_sent_only_on_third_consecutive_failure(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create();

        $brain = $this->mock(BrainEventClient::class);

        $brain->shouldReceive('sendAsync')->once()->withArgs(function (string $eventType, array $payload, array $options) use ($domain): bool {
            $this->assertSame('domain.check.ssl', $eventType);
            $this->assertSame($domain->id, $payload['domain_id']);
            $this->assertSame('ssl', $payload['check_type']);
            $this->assertSame('fail', $payload['status']);
            $this->assertSame('triggered', $payload['metadata']['alert_state'] ?? null);
            $this->assertSame(3, $payload['metadata']['threshold'] ?? null);

            return true;
        });

        $this->createCheck($domain->id, 'ssl', 'warn');
        $this->createCheck($domain->id, 'ssl', 'fail');
        $this->createCheck($domain->id, 'ssl', 'fail'); // triggers
    }

    public function test_non_http_ssl_checks_still_emit_immediately(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create();

        $brain = $this->mock(BrainEventClient::class);
        $brain->shouldReceive('sendAsync')->once();

        $this->createCheck($domain->id, 'dns', 'fail');
    }

    private function createCheck(string $domainId, string $type, string $status): DomainCheck
    {
        return DomainCheck::factory()->create([
            'domain_id' => $domainId,
            'check_type' => $type,
            'status' => $status,
            'started_at' => now()->subSeconds(2),
            'finished_at' => now()->subSecond(),
            'duration_ms' => 123,
            'retry_count' => 0,
        ]);
    }
}
