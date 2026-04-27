<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\UptimeHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunHealthChecksRepresentativeHostsTest extends TestCase
{
    use RefreshDatabase;

    public function test_representative_host_mode_checks_one_domain_per_ip_for_uptime(): void
    {
        Domain::factory()->create([
            'domain' => 'alpha.example.com',
            'ip_address' => '203.0.113.10',
        ]);
        Domain::factory()->create([
            'domain' => 'beta.example.com',
            'ip_address' => '203.0.113.10',
        ]);
        Domain::factory()->create([
            'domain' => 'gamma.example.com',
            'ip_address' => '203.0.113.20',
        ]);

        $this->app->instance(UptimeHealthCheck::class, new class extends UptimeHealthCheck
        {
            /**
             * @return array{is_valid: bool, status_code: int|null, duration_ms: int, error_message: string|null, payload: array<string, mixed>}
             */
            public function check(string $domain, int $timeout = 5): array
            {
                return [
                    'is_valid' => true,
                    'status_code' => 200,
                    'duration_ms' => 1,
                    'error_message' => null,
                    'payload' => [
                        'url' => 'https://'.$domain,
                        'status_code' => 200,
                        'duration_ms' => 1,
                    ],
                ];
            }
        });

        $exitCode = Artisan::call('domains:health-check', [
            '--all' => true,
            '--type' => 'uptime',
            '--representative-hosts' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('domain_checks', 2);
        $this->assertDatabaseHas('domain_checks', [
            'check_type' => 'uptime',
            'status' => 'ok',
        ]);
    }
}
