<?php

namespace Tests\Feature;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $command = $this->artisan('domains:health-check', [
            '--type' => 'email_security',
            '--domain' => 'quoting.backloading-au.com.au',
        ]);

        if (is_int($command)) {
            $this->assertSame(0, $command);
        } else {
            $command->assertSuccessful();
        }

        $this->assertDatabaseMissing('domain_checks', [
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
        ]);
    }
}
