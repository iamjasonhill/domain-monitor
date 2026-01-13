<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\UptimeIncident;
use App\Services\DomainCheckAlertingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UptimeIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_failing_uptime_check_creates_incident(): void
    {
        $domain = Domain::factory()->create(['domain' => 'test-uptime.com']);

        $check = DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'fail',
            'error_message' => 'Connection timed out',
            'started_at' => now()->subMinutes(1),
            'finished_at' => now(),
            'duration_ms' => 1000,
            'retry_count' => 0,
        ]);

        // Trigger alerting service
        app(DomainCheckAlertingService::class)->handle($check);

        $this->assertDatabaseHas('uptime_incidents', [
            'domain_id' => $domain->id,
            'status_code' => null,
            'error_message' => 'Connection timed out',
            'ended_at' => null,
        ]);
    }

    public function test_successful_uptime_check_closes_open_incident(): void
    {
        $domain = Domain::factory()->create(['domain' => 'test-uptime.com']);

        // Create an open incident
        $incident = UptimeIncident::create([
            'domain_id' => $domain->id,
            'started_at' => now()->subHours(1),
            'status_code' => 500,
        ]);

        $check = DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'ok',
            'started_at' => now()->subMinutes(1),
            'finished_at' => now(),
            'duration_ms' => 100,
            'retry_count' => 0,
        ]);

        // Trigger alerting service
        app(DomainCheckAlertingService::class)->handle($check);

        $incident->refresh();
        $this->assertNotNull($incident->ended_at);
        $this->assertEquals($check->finished_at->toDateTimeString(), $incident->ended_at->toDateTimeString());
    }

    public function test_repeated_failures_do_not_create_duplicate_incidents(): void
    {
        $domain = Domain::factory()->create(['domain' => 'test-uptime.com']);

        $service = app(DomainCheckAlertingService::class);

        // First failure
        $check1 = DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'fail',
            'started_at' => now()->subMinutes(2),
            'finished_at' => now()->subMinutes(1),
        ]);
        $service->handle($check1);

        $this->assertEquals(1, UptimeIncident::count());

        // Second failure
        $check2 = DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'fail',
            'started_at' => now()->subSeconds(30),
            'finished_at' => now(),
        ]);
        $service->handle($check2);

        $this->assertEquals(1, UptimeIncident::count());
    }
}
