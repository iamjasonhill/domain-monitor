<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\DnsHealthCheck;
use App\Services\HttpHealthCheck;
use App\Services\SslHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ParkedOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_manually_parked_domain_is_skipped_for_all_health_checks(): void
    {
        $parked = Domain::factory()->create([
            'parked_override' => true,
            'parked_override_set_at' => now(),
        ]);

        $active = Domain::factory()->create([
            'parked_override' => false,
        ]);

        $this->fakeHealthChecks();

        Artisan::call('domains:health-check', [
            '--all' => true,
            '--type' => 'http',
        ]);

        $this->assertSame(0, DomainCheck::where('domain_id', $parked->id)->count());
        $this->assertSame(1, DomainCheck::where('domain_id', $active->id)->count());
    }

    public function test_manually_parked_domain_is_skipped_for_single_domain_health_check(): void
    {
        $parked = Domain::factory()->create([
            'parked_override' => true,
            'parked_override_set_at' => now(),
        ]);

        $this->fakeHealthChecks();

        Artisan::call('domains:health-check', [
            '--domain' => $parked->domain,
            '--type' => 'http',
        ]);

        $this->assertSame(0, DomainCheck::where('domain_id', $parked->id)->count());
    }

    private function fakeHealthChecks(): void
    {
        $this->app->instance(HttpHealthCheck::class, new class extends HttpHealthCheck
        {
            /**
             * @return array{is_up: bool, status_code: int|null, error_message: string|null, payload: array<string, mixed>}
             */
            public function check(string $domain, int $timeout = 10): array
            {
                return [
                    'is_up' => true,
                    'status_code' => 200,
                    'error_message' => null,
                    'payload' => [
                        'duration_ms' => 1,
                    ],
                ];
            }
        });

        $this->app->instance(SslHealthCheck::class, new class extends SslHealthCheck
        {
            /**
             * @return array{is_valid: bool, expires_at: string|null, days_until_expiry: int|null, issuer: string|null, error_message: string|null, payload: array<string, mixed>}
             */
            public function check(string $domain, int $timeout = 10): array
            {
                return [
                    'is_valid' => true,
                    'expires_at' => now()->addYear()->toIso8601String(),
                    'days_until_expiry' => 365,
                    'issuer' => null,
                    'error_message' => null,
                    'payload' => [
                        'duration_ms' => 1,
                    ],
                ];
            }
        });

        $this->app->instance(DnsHealthCheck::class, new class extends DnsHealthCheck
        {
            /**
             * @return array{is_valid: bool, has_a_record: bool, has_mx_record: bool, nameservers: array<int, string>, error_message: string|null, payload: array<string, mixed>}
             */
            public function check(string $domain): array
            {
                return [
                    'is_valid' => true,
                    'has_a_record' => true,
                    'has_mx_record' => true,
                    'nameservers' => [],
                    'error_message' => null,
                    'payload' => [
                        'duration_ms' => 1,
                    ],
                ];
            }
        });
    }
}
