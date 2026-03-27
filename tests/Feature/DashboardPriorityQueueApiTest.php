<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPriorityQueueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_queue_endpoint_returns_actionable_domains_only(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $mustFixDomain = Domain::factory()->create([
            'domain' => 'must-fix.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $shouldFixDomain = Domain::factory()->create([
            'domain' => 'should-fix.example.com',
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked.example.com',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        $emailOnlyDomain = Domain::factory()->create([
            'domain' => 'mail-only.example.com',
            'is_active' => true,
            'platform' => 'Email Only',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-priority-queue')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('stats.must_fix', 1)
            ->assertJsonPath('stats.should_fix', 2)
            ->assertJsonPath('must_fix.0.domain', 'must-fix.example.com')
            ->assertJsonPath('must_fix.0.hosting_provider', 'DreamIT Host');

        $mustFixDomains = collect($response->json('must_fix'));
        $shouldFixDomains = collect($response->json('should_fix'));

        $this->assertFalse($mustFixDomains->contains(fn (array $item): bool => $item['domain'] === 'parked.example.com'));
        $this->assertFalse($mustFixDomains->contains(fn (array $item): bool => $item['domain'] === 'mail-only.example.com'));
        $this->assertTrue($shouldFixDomains->contains(fn (array $item): bool => $item['domain'] === 'mail-only.example.com'));
        $this->assertTrue($shouldFixDomains->contains(fn (array $item): bool => $item['domain'] === 'should-fix.example.com'));

        $emailOnly = $shouldFixDomains->firstWhere('domain', 'mail-only.example.com');

        $this->assertIsArray($emailOnly);
        $this->assertTrue($emailOnly['is_email_only']);
        $this->assertSame(['Email security needs review'], $emailOnly['primary_reasons']);
    }
}
