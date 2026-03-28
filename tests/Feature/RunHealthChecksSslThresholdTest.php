<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\SslHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunHealthChecksSslThresholdTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_certificate_more_than_seven_days_from_expiry_stays_ok(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $this->fakeSslHealthCheck(daysUntilExpiry: 10);

        Artisan::call('domains:health-check', [
            '--domain' => $domain->domain,
            '--type' => 'ssl',
        ]);

        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'ssl',
            'status' => 'ok',
        ]);
    }

    public function test_valid_certificate_within_seven_days_of_expiry_warns(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.org',
        ]);

        $this->fakeSslHealthCheck(daysUntilExpiry: 7);

        Artisan::call('domains:health-check', [
            '--domain' => $domain->domain,
            '--type' => 'ssl',
        ]);

        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'ssl',
            'status' => 'warn',
        ]);
    }

    private function fakeSslHealthCheck(int $daysUntilExpiry): void
    {
        $this->app->instance(SslHealthCheck::class, new class($daysUntilExpiry) extends SslHealthCheck
        {
            public function __construct(private int $daysUntilExpiry) {}

            /**
             * @return array{is_valid: bool, expires_at: string|null, days_until_expiry: int|null, issuer: string|null, protocol: string|null, cipher: string|null, chain: array<int, array{subject: string, issuer: string, valid_to: string|null}>, error_message: string|null, payload: array<string, mixed>}
             */
            public function check(string $domain, int $timeout = 10): array
            {
                return [
                    'is_valid' => true,
                    'expires_at' => now()->addDays($this->daysUntilExpiry)->toIso8601String(),
                    'days_until_expiry' => $this->daysUntilExpiry,
                    'issuer' => 'Test CA',
                    'protocol' => 'TLSv1.3',
                    'cipher' => 'TLS_AES_256_GCM_SHA384',
                    'chain' => [],
                    'error_message' => null,
                    'payload' => [
                        'duration_ms' => 1,
                    ],
                ];
            }
        });
    }
}
