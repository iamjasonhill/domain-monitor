<?php

namespace Tests\Feature;

use App\Livewire\HealthChecksList;
use App\Models\Domain;
use App\Models\DomainCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HealthChecksListTest extends TestCase
{
    use RefreshDatabase;

    public function test_recent_failures_excludes_parked_domains_and_skipped_email_only_web_checks(): void
    {
        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked.example.com.au',
            'dns_config_name' => 'Parked',
            'is_active' => true,
        ]);

        $emailOnlyDomain = Domain::factory()->create([
            'domain' => 'mail-only.example.com.au',
            'platform' => 'Email Only',
            'is_active' => true,
        ]);

        $activeDomain = Domain::factory()->create([
            'domain' => 'active.example.com.au',
            'platform' => 'WordPress',
            'is_active' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
            'created_at' => now()->subHour(),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'ssl',
            'status' => 'fail',
            'created_at' => now()->subHour(),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'email_security',
            'status' => 'fail',
            'created_at' => now()->subHour(),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $activeDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
            'created_at' => now()->subHour(),
        ]);

        Livewire::withQueryParams(['recentFailures' => 1])
            ->test(HealthChecksList::class)
            ->assertSet('filterRecentFailures', true)
            ->assertSet('filterStatus', 'fail')
            ->assertDontSee('parked.example.com.au')
            ->assertSee('mail-only.example.com.au')
            ->assertSee('active.example.com.au')
            ->assertSee('email_security');
    }
}
