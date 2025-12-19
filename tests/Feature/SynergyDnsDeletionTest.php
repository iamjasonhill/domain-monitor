<?php

namespace Tests\Feature;

use App\Livewire\DomainDetail;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\SynergyCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class SynergyDnsDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_dns_record_deletes_it_in_synergy_and_locally(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $record = DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'record_id' => 'RID-123',
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
                'error_message' => null,
            ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('deleteDnsRecord', $record->id);

        $this->assertDatabaseMissing('dns_records', ['id' => $record->id]);
    }

    public function test_deleting_a_dns_record_without_a_synergy_record_id_does_not_call_synergy(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $record = DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'record_id' => null,
        ]);

        SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('deleteDnsRecord', $record->id);

        $this->assertDatabaseHas('dns_records', ['id' => $record->id]);
    }
}
