<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Services\ReputationHealthCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class ReputationHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.google.safe_browsing_key', 'test-api-key');
    }

    public function test_it_reports_clean_reputation(): void
    {
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'clean-reputation.com',
            'is_active' => true,
        ]);

        // Mock Google Safe Browsing API
        Http::fake([
            'safebrowsing.googleapis.com/*' => Http::response(['matches' => []], 200),
        ]);

        // Mock DNSBL lookups
        $this->mock(ReputationHealthCheck::class, function (MockInterface $mock) {
            $mock->makePartial()->shouldAllowMockingProtectedMethods();

            // Mock resolveIp
            $mock->shouldReceive('resolveIp')
                ->with('clean-reputation.com')
                ->andReturn('1.2.3.4');

            // Mock resolveDns (DNSBL lookup) - return false (not found/clean)
            $mock->shouldReceive('resolveDns')
                ->with('4.3.2.1.zen.spamhaus.org')
                ->andReturn(false);
        });

        // Act
        $this->artisan('domains:health-check', ['--type' => 'reputation', '--domain' => 'clean-reputation.com'])
            ->assertSuccessful();

        // Assert
        $check = $domain->checks()->latest()->first();
        $this->assertEquals('reputation', $check->check_type);
        $this->assertEquals('ok', $check->status);

        $payload = $check->payload;
        $this->assertTrue($payload['google_safe_browsing']['safe']);
        $this->assertFalse($payload['dnsbl']['spamhaus']['listed']);
    }

    public function test_it_reports_google_safe_browsing_threats(): void
    {
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'malware-site.com',
            'is_active' => true,
        ]);

        // Mock Google Safe Browsing API with threat
        Http::fake([
            'safebrowsing.googleapis.com/*' => Http::response([
                'matches' => [
                    ['threatType' => 'MALWARE', 'platformType' => 'ANY_PLATFORM', 'threatEntryType' => 'URL'],
                ],
            ], 200),
        ]);

        // Mock DNSBL lookups (clean IP)
        $this->mock(ReputationHealthCheck::class, function (MockInterface $mock) {
            $mock->makePartial()->shouldAllowMockingProtectedMethods();
            $mock->shouldReceive('resolveIp')->andReturn('1.2.3.4');
            $mock->shouldReceive('resolveDns')->andReturn(false);
        });

        // Act
        $this->artisan('domains:health-check', ['--type' => 'reputation', '--domain' => 'malware-site.com'])
            ->assertSuccessful();

        // Assert
        $check = $domain->checks()->latest()->first();
        $this->assertEquals('fail', $check->status);

        $payload = $check->payload;
        $this->assertFalse($payload['google_safe_browsing']['safe']);
        $this->assertEquals('MALWARE', $payload['google_safe_browsing']['matches'][0]['threatType']);
        $this->assertFalse($payload['dnsbl']['spamhaus']['listed']);
    }

    public function test_it_reports_spamhaus_listing(): void
    {
        // Arrange
        $domain = Domain::factory()->create([
            'domain' => 'spam-source.com',
            'is_active' => true,
        ]);

        // Mock Google Safe Browsing (Safe)
        Http::fake([
            'safebrowsing.googleapis.com/*' => Http::response(['matches' => []], 200),
        ]);

        // Mock DNSBL lookups (listed IP)
        $this->mock(ReputationHealthCheck::class, function (MockInterface $mock) {
            $mock->makePartial()->shouldAllowMockingProtectedMethods();

            $mock->shouldReceive('resolveIp')
                ->with('spam-source.com')
                ->andReturn('127.0.0.2'); // SBL test IP

            // Mock resolveDns (DNSBL lookup) - return array (found)
            // 2.0.0.127.zen.spamhaus.org
            $mock->shouldReceive('resolveDns')
                ->with('2.0.0.127.zen.spamhaus.org')
                ->andReturn(['127.0.0.2']);
        });

        // Act
        $this->artisan('domains:health-check', ['--type' => 'reputation', '--domain' => 'spam-source.com'])
            ->assertSuccessful();

        // Assert
        $check = $domain->checks()->latest()->first();
        $this->assertEquals('fail', $check->status);

        $payload = $check->payload;
        $this->assertTrue($payload['google_safe_browsing']['safe']);
        $this->assertTrue($payload['dnsbl']['spamhaus']['listed']);
        $this->assertStringContainsString('Spam Source', $payload['dnsbl']['spamhaus']['details']);
    }
}
