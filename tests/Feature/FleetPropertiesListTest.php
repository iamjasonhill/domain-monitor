<?php

namespace Tests\Feature;

use App\Livewire\WebPropertiesList;
use App\Models\Domain;
use App\Models\DomainTag;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class FleetPropertiesListTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_view_only_shows_tagged_properties(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'fleet.example.com',
            'is_active' => true,
        ]);

        $otherDomain = Domain::factory()->create([
            'domain' => 'other.example.com',
            'is_active' => true,
        ]);

        $fleetProperty = WebProperty::factory()->create([
            'slug' => 'fleet-site',
            'name' => 'Fleet Site',
            'primary_domain_id' => $fleetDomain->id,
        ]);

        $otherProperty = WebProperty::factory()->create([
            'slug' => 'other-site',
            'name' => 'Other Site',
            'primary_domain_id' => $otherDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $fleetProperty->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $otherProperty->id,
            'domain_id' => $otherDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->assertSee('Fleet Site')
            ->assertDontSee('Other Site');
    }

    public function test_fleet_view_can_update_manual_property_priority(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        $this->actingAs(User::factory()->create());

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'priority.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'priority-site',
            'name' => 'Priority Site',
            'primary_domain_id' => $fleetDomain->id,
            'priority' => null,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->call('updatePropertyPriority', $property->id, '77');

        $this->assertSame(77, $property->fresh()->priority);
    }

    public function test_fleet_view_rejects_invalid_manual_priority_values(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        $this->actingAs(User::factory()->create());

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'negative-priority.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'negative-priority-site',
            'name' => 'Negative Priority Site',
            'primary_domain_id' => $fleetDomain->id,
            'priority' => 5,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->call('updatePropertyPriority', $property->id, '-3')
            ->assertHasErrors(['priority']);

        $this->assertSame(5, $property->fresh()->priority);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->call('updatePropertyPriority', $property->id, '300')
            ->assertHasErrors(['priority']);

        $this->assertSame(5, $property->fresh()->priority);
    }

    public function test_fleet_view_rejects_non_fleet_property_priority_updates(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');
        $this->actingAs(User::factory()->create());

        $property = WebProperty::factory()->create([
            'slug' => 'non-fleet-priority-site',
            'name' => 'Non Fleet Priority Site',
            'priority' => 9,
        ]);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->call('updatePropertyPriority', $property->id, '77')
            ->assertForbidden();

        $this->assertSame(9, $property->fresh()->priority);
    }

    public function test_fleet_view_requires_authentication_for_priority_updates(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'auth-required.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'auth-required-site',
            'name' => 'Auth Required Site',
            'primary_domain_id' => $fleetDomain->id,
            'priority' => 5,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->call('updatePropertyPriority', $property->id, '77')
            ->assertForbidden();

        $this->assertSame(5, $property->fresh()->priority);
    }

    public function test_fleet_view_does_not_allow_client_updates_to_fleet_mode(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->set('fleetFocusMode', false);
    }

    public function test_fleet_view_uses_thirty_items_per_page(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        for ($index = 1; $index <= 31; $index++) {
            $domain = Domain::factory()->create([
                'domain' => sprintf('fleet-%02d.example.com', $index),
                'is_active' => true,
            ]);

            $property = WebProperty::factory()->create([
                'slug' => sprintf('fleet-site-%02d', $index),
                'name' => sprintf('Fleet Site %02d', $index),
                'primary_domain_id' => $domain->id,
            ]);

            WebPropertyDomain::create([
                'web_property_id' => $property->id,
                'domain_id' => $domain->id,
                'usage_type' => 'primary',
                'is_canonical' => true,
            ]);

            $domain->tags()->syncWithoutDetaching([$fleetTag->id]);
        }

        Livewire::test(WebPropertiesList::class, ['fleetFocusMode' => true])
            ->assertSee('Fleet Site 01')
            ->assertSee('Fleet Site 30')
            ->assertSee('Showing')
            ->assertSee('1')
            ->assertSee('30')
            ->assertSee('31')
            ->assertSee('results');
    }
}
