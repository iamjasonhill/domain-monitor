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
            ->set('targetMoverooSubdomainUrl', 'https://wemove.moveroo.com.au')
            ->set('targetContactUsPageUrl', 'https://moveroo.com.au/contact-us')
            ->call('saveConversionTargets')
            ->assertHasNoErrors();

        $property->refresh();

        $this->assertSame('https://quote.moveroo.com.au/household', $property->target_household_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/booking', $property->target_household_booking_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle', $property->target_vehicle_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle-booking', $property->target_vehicle_booking_url);
        $this->assertSame('https://wemove.moveroo.com.au', $property->target_moveroo_subdomain_url);
        $this->assertSame('https://moveroo.com.au/contact-us', $property->target_contact_us_page_url);
    }

    public function test_property_detail_can_clear_target_conversion_links(): void
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
            'target_household_quote_url' => 'https://quote.moveroo.com.au/household',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'moveroo-website'])
            ->set('targetHouseholdQuoteUrl', '   ')
            ->call('saveConversionTargets')
            ->assertHasNoErrors();

        $this->assertNull($property->fresh()->target_household_quote_url);
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

    public function test_property_detail_rejects_mutations_for_unauthorized_users_outside_local_env(): void
    {
        $originalEnvironment = $this->app['env'];
        $this->app['env'] = 'production';

        config()->set('domain_monitor.property_mutation_emails', ['allowed@example.com']);

        $user = User::factory()->create(['email' => 'blocked@example.com']);
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

        try {
            Livewire::actingAs($user)
                ->test(WebPropertyDetail::class, ['propertySlug' => 'moveroo-website'])
                ->set('targetHouseholdQuoteUrl', 'https://quote.moveroo.com.au/household')
                ->set('targetMoverooSubdomainUrl', 'https://wemove.moveroo.com.au')
                ->call('saveConversionTargets')
                ->assertForbidden();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
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

    public function test_fleet_scan_conversion_links_prefers_primary_domain_over_non_root_production_url(): void
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
            'domain' => 'backloadingremovals.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'backloadingremovals-com-au',
            'name' => 'backloadingremovals.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://mymovehub.backloadingremovals.com.au/booking/create',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://backloadingremovals.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymovehub.backloadingremovals.com.au/quote/household">Moving Furniture &amp; boxes</a>
                            <a href="https://mymovehub.backloadingremovals.com.au/booking/create">Book Your Move</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://mymovehub.backloadingremovals.com.au/booking/create' => Http::response('<html><body>No nav</body></html>'),
        ]);

        $this->assertSame(0, Artisan::call('fleet:scan-conversion-links', ['propertySlug' => 'backloadingremovals-com-au']));

        $property->refresh();

        $this->assertSame('https://mymovehub.backloadingremovals.com.au/quote/household', $property->current_household_quote_url);
        $this->assertSame('https://mymovehub.backloadingremovals.com.au/booking/create', $property->current_household_booking_url);
    }

    public function test_fleet_scan_conversion_links_clears_previous_values_when_a_bucket_is_missing(): void
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
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moving-again',
            'name' => 'Moving Again',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://movingagain.com.au',
            'current_vehicle_quote_url' => 'https://cartransport.movingagain.com.au/quote',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://movingagain.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://removalistquotes.movingagain.com.au/quote/household">Get a Quote</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        $this->assertSame(0, Artisan::call('fleet:scan-conversion-links', ['propertySlug' => 'moving-again']));

        $property->refresh();

        $this->assertSame('https://removalistquotes.movingagain.com.au/quote/household', $property->current_household_quote_url);
        $this->assertNull($property->current_vehicle_quote_url);
    }

    public function test_fleet_scan_conversion_links_does_not_clear_existing_values_when_homepage_redirects(): void
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
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://moveroo.com.au',
            'current_household_quote_url' => 'https://removalists.moveroo.com.au/quote/household',
            'current_household_booking_url' => 'https://removalists.moveroo.com.au/booking/create',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://moveroo.com.au' => Http::response('', 302, [
                'Location' => 'https://www.moveroo.com.au',
            ]),
        ]);

        $this->assertSame(1, Artisan::call('fleet:scan-conversion-links', ['propertySlug' => 'moveroo-website']));

        $property->refresh();

        $this->assertSame('https://removalists.moveroo.com.au/quote/household', $property->current_household_quote_url);
        $this->assertSame('https://removalists.moveroo.com.au/booking/create', $property->current_household_booking_url);
    }

    public function test_fleet_scan_conversion_links_does_not_treat_facebook_as_a_booking_link(): void
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

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://moveroo.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://facebook.com/moveroo">Facebook</a>
                            <a href="https://removalists.moveroo.com.au/quote/household">Moving Quote</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        $this->assertSame(0, Artisan::call('fleet:scan-conversion-links', ['propertySlug' => 'moveroo-website']));

        $property->refresh();

        $this->assertSame('https://removalists.moveroo.com.au/quote/household', $property->current_household_quote_url);
        $this->assertNull($property->current_household_booking_url);
    }
}
