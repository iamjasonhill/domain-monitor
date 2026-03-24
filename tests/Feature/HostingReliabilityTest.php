<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\UptimeIncident;
use App\Models\User;
use App\Services\HostingDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HostingReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_hosting_reliability_page_requires_authentication(): void
    {
        $this->get(route('hosting.index'))
            ->assertRedirect(route('login'));
    }

    public function test_it_aggregates_stats_by_hosting_provider(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        \Illuminate\Support\Carbon::setTestNow(now());

        $hostA = 'Host A';
        $hostB = 'Host B';
        // ... rest of setup ...
        $domain1 = Domain::factory()->create(['domain' => 'a1.com', 'hosting_provider' => $hostA]);
        $domain2 = Domain::factory()->create(['domain' => 'a2.com', 'hosting_provider' => $hostA]);
        $domain3 = Domain::factory()->create(['domain' => 'b1.com', 'hosting_provider' => $hostB]);

        // Host A incidents
        UptimeIncident::create([
            'domain_id' => $domain1->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1), // 60 mins
        ]);
        UptimeIncident::create([
            'domain_id' => $domain2->id,
            'started_at' => now()->subMinutes(30),
            'ended_at' => now()->subMinutes(24), // 6 mins
        ]);

        // Host B incident
        UptimeIncident::create([
            'domain_id' => $domain3->id,
            'started_at' => now()->subHours(1),
            'ended_at' => now()->subMinutes(30), // 30 mins
        ]);

        // Total Host A = 66 mins = 1.1 hrs
        // Total Host B = 30 mins = 30 mins

        Livewire::test(\App\Livewire\HostingReliability::class)
            ->assertSet('selectedHost', null)
            ->assertSee($hostA)
            ->assertSee($hostB)
            ->assertSee('1.1')
            ->assertSee('30 mins')
            ->assertSee('Pending Review')
            ->assertSee('3');
    }

    public function test_it_can_select_a_host_for_details(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $hostA = 'Host A';
        $domain = Domain::factory()->create(['domain' => 'a1.com', 'hosting_provider' => $hostA]);

        UptimeIncident::create([
            'domain_id' => $domain->id,
            'started_at' => now()->subHours(2),
            'ended_at' => now()->subHours(1),
            'error_message' => 'Test Error',
        ]);

        Livewire::test(\App\Livewire\HostingReliability::class)
            ->call('selectHost', $hostA)
            ->assertSet('selectedHost', $hostA)
            ->assertSee($domain->domain)
            ->assertSee('Test Error');
    }

    public function test_it_can_detect_and_confirm_hosting_from_review_queue(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $domain = Domain::factory()->create([
            'domain' => 'review-me.com',
            'hosting_provider' => null,
            'hosting_review_status' => null,
        ]);

        $detector = $this->mock(HostingDetector::class);
        $detector->shouldReceive('detect')
            ->once()
            ->with('review-me.com')
            ->andReturn([
                'provider' => 'Vercel',
                'confidence' => 'high',
                'admin_url' => 'https://vercel.com/dashboard',
            ]);

        $component = Livewire::test(\App\Livewire\HostingReliability::class)
            ->assertSee($domain->domain)
            ->call('detectHostingForDomain', $domain->id);

        $component
            ->assertSee('Vercel')
            ->assertSee('PENDING');

        $component
            ->call('confirmHostingForDomain', $domain->id)
            ->assertSee("Hosting confirmed for {$domain->domain}.")
            ->assertSee('No domains currently need hosting review.');

        $this->assertDatabaseHas('domains', [
            'id' => $domain->id,
            'hosting_provider' => 'Vercel',
            'hosting_admin_url' => 'https://vercel.com/dashboard',
            'hosting_detection_confidence' => 'high',
            'hosting_detection_source' => 'detector',
            'hosting_review_status' => 'confirmed',
        ]);
    }

    public function test_it_separates_parked_domains_from_live_hosting_providers(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Domain::factory()->create([
            'domain' => 'parked-example.com.au',
            'hosting_provider' => 'Synergy Wholesale PTY',
            'dns_config_name' => 'Parked',
        ]);

        Domain::factory()->create([
            'domain' => 'live-example.com.au',
            'hosting_provider' => 'Netlify',
            'platform' => 'Astro',
        ]);

        Livewire::test(\App\Livewire\HostingReliability::class)
            ->assertSee('Parking / Non-live Providers')
            ->assertSee('Synergy Wholesale PTY')
            ->assertSee('parked-example.com.au')
            ->assertSee('Live Hosting Providers')
            ->assertSee('Netlify')
            ->assertSee('Parked Domains')
            ->assertSee('1');
    }
}
