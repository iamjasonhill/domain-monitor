<?php

namespace Tests\Feature;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\DomainDnsRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DomainDnsRecordServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_record_rejects_non_au_domains(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnFalse();

        $result = app(DomainDnsRecordService::class)->saveRecord($domain, [
            'host' => '@',
            'type' => 'A',
            'value' => '1.2.3.4',
            'ttl' => 300,
            'priority' => 0,
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('dnsRecordHost', $result['error_field']);
    }

    public function test_save_record_creates_dns_record_when_synergy_add_succeeds(): void
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

        $synergyClient = Mockery::mock();
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('addDnsRecord')
            ->once()
            ->andReturn([
                'status' => 'OK',
                'record_id' => 'RID-NEW',
            ]);

        $result = app(DomainDnsRecordService::class)->saveRecord($domain, [
            'host' => '@',
            'type' => 'txt',
            'value' => 'v=spf1 -all',
            'ttl' => 300,
            'priority' => 0,
        ]);

        $this->assertTrue($result['ok']);

        $this->assertDatabaseHas('dns_records', [
            'domain_id' => $domain->id,
            'host' => '@',
            'type' => 'TXT',
            'value' => 'v=spf1 -all',
            'record_id' => 'RID-NEW',
        ]);
    }

    public function test_save_record_updates_existing_record_when_synergy_update_succeeds(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $record = DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'record_id' => 'RID-123',
            'host' => '@',
            'type' => 'A',
            'value' => '1.1.1.1',
            'ttl' => 300,
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock();
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('updateDnsRecord')
            ->once()
            ->with($domain->domain, $record->record_id, 'www', 'A', '2.2.2.2', 600, 0)
            ->andReturn([
                'status' => 'OK',
            ]);

        $result = app(DomainDnsRecordService::class)->saveRecord($domain, [
            'host' => 'www',
            'type' => 'A',
            'value' => '2.2.2.2',
            'ttl' => 600,
            'priority' => 0,
        ], $record->id);

        $this->assertTrue($result['ok']);

        $this->assertDatabaseHas('dns_records', [
            'id' => $record->id,
            'host' => 'www',
            'value' => '2.2.2.2',
            'ttl' => 600,
        ]);
    }

    public function test_delete_record_deletes_local_row_when_synergy_delete_succeeds(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $record = DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'record_id' => 'RID-DELETE',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock();
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        $synergyClient->shouldReceive('deleteDnsRecord')
            ->once()
            ->with($domain->domain, $record->record_id)
            ->andReturn([
                'status' => 'OK',
            ]);

        $result = app(DomainDnsRecordService::class)->deleteRecord($domain, $record->id);

        $this->assertTrue($result['ok']);
        $this->assertDatabaseMissing('dns_records', ['id' => $record->id]);
    }
}
