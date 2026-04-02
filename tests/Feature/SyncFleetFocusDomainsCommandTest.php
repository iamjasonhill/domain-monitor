<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncFleetFocusDomainsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_configured_domains_onto_the_fleet_focus_tag(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        config()->set('domain_monitor.fleet_focus.domains', [
            'fleet-a.example.com',
            'fleet-b.example.com',
            'missing.example.com',
        ]);

        $fleetA = Domain::factory()->create([
            'domain' => 'fleet-a.example.com',
            'is_active' => true,
        ]);

        $fleetB = Domain::factory()->create([
            'domain' => 'fleet-b.example.com',
            'is_active' => true,
        ]);

        $this->assertSame(0, Artisan::call('fleet:sync-focus-domains'));

        $tag = DomainTag::query()->where('name', 'fleet.live')->firstOrFail();

        $this->assertTrue($fleetA->fresh()->tags->contains('id', $tag->id));
        $this->assertTrue($fleetB->fresh()->tags->contains('id', $tag->id));
        $this->assertStringContainsString('Configured domains not found: missing.example.com', Artisan::output());
    }

    public function test_dry_run_reports_changes_without_attaching_tags(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        config()->set('domain_monitor.fleet_focus.domains', [
            'fleet-dry-run.example.com',
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'fleet-dry-run.example.com',
            'is_active' => true,
        ]);

        $this->assertSame(0, Artisan::call('fleet:sync-focus-domains', ['--dry-run' => true]));
        $this->assertStringContainsString('Would attach [1] domains to [fleet.live].', Artisan::output());
        $this->assertCount(0, $domain->fresh()->tags);
    }
}
