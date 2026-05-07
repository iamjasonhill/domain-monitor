<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DomainEmailUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_subdomains_default_to_no_email_usage(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'quoting.backloading-au.com.au',
            'email_usage' => null,
        ]);

        $this->assertSame(Domain::EMAIL_USAGE_NONE, $domain->emailUsage());
        $this->assertFalse($domain->emailExpected());
        $this->assertFalse($domain->emailSendingExpected());
        $this->assertFalse($domain->emailReceivingExpected());
    }

    public function test_car_transport_subdomains_default_to_no_email_usage(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'email_usage' => null,
        ]);

        $this->assertSame(Domain::EMAIL_USAGE_NONE, $domain->emailUsage());
        $this->assertFalse($domain->emailExpected());
        $this->assertFalse($domain->emailSendingExpected());
        $this->assertFalse($domain->emailReceivingExpected());
    }

    public function test_primary_domains_default_to_send_and_receive_email_usage(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'backloading-au.com.au',
            'email_usage' => null,
        ]);

        $this->assertSame(Domain::EMAIL_USAGE_SEND_RECEIVE, $domain->emailUsage());
        $this->assertTrue($domain->emailExpected());
        $this->assertTrue($domain->emailSendingExpected());
        $this->assertTrue($domain->emailReceivingExpected());
    }

    public function test_explicit_email_usage_overrides_operational_subdomain_default(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'portal.example.com',
            'email_usage' => Domain::EMAIL_USAGE_SEND,
        ]);

        $this->assertSame(Domain::EMAIL_USAGE_SEND, $domain->emailUsage());
        $this->assertTrue($domain->emailExpected());
        $this->assertTrue($domain->emailSendingExpected());
        $this->assertFalse($domain->emailReceivingExpected());
    }

    public function test_email_security_check_is_skipped_for_web_only_subdomains(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'quoting.backloading-au.com.au',
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('domains:health-check', [
            '--type' => 'email_security',
            '--domain' => 'quoting.backloading-au.com.au',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
        ]);
    }

    public function test_health_summary_ignores_stale_email_security_failures_for_web_only_subdomains(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'email_usage' => null,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'cartransport-movingagain-com-au',
            'name' => 'Moving Again Car Transport',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://cartransport.movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'status' => 'ok',
        ]);

        $property = WebProperty::query()
            ->with([
                'propertyDomains.domain' => fn ($query) => $query->withLatestCheckStatuses()->with('alerts'),
            ])
            ->whereKey($property->id)
            ->firstOrFail();

        $summary = $property->healthSummary();

        $this->assertSame('ok', $summary['overall_status']);
        $this->assertSame('not_applicable', $summary['checks']['email_security']);
        $this->assertSame('not_applicable', $summary['per_domain'][0]['checks']['email_security']);
    }
}
