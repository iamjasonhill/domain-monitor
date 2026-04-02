<?php

namespace Tests\Feature;

use App\Livewire\WebPropertyDetail;
use App\Models\Domain;
use App\Models\DomainTag;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class WebPropertyConversionLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_detail_can_save_target_conversion_links(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'moveroo-website'])
            ->set('targetHouseholdQuoteUrl', 'https://quote.moveroo.com.au/household')
            ->set('targetHouseholdBookingUrl', 'https://quote.moveroo.com.au/booking')
            ->set('targetVehicleQuoteUrl', 'https://quote.moveroo.com.au/vehicle')
            ->set('targetVehicleBookingUrl', 'https://quote.moveroo.com.au/vehicle-booking')
            ->call('saveConversionTargets')
            ->assertHasNoErrors();

        $property->refresh();

        $this->assertSame('https://quote.moveroo.com.au/household', $property->target_household_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/booking', $property->target_household_booking_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle', $property->target_vehicle_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle-booking', $property->target_vehicle_booking_url);
    }

    public function test_property_detail_can_refresh_current_conversion_links_from_live_navigation(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://moveroo.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://moveroo.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://removalists.moveroo.com.au/quote/household">Moving Quote</a>
                            <a href="https://cars.moveroo.com.au/quote/v2">Vehicle Transport Quote</a>
                            <a href="https://removalists.moveroo.com.au/booking/create">Book Your Move</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'moveroo-website'])
            ->call('refreshCurrentConversionLinks')
            ->assertHasNoErrors();

        $property->refresh();

        $this->assertSame('https://removalists.moveroo.com.au/quote/household', $property->current_household_quote_url);
        $this->assertSame('https://removalists.moveroo.com.au/booking/create', $property->current_household_booking_url);
        $this->assertSame('https://cars.moveroo.com.au/quote/v2', $property->current_vehicle_quote_url);
        $this->assertNull($property->current_vehicle_booking_url);
        $this->assertNotNull($property->conversion_links_scanned_at);
    }

    public function test_fleet_scan_conversion_links_command_persists_detected_links(): void
    {
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $tag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-com-au',
            'name' => 'wemove.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://wemove.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <nav>
                            <a href="https://wemove.moveroo.com.au/">Quote Page</a>
                            <a href="https://wemove.moveroo.com.au/bookings">Book Removalists</a>
                            <a href="https://vehicles.moveroo.com.au">Car Transport Quote</a>
                        </nav>
                    </body>
                </html>
            HTML),
        ]);

        $this->assertSame(0, Artisan::call('fleet:scan-conversion-links'));

        $property->refresh();

        $this->assertSame('https://wemove.moveroo.com.au/', $property->current_household_quote_url);
        $this->assertSame('https://wemove.moveroo.com.au/bookings', $property->current_household_booking_url);
        $this->assertSame('https://vehicles.moveroo.com.au', $property->current_vehicle_quote_url);
        $this->assertNull($property->current_vehicle_booking_url);
    }
}
