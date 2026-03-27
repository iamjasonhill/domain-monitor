<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_must_fix_and_should_fix_domain_queues(): void
    {
        $user = User::factory()->create();

        $mustFixDomain = Domain::factory()->create([
            'domain' => 'must-fix.example.com',
            'is_active' => true,
            'eligibility_valid' => false,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        DomainAlert::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'severity' => 'critical',
            'resolved_at' => null,
            'alert_type' => 'ssl_expired',
        ]);

        $shouldFixDomain = Domain::factory()->create([
            'domain' => 'should-fix.example.com',
            'is_active' => true,
            'expires_at' => now()->addDays(14),
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Must Fix')
            ->assertSee('Should Fix')
            ->assertSee('must-fix.example.com')
            ->assertSee('HTTP check is failing')
            ->assertSee('Eligibility or compliance has failed')
            ->assertSee('should-fix.example.com')
            ->assertSee('Security headers need review')
            ->assertSee('Email security needs review')
            ->assertSee('Domain expires in 14 days');
    }

    public function test_dashboard_excludes_domains_marked_as_parked_in_synergy(): void
    {
        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked-domain.example.com',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'ssl',
            'status' => 'fail',
        ]);

        Livewire::test(Dashboard::class)
            ->assertViewHas('mustFixDomains', function (Collection $items): bool {
                return $items->every(fn (array $item): bool => $item['domain'] !== 'parked-domain.example.com');
            })
            ->assertViewHas('shouldFixDomains', function (Collection $items): bool {
                return $items->every(fn (array $item): bool => $item['domain'] !== 'parked-domain.example.com');
            });
    }
}
