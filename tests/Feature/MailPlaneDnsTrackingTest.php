<?php

namespace Tests\Feature;

use App\Models\DnsRecord;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MailPlaneDnsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_api_exposes_resend_mail_plane_dns_readiness(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        Carbon::setTestNow('2026-05-08 03:00:00');

        $domain = Domain::factory()->create([
            'domain' => 'notify.again.com.au',
            'platform' => 'Email Only',
            'email_usage' => Domain::EMAIL_USAGE_SEND,
            'mail_plane_type' => Domain::MAIL_PLANE_AGENT_NOTIFICATIONS,
            'mail_provider' => 'resend',
            'mail_dns_requirements' => [
                [
                    'purpose' => 'spf',
                    'host' => '@',
                    'type' => 'TXT',
                    'value' => 'v=spf1 include:amazonses.com ~all',
                    'required' => true,
                    'description' => 'Allow Resend to send agent notification mail.',
                ],
                [
                    'purpose' => 'dkim',
                    'host' => 'resend._domainkey',
                    'type' => 'CNAME',
                    'value' => 'resend._domainkey.resend.com',
                    'required' => true,
                ],
                [
                    'purpose' => 'dmarc',
                    'host' => '_dmarc',
                    'type' => 'TXT',
                    'value' => 'v=DMARC1; p=none;',
                    'required' => false,
                ],
            ],
            'mail_provider_verification' => [
                'status' => 'pending',
                'checked_at' => '2026-05-08T02:55:00+00:00',
                'external_id' => 'resend-domain-123',
                'notes' => 'Waiting on DKIM.',
            ],
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'notify.again.com.au',
            'type' => 'TXT',
            'value' => 'v=spf1 include:amazonses.com ~all',
            'synced_at' => now(),
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => 'resend._domainkey.notify.again.com.au',
            'type' => 'CNAME',
            'value' => 'old.resend.example',
            'synced_at' => now(),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
            'finished_at' => now()->subMinutes(5),
        ]);

        $response = $this
            ->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/domains/notify.again.com.au');

        $response
            ->assertOk()
            ->assertJsonPath('data.domain', 'notify.again.com.au')
            ->assertJsonPath('data.is_email_only', true)
            ->assertJsonPath('data.email_sending_expected', true)
            ->assertJsonPath('data.mail_plane.enabled', true)
            ->assertJsonPath('data.mail_plane.plane_type', Domain::MAIL_PLANE_AGENT_NOTIFICATIONS)
            ->assertJsonPath('data.mail_plane.provider', 'resend')
            ->assertJsonPath('data.mail_plane.status', 'fail')
            ->assertJsonPath('data.mail_plane.counts.total', 3)
            ->assertJsonPath('data.mail_plane.counts.verified', 1)
            ->assertJsonPath('data.mail_plane.counts.drifted', 1)
            ->assertJsonPath('data.mail_plane.counts.optional_missing', 1)
            ->assertJsonPath('data.mail_plane.records.0.status', 'ok')
            ->assertJsonPath('data.mail_plane.records.1.status', 'drifted')
            ->assertJsonPath('data.mail_plane.records.1.fqdn', 'resend._domainkey.notify.again.com.au')
            ->assertJsonPath('data.mail_plane.records.2.status', 'missing')
            ->assertJsonPath('data.mail_plane.provider_verification.status', 'pending');
    }

    public function test_web_property_summary_keeps_mail_plane_dns_separate_from_website_health(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'notify.again.com.au',
            'platform' => 'Email Only',
            'email_usage' => Domain::EMAIL_USAGE_SEND,
            'mail_plane_type' => Domain::MAIL_PLANE_AGENT_NOTIFICATIONS,
            'mail_provider' => 'resend',
            'mail_dns_requirements' => [
                [
                    'purpose' => 'provider_verification',
                    'host' => '@',
                    'type' => 'TXT',
                    'value' => 'resend-verification=abc123',
                    'required' => true,
                ],
            ],
        ]);

        DnsRecord::factory()->create([
            'domain_id' => $domain->id,
            'host' => '@',
            'type' => 'TXT',
            'value' => 'resend-verification=abc123',
            'synced_at' => now(),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'status' => 'fail',
            'finished_at' => now(),
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'agent-notifications-mail-plane',
            'name' => 'Agent Notifications Mail Plane',
            'property_type' => 'domain_asset',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => null,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'mail_plane',
            'is_canonical' => true,
        ]);

        $response = $this
            ->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.domains.0.mail_plane.enabled', true)
            ->assertJsonPath('web_properties.0.domains.0.mail_plane.status', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.http', 'not_applicable')
            ->assertJsonPath('web_properties.0.freshness.status', 'not_applicable');
    }
}
