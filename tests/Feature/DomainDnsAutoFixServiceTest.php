<?php

namespace Tests\Feature;

use App\Contracts\SynergyDnsFixClient;
use App\Models\Domain;
use App\Models\SynergyCredential;
use App\Services\DomainDnsAutoFixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DomainDnsAutoFixServiceTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL_FIX_DISABLED_MESSAGE = 'Automatic DNS fixes are disabled for email security because generic SPF, DMARC, and CAA records may be incorrect for the real mail and certificate setup.';

    public function test_apply_fix_skips_spf_autofix(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'spf');

        $this->assertFalse($result['ok']);
        $this->assertSame(self::EMAIL_FIX_DISABLED_MESSAGE, $result['message']);
    }

    public function test_apply_fix_skips_dmarc_autofix(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'dmarc');

        $this->assertFalse($result['ok']);
        $this->assertSame(self::EMAIL_FIX_DISABLED_MESSAGE, $result['message']);
    }

    public function test_apply_fix_skips_caa_autofix(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'caa');

        $this->assertFalse($result['ok']);
        $this->assertSame(self::EMAIL_FIX_DISABLED_MESSAGE, $result['message']);
    }

    public function test_apply_fix_rejects_non_au_domain_for_non_email_fix(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com',
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnFalse();
        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'unknown');

        $this->assertFalse($result['ok']);
        $this->assertSame('Automated fixes are only available for Australian TLD domains.', $result['message']);
    }

    public function test_apply_fix_requires_active_credentials_for_non_email_fix(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
        ]);

        $synergyAlias = Mockery::mock('alias:App\Services\SynergyWholesaleClient');
        $synergyAlias->shouldReceive('isAustralianTld')
            ->with($domain->domain)
            ->andReturnTrue();
        $synergyAlias->shouldNotReceive('fromEncryptedCredentials');

        $result = app(DomainDnsAutoFixService::class)->applyFix($domain, 'unknown');

        $this->assertFalse($result['ok']);
        $this->assertSame('No active Synergy credentials found.', $result['message']);
    }

    public function test_apply_fix_returns_unknown_type_error_after_loading_live_records(): void
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
}
