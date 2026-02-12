<?php

namespace Tests\Feature;

use App\Contracts\SynergyDnsFixClient;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\DomainDnsAutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Dns\Dns;
use Tests\TestCase;

class DomainDnsAutoFixServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_fix_rejects_non_au_domain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnFalse();
        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'spf');

        $this->assertFalse($result['ok']);
        $this->assertSame('Automated fixes are only available for Australian TLD domains.', $result['message']);
    }

    public function test_apply_fix_requires_active_credentials(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();
        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'spf');

        $this->assertFalse($result['ok']);
        $this->assertSame('No active Synergy credentials found.', $result['message']);
    }

    public function test_apply_fix_returns_unknown_type_error(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock(SynergyDnsFixClient::class);
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('getDnsRecords')
            ->once()
            ->with($domain->domain)
            ->andReturn([
                [
                    'host' => '@',
                    'type' => 'TXT',
                    'value' => 'v=spf1 include:_spf.google.com ~all',
                    'ttl' => 300,
                ],
            ]);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'unknown');

        $this->assertFalse($result['ok']);
        $this->assertSame('Unknown fix type: unknown', $result['message']);
    }

    public function test_apply_fix_spf_updates_existing_record(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock(SynergyDnsFixClient::class);
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('getDnsRecords')
            ->once()
            ->andReturn([
                [
                    'host' => '@',
                    'type' => 'TXT',
                    'value' => 'v=spf1 old',
                    'ttl' => 300,
                    'id' => 'RID-SPF',
                ],
            ]);

        $synergyClient->shouldReceive('updateDnsRecord')
            ->once()
            ->with($domain->domain, 'RID-SPF', '@', 'TXT', 'v=spf1 a mx ~all', 300)
            ->andReturn(['status' => 'OK']);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'spf');

        $this->assertTrue($result['ok']);
        $this->assertSame('Updated existing SPF record to safe default.', $result['message']);
    }

    public function test_apply_fix_dmarc_creates_record_when_missing(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock(SynergyDnsFixClient::class);
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('getDnsRecords')
            ->once()
            ->andReturn([
                [
                    'host' => '@',
                    'type' => 'TXT',
                    'value' => 'v=spf1 old',
                    'ttl' => 300,
                ],
            ]);

        $synergyClient->shouldReceive('addDnsRecord')
            ->once()
            ->with($domain->domain, '_dmarc', 'TXT', 'v=DMARC1; p=none;', 300)
            ->andReturn(['status' => 'OK']);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'dmarc');

        $this->assertTrue($result['ok']);
        $this->assertSame('Created new DMARC record.', $result['message']);
    }

    public function test_apply_fix_caa_skips_when_dns_already_has_caa_record(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock(SynergyDnsFixClient::class);
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('getDnsRecords')
            ->once()
            ->andReturn([
                [
                    'host' => '@',
                    'type' => 'A',
                    'value' => '1.2.3.4',
                    'ttl' => 300,
                ],
            ]);

        $synergyClient->shouldNotReceive('addDnsRecord');

        $dnsClient = Mockery::mock(Dns::class);
        $dnsClient->shouldReceive('getRecords')
            ->once()
            ->with($domain->domain, 'CAA')
            ->andReturn([['type' => 'CAA']]);
        $this->instance(Dns::class, $dnsClient);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'caa');

        $this->assertFalse($result['ok']);
        $this->assertSame(
            'CAA records already exist (verified via API and DNS lookup). Automatic fix skipped to avoid breaking existing authorization.',
            $result['message']
        );
    }
}
