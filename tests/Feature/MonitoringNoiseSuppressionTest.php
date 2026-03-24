<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainCheckAlertState;
use Brain\Client\BrainEventClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringNoiseSuppressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_parked_http_failures_do_not_emit_alert_events_or_create_three_strike_state(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create([
            'domain' => 'parked-example.com',
            'is_active' => true,
            'platform' => 'Parked',
        ]);

        $brain = $this->mock(BrainEventClient::class);
        $brain->shouldNotReceive('sendAsync');

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        $this->assertDatabaseCount('domain_check_alert_states', 0);
    }

    public function test_inactive_domains_do_not_emit_alert_events_for_dns_failures(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create([
            'domain' => 'inactive-example.com',
            'is_active' => false,
        ]);

        $brain = $this->mock(BrainEventClient::class);
        $brain->shouldNotReceive('sendAsync');

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'dns',
            'status' => 'fail',
        ]);

        $this->assertDatabaseCount('domain_check_alert_states', 0);
    }

    public function test_email_only_uptime_failures_do_not_create_incidents(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'mail-only.example.com',
            'is_active' => true,
            'platform' => 'Email Only',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'fail',
        ]);

        $this->assertDatabaseCount('uptime_incidents', 0);
    }

    public function test_suppressed_http_check_resets_existing_alert_state(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $domain = Domain::factory()->create([
            'domain' => 'manual-parked.example.com',
            'is_active' => true,
            'parked_override' => true,
        ]);

        DomainCheckAlertState::create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'consecutive_failure_count' => 4,
            'alert_active' => true,
            'alerted_at' => now()->subHour(),
        ]);

        $brain = $this->mock(BrainEventClient::class);
        $brain->shouldNotReceive('sendAsync');

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        $state = DomainCheckAlertState::query()
            ->where('domain_id', $domain->id)
            ->where('check_type', 'http')
            ->firstOrFail();

        $this->assertSame(0, $state->consecutive_failure_count);
        $this->assertFalse($state->alert_active);
        $this->assertNotNull($state->recovered_at);
    }
}
