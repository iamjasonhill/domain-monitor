<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Services\SecurityHeadersHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RefreshShouldFixQueueCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_current_should_fix_checks(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'refresh-me.example.com',
            'is_active' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $this->app->instance(SecurityHeadersHealthCheck::class, new class extends SecurityHeadersHealthCheck
        {
            /**
             * @return array{
             *     is_valid: bool,
             *     verified: bool,
             *     score: int,
             *     headers: array<string, array{present: bool}>,
             *     error_message: string|null,
             *     payload: array<string, mixed>
             * }
             */
            public function check(string $domain, int $timeout = 10): array
            {
                return [
                    'is_valid' => true,
                    'verified' => true,
                    'score' => 100,
                    'headers' => [
                        'strict-transport-security' => ['present' => true],
                        'content-security-policy' => ['present' => true],
                    ],
                    'error_message' => null,
                    'payload' => [
                        'duration_ms' => 1,
                    ],
                ];
            }
        });

        Artisan::call('domains:refresh-should-fix');

        $latestCheck = DomainCheck::query()
            ->where('domain_id', $domain->id)
            ->where('check_type', 'security_headers')
            ->latest()
            ->first();

        $this->assertNotNull($latestCheck);
        $this->assertSame('ok', $latestCheck->status);
    }
}
