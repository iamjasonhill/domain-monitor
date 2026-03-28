<?php

namespace Tests\Feature;

use App\Livewire\AlertsList;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlertsListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_auto_renew_state_for_domain_expiring_alerts(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $domain = Domain::factory()->create([
            'domain' => 'bestinterstateremovals.com.au',
            'is_active' => true,
        ]);

        DomainAlert::create([
            'domain_id' => $domain->id,
            'alert_type' => 'domain_expiring',
            'severity' => 'warning',
            'triggered_at' => now(),
            'payload' => [
                'days_until_expiry' => 14,
                'expires_at' => now()->addDays(14)->toIso8601String(),
                'auto_renew' => false,
            ],
        ]);

        Livewire::test(AlertsList::class)
            ->assertSee('bestinterstateremovals.com.au')
            ->assertSee('Auto-renew:')
            ->assertSee('Disabled')
            ->assertSee('Not set to auto-renew.');
    }
}
