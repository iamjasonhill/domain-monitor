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
                    new TXT(['host' => 'example.com', 'class' => 'IN', 'ttl' => 300, 'type' => 'TXT', 'txt' => 'v=spf1 include:_spf.google.com ~all']),
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
        $this->assertEquals('soft_fail', $payload['spf']['mechanism']);

        $this->assertTrue($payload['dmarc']['valid']);
        $this->assertEquals('reject', $payload['dmarc']['policy']);

        $this->assertTrue($payload['dnssec']['enabled']);
        $this->assertTrue($payload['caa']['present']);
        $this->assertCount(1, $payload['caa']['records']);
    }

    public function test_it_detects_invalid_spf_and_missing_dmarc(): void
    {
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
        $this->assertStringContainsString('Multiple SPF records', $payload['spf']['error']);

        $this->assertFalse($payload['dmarc']['present']);
        $this->assertFalse($payload['dmarc']['valid']);

        $this->assertFalse($payload['dnssec']['enabled']);
        $this->assertFalse($payload['caa']['present']);
    }
}
