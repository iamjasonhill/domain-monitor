<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\UptimeIncident;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UptimeIncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_failing_uptime_check_creates_incident_after_three_strikes(): void
    {
        $domain = Domain::factory()->create(['domain' => 'test-uptime.com']);

        foreach (range(1, 3) as $attempt) {
            DomainCheck::create([
                'domain_id' => $domain->id,
                'check_type' => 'uptime',
                'status' => 'fail',
                'error_message' => 'Connection timed out',
                'started_at' => now()->subMinutes(3 - $attempt),
                'finished_at' => now(),
                'duration_ms' => 1000,
                'retry_count' => 0,
            ]);
        }

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

        $incident->refresh();
        $this->assertNotNull($incident->ended_at);
        $this->assertEquals($check->finished_at->toDateTimeString(), $incident->ended_at->toDateTimeString());
    }

    public function test_repeated_failures_do_not_create_duplicate_incidents(): void
    {
        $domain = Domain::factory()->create(['domain' => 'test-uptime.com']);

        foreach (range(1, 3) as $attempt) {
            DomainCheck::create([
                'domain_id' => $domain->id,
                'check_type' => 'uptime',
                'status' => 'fail',
                'started_at' => now()->subMinutes(4 - $attempt),
                'finished_at' => now()->subMinutes(3 - $attempt),
            ]);
        }

        $this->assertSame(1, UptimeIncident::count());

        // Second failure
        DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'uptime',
            'status' => 'fail',
            'started_at' => now()->subSeconds(30),
            'finished_at' => now(),
        ]);

        $this->assertSame(1, UptimeIncident::count());
    }
}
