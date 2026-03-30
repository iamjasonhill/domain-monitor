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
             *     headers: array<string, array{
             *         name: string,
             *         present: bool,
             *         value: string|null,
             *         status: string,
             *         recommendation: string|null,
             *         assessment: string
             *     }>,
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
                        'strict-transport-security' => [
                            'name' => 'strict-transport-security',
                            'present' => true,
                            'value' => 'max-age=31536000',
                            'status' => 'ok',
                            'recommendation' => null,
                            'assessment' => 'header present',
                        ],
                        'content-security-policy' => [
                            'name' => 'content-security-policy',
                            'present' => true,
                            'value' => "default-src 'self'",
                            'status' => 'ok',
                            'recommendation' => null,
                            'assessment' => 'header present',
                        ],
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

    public function test_it_can_scope_refreshes_to_one_domain(): void
    {
        $targetDomain = Domain::factory()->create([
            'domain' => 'target.example.com',
            'is_active' => true,
        ]);

        $otherDomain = Domain::factory()->create([
            'domain' => 'other.example.com',
            'is_active' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $targetDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $otherDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $this->app->instance(SecurityHeadersHealthCheck::class, new class extends SecurityHeadersHealthCheck
        {
            public int $calls = 0;

            /**
             * @return array{
             *     is_valid: bool,
             *     verified: bool,
             *     score: int,
             *     headers: array<string, array{
             *         name: string,
             *         present: bool,
             *         value: string|null,
             *         status: string,
             *         recommendation: string|null,
             *         assessment: string
             *     }>,
             *     error_message: string|null,
             *     payload: array<string, mixed>
             * }
             */
            public function check(string $domain, int $timeout = 10): array
            {
                $this->calls++;

                return [
                    'is_valid' => true,
                    'verified' => true,
                    'score' => 100,
                    'headers' => [
                        'strict-transport-security' => [
                            'name' => 'strict-transport-security',
                            'present' => true,
                            'value' => 'max-age=31536000',
                            'status' => 'ok',
                            'recommendation' => null,
                            'assessment' => 'header present',
                        ],
                        'content-security-policy' => [
                            'name' => 'content-security-policy',
                            'present' => true,
                            'value' => "default-src 'self'",
                            'status' => 'ok',
                            'recommendation' => null,
                            'assessment' => 'header present',
                        ],
                    ],
                    'error_message' => null,
                    'payload' => [
                        'domain' => $domain,
                        'duration_ms' => 1,
                    ],
                ];
            }
        });

        Artisan::call('domains:refresh-should-fix', [
            '--domain' => 'target.example.com',
        ]);

        $this->assertSame(2, DomainCheck::query()->where('domain_id', $targetDomain->id)->where('check_type', 'security_headers')->count());
        $this->assertSame(1, DomainCheck::query()->where('domain_id', $otherDomain->id)->where('check_type', 'security_headers')->count());
        $this->assertSame('ok', DomainCheck::query()->where('domain_id', $targetDomain->id)->where('check_type', 'security_headers')->latest()->first()?->status);
        $this->assertSame('warn', DomainCheck::query()->where('domain_id', $otherDomain->id)->where('check_type', 'security_headers')->latest()->first()?->status);
    }
}
