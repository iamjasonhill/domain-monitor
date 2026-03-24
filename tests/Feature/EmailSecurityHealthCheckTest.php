<?php

namespace Tests\Feature;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Dns\Dns;
use Spatie\Dns\Records\TXT;
use Tests\TestCase;

class EmailSecurityHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_email_security_check_command(): void
    {
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
            'is_active' => true,
        ]);

        $this->mock(Dns::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRecords')
                ->with('example.com', 'TXT')
                ->andReturn([
                    new TXT(['host' => 'example.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=spf1 include:_spf.google.com -all']),
                ]);

            $mock->shouldReceive('getRecords')
                ->with('_dmarc.example.com', 'TXT')
                ->andReturn([
                    new TXT(['host' => '_dmarc.example.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=DMARC1; p=reject;']),
                ]);

            // Mock CAA
            $mock->shouldReceive('getRecords')
                ->with('example.com', 'CAA')
                ->andReturn([
                    new \Spatie\Dns\Records\CAA(['host' => 'example.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'CAA', 'tag' => 'issue', 'value' => 'letsencrypt.org', 'flags' => 0]),
                ]);
        });

        // Mock the EmailSecurityHealthCheck service to control getDnsKey
        $mockService = \Mockery::mock(\App\Services\EmailSecurityHealthCheck::class, [app(Dns::class)])->makePartial();
        $mockService->shouldReceive('getDnsKey')
            ->with('example.com')
            ->andReturn([['type' => 'DNSKEY']]); // Simulate DNSSEC being present

        $this->instance(\App\Services\EmailSecurityHealthCheck::class, $mockService);

        // Act
        $this->artisan('domains:health-check', ['--type' => 'email_security', '--domain' => 'example.com'])
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
            'status' => 'ok',
        ]);

        $check = $domain->checks()->latest()->first();
        $payload = $check->payload;

        $this->assertTrue($payload['spf']['valid']);
        $this->assertEquals('hard_fail', $payload['spf']['mechanism']);
        $this->assertEquals('ok', $payload['spf']['status']);

        $this->assertTrue($payload['dmarc']['valid']);
        $this->assertEquals('reject', $payload['dmarc']['policy']);
        $this->assertEquals('ok', $payload['dmarc']['status']);
        $this->assertEquals('ok', $payload['overall_status']);
        $this->assertTrue($payload['is_valid']);

        $this->assertTrue($payload['dnssec']['enabled']);
        $this->assertTrue($payload['caa']['present']);
        $this->assertCount(1, $payload['caa']['records']);
    }

    public function test_it_discovers_dkim_records(): void
    {
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'dkim-test.com',
            'is_active' => true,
        ]);

        $this->mock(Dns::class, function (MockInterface $mock) {
            // Standard mocks for other checks (keep them clean)
            $mock->shouldReceive('getRecords')->with('dkim-test.com', 'TXT')->andReturn([]);
            $mock->shouldReceive('getRecords')->with('_dmarc.dkim-test.com', 'TXT')->andReturn([]);
            $mock->shouldReceive('getRecords')->with('dkim-test.com', 'CAA')->andReturn([]);

            // Mock DKIM - Simulate finding a 'google' selector
            // We need to use a loose matching because the loop checks many selectors
            $mock->shouldReceive('getRecords')
                ->withArgs(function ($host, $type) {
                    return str_ends_with($host, '._domainkey.dkim-test.com') && $type === 'TXT';
                })
                ->andReturnUsing(function ($host) {
                    if ($host === 'google._domainkey.dkim-test.com') {
                        return [
                            new TXT(['host' => $host, 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkqh...']),
                        ];
                    }

                    return [];
                });
        });

        // Mock service for DNSSEC
        $mockService = \Mockery::mock(\App\Services\EmailSecurityHealthCheck::class, [app(Dns::class)])->makePartial();
        $mockService->shouldReceive('getDnsKey')->andReturn([]);
        $this->instance(\App\Services\EmailSecurityHealthCheck::class, $mockService);

        // Act
        $this->artisan('domains:health-check', ['--type' => 'email_security', '--domain' => 'dkim-test.com'])
            ->assertSuccessful();

        // Assert
        $check = $domain->checks()->latest()->first();
        $payload = $check->payload;

        $this->assertTrue($payload['dkim']['present']);
        $this->assertEquals('ok', $payload['dkim']['status']);
        $this->assertCount(1, $payload['dkim']['selectors']);
        $this->assertEquals('google', $payload['dkim']['selectors'][0]['selector']);
        $this->assertStringContainsString('v=DKIM1', $payload['dkim']['selectors'][0]['record']);
    }

    public function test_it_detects_invalid_spf_and_missing_dmarc(): void
    {
        // ... (existing content)
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'bad-config.com',
            'is_active' => true,
        ]);

        $this->mock(Dns::class, function (MockInterface $mock) {
            // Multiple SPF records = Invalid
            $mock->shouldReceive('getRecords')
                ->with('bad-config.com', 'TXT')
                ->andReturn([
                    new TXT(['host' => 'bad-config.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=spf1 -all']),
                    new TXT(['host' => 'bad-config.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=spf1 mx ~all']),
                ]);

            // Missing DMARC
            $mock->shouldReceive('getRecords')
                ->with('_dmarc.bad-config.com', 'TXT')
                ->andReturn([]);

            // Missing CAA
            $mock->shouldReceive('getRecords')
                ->with('bad-config.com', 'CAA')
                ->andReturn([]);
        });

        // Mock the EmailSecurityHealthCheck service to control getDnsKey
        $mockService = \Mockery::mock(\App\Services\EmailSecurityHealthCheck::class, [app(Dns::class)])->makePartial();
        $mockService->shouldReceive('getDnsKey')
            ->with('bad-config.com')
            ->andReturn([]); // Simulate DNSSEC being missing

        $this->instance(\App\Services\EmailSecurityHealthCheck::class, $mockService);

        // Act
        $this->artisan('domains:health-check', ['--type' => 'email_security', '--domain' => 'bad-config.com'])
            ->assertSuccessful();

        // Assert
        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
            'status' => 'fail', // Both failed
        ]);

        $check = $domain->checks()->latest()->first();
        $payload = $check->payload;

        $this->assertFalse($payload['spf']['valid']);
        $this->assertEquals('fail', $payload['spf']['status']);
        $this->assertStringContainsString('Multiple SPF records', $payload['spf']['error']);

        $this->assertFalse($payload['dmarc']['present']);
        $this->assertFalse($payload['dmarc']['valid']);
        $this->assertEquals('fail', $payload['dmarc']['status']);
        $this->assertEquals('fail', $payload['overall_status']);

        $this->assertFalse($payload['dnssec']['enabled']);
        $this->assertFalse($payload['caa']['present']);
    }

    public function test_it_marks_weak_but_verified_email_security_as_warn(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'review-needed.com',
            'is_active' => true,
        ]);

        $this->mock(Dns::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRecords')
                ->with('review-needed.com', 'TXT')
                ->andReturn([
                    new TXT(['host' => 'review-needed.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=spf1 include:_spf.google.com ~all']),
                ]);

            $mock->shouldReceive('getRecords')
                ->with('_dmarc.review-needed.com', 'TXT')
                ->andReturn([
                    new TXT(['host' => '_dmarc.review-needed.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=DMARC1; p=none;']),
                ]);

            $mock->shouldReceive('getRecords')
                ->with('review-needed.com', 'CAA')
                ->andReturn([]);

            $mock->shouldReceive('getRecords')
                ->withArgs(function ($host, $type) {
                    return str_ends_with($host, '._domainkey.review-needed.com') && $type === 'TXT';
                })
                ->andReturn([]);
        });

        $mockService = \Mockery::mock(\App\Services\EmailSecurityHealthCheck::class, [app(Dns::class)])->makePartial();
        $mockService->shouldReceive('getDnsKey')
            ->with('review-needed.com')
            ->andReturn([]);

        $this->instance(\App\Services\EmailSecurityHealthCheck::class, $mockService);

        $this->artisan('domains:health-check', ['--type' => 'email_security', '--domain' => 'review-needed.com'])
            ->assertSuccessful();

        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $check = $domain->checks()->latest()->first();
        $payload = $check->payload;

        $this->assertEquals('warn', $payload['spf']['status']);
        $this->assertEquals('soft_fail', $payload['spf']['mechanism']);
        $this->assertEquals('warn', $payload['dmarc']['status']);
        $this->assertEquals('none', $payload['dmarc']['policy']);
        $this->assertEquals('warn', $payload['overall_status']);
        $this->assertFalse($payload['is_valid']);
        $this->assertStringContainsString('monitor-only', $payload['overall_assessment']);
    }

    public function test_it_marks_email_security_as_unknown_when_verification_fails(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'unknown-email.com',
            'is_active' => true,
        ]);

        $this->mock(Dns::class, function (MockInterface $mock) {
            $mock->shouldReceive('getRecords')
                ->andThrow(new \RuntimeException('DNS lookup failed'));
        });

        $mockService = \Mockery::mock(\App\Services\EmailSecurityHealthCheck::class, [app(Dns::class)])->makePartial();
        $mockService->shouldReceive('getDnsKey')
            ->with('unknown-email.com')
            ->andReturn([]);

        $this->instance(\App\Services\EmailSecurityHealthCheck::class, $mockService);

        $this->artisan('domains:health-check', ['--type' => 'email_security', '--domain' => 'unknown-email.com'])
            ->assertSuccessful();

        $check = $domain->checks()->latest()->first();
        $this->assertEquals('unknown', $check->status);

        $payload = $check->payload;
        $this->assertEquals('unknown', $payload['overall_status']);
        $this->assertFalse($payload['spf']['verified']);
        $this->assertFalse($payload['dmarc']['verified']);
    }
}
