<?php

namespace Tests\Feature;

use App\Livewire\DomainDetail;
use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\Subdomain;
use App\Models\SynergyCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class DomainDetailActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_add_dns_record_modal_resets_the_form_state(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->set('editingDnsRecordId', 'existing-record')
            ->set('dnsRecordHost', 'mail')
            ->set('dnsRecordType', 'MX')
            ->set('dnsRecordValue', 'mail.example.com.au')
            ->set('dnsRecordTtl', 3600)
            ->set('dnsRecordPriority', 10)
            ->call('openAddDnsRecordModal')
            ->assertSet('showDnsRecordModal', true)
            ->assertSet('editingDnsRecordId', null)
            ->assertSet('dnsRecordHost', '')
            ->assertSet('dnsRecordType', 'A')
            ->assertSet('dnsRecordValue', '')
            ->assertSet('dnsRecordTtl', 300)
            ->assertSet('dnsRecordPriority', 0);
    }

    public function test_saving_dns_record_with_blank_host_normalizes_to_root_domain(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        /** @phpstan-ignore-next-line */
        $synergyClient->shouldReceive('addDnsRecord')
            ->once()
            ->with($domain->domain, '@', 'A', '203.0.113.10', 300, 0)
            ->andReturn([
                'status' => 'OK',
                'record_id' => 'RID-NEW-123',
                'error_message' => null,
            ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('openAddDnsRecordModal')
            ->set('dnsRecordHost', '')
            ->set('dnsRecordType', 'A')
            ->set('dnsRecordValue', '203.0.113.10')
            ->set('dnsRecordTtl', 300)
            ->set('dnsRecordPriority', 0)
            ->call('saveDnsRecord')
            ->assertHasNoErrors()
            ->assertSet('showDnsRecordModal', false);

        $this->assertDatabaseHas('dns_records', [
            'domain_id' => $domain->id,
            'host' => '@',
            'type' => 'A',
            'value' => '203.0.113.10',
            'ttl' => 300,
            'priority' => 0,
            'record_id' => 'RID-NEW-123',
        ]);
    }

    public function test_saving_caa_record_persists_when_value_is_valid(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $credential = SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();

        $synergyClient = Mockery::mock();
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldReceive('fromEncryptedCredentials')
            ->with($credential->reseller_id, $credential->api_key_encrypted, $credential->api_url)
            ->andReturn($synergyClient);

        /** @phpstan-ignore-next-line */
        $synergyClient->shouldReceive('addDnsRecord')
            ->once()
            ->with($domain->domain, '@', 'CAA', '0 issue "letsencrypt.org"', 300, 0)
            ->andReturn([
                'status' => 'OK',
                'record_id' => 'RID-CAA-123',
                'error_message' => null,
            ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('openAddDnsRecordModal')
            ->set('dnsRecordHost', '@')
            ->set('dnsRecordType', 'CAA')
            ->set('dnsRecordValue', '0 issue "letsencrypt.org"')
            ->set('dnsRecordTtl', 300)
            ->set('dnsRecordPriority', 0)
            ->call('saveDnsRecord')
            ->assertHasNoErrors()
            ->assertSet('showDnsRecordModal', false);

        $this->assertDatabaseHas('dns_records', [
            'domain_id' => $domain->id,
            'host' => '@',
            'type' => 'CAA',
            'value' => '0 issue "letsencrypt.org"',
            'record_id' => 'RID-CAA-123',
        ]);
    }

    public function test_saving_caa_record_rejects_malformed_quoted_value(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        SynergyCredential::factory()->create([
            'is_active' => true,
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();
        /** @phpstan-ignore-next-line */
        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('openAddDnsRecordModal')
            ->set('dnsRecordHost', '@')
            ->set('dnsRecordType', 'CAA')
            ->set('dnsRecordValue', '0 issue "\"letsencrypt.org\""')
            ->set('dnsRecordTtl', 300)
            ->set('dnsRecordPriority', 0)
            ->call('saveDnsRecord')
            ->assertHasErrors(['dnsRecordValue']);
    }

    public function test_saving_subdomain_persists_and_closes_the_modal(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('openAddSubdomainModal')
            ->set('subdomainName', 'api')
            ->set('subdomainNotes', 'Primary API')
            ->call('saveSubdomain')
            ->assertSet('showSubdomainModal', false);

        $this->assertDatabaseHas('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'notes' => 'Primary API',
            'is_active' => 1,
        ]);
    }

    public function test_discover_subdomains_from_dns_only_adds_new_valid_hosts(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.invalid',
        ]);

        Subdomain::create([
            'domain_id' => $domain->id,
            'subdomain' => 'api',
            'full_domain' => 'api.example.com',
            'is_active' => true,
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'api',
            'type' => 'A',
            'value' => '203.0.113.11',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'blog',
            'type' => 'CNAME',
            'value' => 'sites.example.net',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'cdn',
            'type' => 'AAAA',
            'value' => '2001:db8::10',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => '@',
            'type' => 'A',
            'value' => '203.0.113.12',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'www',
            'type' => 'A',
            'value' => '203.0.113.13',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => '*.preview',
            'type' => 'CNAME',
            'value' => 'preview.example.net',
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'mail',
            'type' => 'MX',
            'value' => 'mail.example.com',
            'priority' => 10,
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'docs',
            'type' => 'TXT',
            'value' => 'verification-token',
        ]);

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('discoverSubdomainsFromDns');

        $this->assertDatabaseHas('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'blog',
            'full_domain' => 'blog.example.invalid',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'cdn',
            'full_domain' => 'cdn.example.invalid',
            'is_active' => 1,
        ]);

        $this->assertDatabaseHas('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'www',
        ]);

        $this->assertDatabaseMissing('subdomains', [
            'domain_id' => $domain->id,
            'subdomain' => 'docs',
        ]);

        $blog = Subdomain::where('domain_id', $domain->id)
            ->where('subdomain', 'blog')
            ->firstOrFail();

        $this->assertNull($blog->ip_address);
        $this->assertNotNull($blog->ip_checked_at);

        $this->assertSame(4, Subdomain::where('domain_id', $domain->id)->count());
    }

    public function test_syncing_seo_baseline_runs_the_manual_sync_command(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'removalsinterstate.com.au',
        ]);

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', ['--domain' => 'removalsinterstate.com.au'])
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Synced Search Console baseline for removalsinterstate.com.au (2025-12-29 to 2026-03-28).');

        Livewire::test(DomainDetail::class, ['domainId' => $domain->id])
            ->call('syncSeoBaseline')
            ->assertHasNoErrors();
    }
}
