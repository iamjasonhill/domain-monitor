<?php

namespace Tests\Feature;

use App\Models\Subdomain;
use Tests\TestCase;

class SubdomainClassificationTest extends TestCase
{
    public function test_domainkey_subdomains_are_classified_as_email_auth(): void
    {
        $subdomain = new Subdomain([
            'subdomain' => 's1._domainkey',
            'full_domain' => 's1._domainkey.example.com',
        ]);

        $this->assertSame('email_auth', $subdomain->category());
        $this->assertSame('Email/Auth', $subdomain->categoryLabel());
        $this->assertFalse($subdomain->expectsIpResolution());
    }

    public function test_em_prefixed_subdomains_are_classified_as_email_auth(): void
    {
        $subdomain = new Subdomain([
            'subdomain' => 'em4280',
            'full_domain' => 'em4280.example.com',
        ]);

        $this->assertSame('email_auth', $subdomain->category());
        $this->assertFalse($subdomain->expectsIpResolution());
    }

    public function test_regular_hosts_are_classified_as_web(): void
    {
        $subdomain = new Subdomain([
            'subdomain' => 'quotes',
            'full_domain' => 'quotes.example.com',
        ]);

        $this->assertSame('web', $subdomain->category());
        $this->assertSame('Web', $subdomain->categoryLabel());
        $this->assertTrue($subdomain->expectsIpResolution());
    }

    public function test_unresolved_email_auth_hosts_show_no_ip_expected(): void
    {
        $subdomain = new Subdomain([
            'subdomain' => 's2._domainkey',
            'full_domain' => 's2._domainkey.example.com',
            'ip_checked_at' => now(),
            'ip_address' => null,
        ]);

        $this->assertSame('not_applicable', $subdomain->resolutionState());
        $this->assertSame('No IP Expected', $subdomain->resolutionLabel());
    }

    public function test_unresolved_web_hosts_show_does_not_resolve(): void
    {
        $subdomain = new Subdomain([
            'subdomain' => 'quotes',
            'full_domain' => 'quotes.example.com',
            'ip_checked_at' => now(),
            'ip_address' => null,
        ]);

        $this->assertSame('unresolved', $subdomain->resolutionState());
        $this->assertSame('Does Not Resolve', $subdomain->resolutionLabel());
    }
}
