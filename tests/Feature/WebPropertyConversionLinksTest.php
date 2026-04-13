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
            ->set('targetLegacyBookingsReplacementUrl', 'https://removalist.net/booking/create')
            ->set('targetLegacyPaymentsReplacementUrl', 'https://wemove.moveroo.com.au/contact')
            ->call('saveConversionTargets')
            ->assertHasNoErrors();

        $property->refresh();

        $this->assertSame('https://quote.moveroo.com.au/household', $property->target_household_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/booking', $property->target_household_booking_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle', $property->target_vehicle_quote_url);
        $this->assertSame('https://quote.moveroo.com.au/vehicle-booking', $property->target_vehicle_booking_url);
        $this->assertSame('https://wemove.moveroo.com.au', $property->target_moveroo_subdomain_url);
        $this->assertSame('https://moveroo.com.au/contact-us', $property->target_contact_us_page_url);
        $this->assertSame('https://removalist.net/booking/create', $property->target_legacy_bookings_replacement_url);
        $this->assertSame('https://wemove.moveroo.com.au/contact', $property->target_legacy_payments_replacement_url);
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

    public function test_property_detail_prefills_vehicle_quote_from_moveroo_subdomain_when_missing(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-website',
            'name' => 'Moving Again Website',
            'primary_domain_id' => $domain->id,
            'target_vehicle_quote_url' => null,
            'target_moveroo_subdomain_url' => 'https://quotes.movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->assertSame(
            'https://quotes.movingagain.com.au/quote/vehicle',
            $property->conversionLinkSummary()['target']['vehicle_quote']
        );

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'movingagain-website'])
            ->assertSet('targetVehicleQuoteUrl', 'https://quotes.movingagain.com.au/quote/vehicle');
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

    public function test_property_detail_refresh_captures_legacy_moveroo_endpoint_drift(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'Discount Backloading',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
            'target_moveroo_subdomain_url' => 'https://mymoveportal.discountbackloading.com.au',
            'target_legacy_bookings_replacement_url' => 'https://removalist.net/booking/create',
            'target_legacy_payments_replacement_url' => 'https://mymoveportal.discountbackloading.com.au/contact',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymoveportal.discountbackloading.com.au/bookings">Book Online</a>
                            <a href="https://mymoveportal.discountbackloading.com.au/payments">Make Payment</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://mymoveportal.discountbackloading.com.au/bookings' => Http::response('', 302, [
                'Location' => 'https://removalist.net/booking/create',
            ]),
            'https://removalist.net/booking/create' => Http::response('<html><body>Booking</body></html>', 200),
            'https://mymoveportal.discountbackloading.com.au/payments' => Http::response('', 302, [
                'Location' => 'https://mymoveportal.discountbackloading.com.au/contact',
            ]),
            'https://mymoveportal.discountbackloading.com.au/contact' => Http::response('<html><body>Contact</body></html>', 200),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'discountbackloading-com-au'])
            ->call('refreshCurrentConversionLinks')
            ->assertSee('Host changed: Yes')
            ->assertSee('Preferred replacement: https://removalist.net/booking/create')
            ->assertHasNoErrors();

        $legacyEndpoints = $property->fresh()->conversionLinkSummary()['legacy_endpoints'];

        $this->assertSame(
            'https://mymoveportal.discountbackloading.com.au/bookings',
            $legacyEndpoints['legacy_booking_endpoint']['url']
        );
        $this->assertSame(
            'https://removalist.net/booking/create',
            $legacyEndpoints['legacy_booking_endpoint']['resolved_url']
        );
        $this->assertSame(200, $legacyEndpoints['legacy_booking_endpoint']['resolved_status']);
        $this->assertTrue($legacyEndpoints['legacy_booking_endpoint']['resolved_host_changed']);
        $this->assertSame(
            'https://removalist.net/booking/create',
            $legacyEndpoints['legacy_booking_endpoint']['preferred_replacement']
        );
        $this->assertSame(
            'https://mymoveportal.discountbackloading.com.au/payments',
            $legacyEndpoints['legacy_payment_endpoint']['url']
        );
        $this->assertSame(
            'https://mymoveportal.discountbackloading.com.au/contact',
            $legacyEndpoints['legacy_payment_endpoint']['resolved_url']
        );
        $this->assertFalse($legacyEndpoints['legacy_payment_endpoint']['resolved_host_changed']);
        $this->assertSame(
            'https://mymoveportal.discountbackloading.com.au/contact',
            $legacyEndpoints['legacy_payment_endpoint']['preferred_replacement']
        );
    }

    public function test_property_detail_refresh_requires_an_explicit_moveroo_subdomain_target_for_legacy_endpoint_detection(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'Discount Backloading',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://portal.discountbackloading.com.au/bookings">Book Online</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'discountbackloading-com-au'])
            ->call('refreshCurrentConversionLinks')
            ->assertHasNoErrors();

        $legacyEndpoints = $property->fresh()->conversionLinkSummary()['legacy_endpoints'];

        $this->assertNull($legacyEndpoints['legacy_booking_endpoint']);
        $this->assertNull($legacyEndpoints['legacy_payment_endpoint']);
    }

    public function test_property_detail_refresh_preserves_previous_legacy_resolution_when_probe_fails(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'Discount Backloading',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
            'target_moveroo_subdomain_url' => 'https://mymoveportal.discountbackloading.com.au',
            'legacy_moveroo_endpoint_scan' => [
                'legacy_booking_endpoint' => [
                    'classification' => 'legacy_booking_endpoint',
                    'found_on' => 'https://discountbackloading.com.au',
                    'url' => 'https://mymoveportal.discountbackloading.com.au/bookings',
                    'resolved_url' => 'https://removalist.net/booking/create',
                    'resolved_status' => 200,
                    'resolved_host_changed' => true,
                ],
                'legacy_payment_endpoint' => null,
            ],
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymoveportal.discountbackloading.com.au/bookings">Book Online</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://mymoveportal.discountbackloading.com.au/bookings' => Http::failedConnection(),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'discountbackloading-com-au'])
            ->call('refreshCurrentConversionLinks')
            ->assertHasNoErrors();

        $legacyEndpoints = $property->fresh()->conversionLinkSummary()['legacy_endpoints'];

        $this->assertSame(
            'https://removalist.net/booking/create',
            $legacyEndpoints['legacy_booking_endpoint']['resolved_url']
        );
        $this->assertSame(200, $legacyEndpoints['legacy_booking_endpoint']['resolved_status']);
        $this->assertTrue($legacyEndpoints['legacy_booking_endpoint']['resolved_host_changed']);
    }

    public function test_property_detail_refresh_blocks_unsafe_legacy_redirect_targets(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'Discount Backloading',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
            'target_moveroo_subdomain_url' => 'https://mymoveportal.discountbackloading.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymoveportal.discountbackloading.com.au/bookings">Book Online</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://mymoveportal.discountbackloading.com.au/bookings' => Http::response('', 302, [
                'Location' => 'http://127.0.0.1/private?token=secret',
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'discountbackloading-com-au'])
            ->call('refreshCurrentConversionLinks')
            ->assertHasNoErrors();

        $legacyEndpoints = $property->fresh()->conversionLinkSummary()['legacy_endpoints'];

        $this->assertNull($legacyEndpoints['legacy_booking_endpoint']['resolved_url']);
        $this->assertSame(302, $legacyEndpoints['legacy_booking_endpoint']['resolved_status']);
        $this->assertNull($legacyEndpoints['legacy_booking_endpoint']['resolved_host_changed']);

        Http::assertSentCount(2);
    }

    public function test_property_detail_refresh_sanitizes_resolved_legacy_endpoint_urls(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'discountbackloading.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'discountbackloading-com-au',
            'name' => 'Discount Backloading',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://discountbackloading.com.au',
            'target_moveroo_subdomain_url' => 'https://mymoveportal.discountbackloading.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Http::fake([
            'https://discountbackloading.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymoveportal.discountbackloading.com.au/bookings">Book Online</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://mymoveportal.discountbackloading.com.au/bookings' => Http::response('', 302, [
                'Location' => 'https://removalist.net/booking/create?token=secret#frag',
            ]),
            'https://removalist.net/booking/create?token=secret#frag' => Http::response('<html><body>Booking</body></html>', 200),
            'https://removalist.net/booking/create' => Http::response('<html><body>Booking</body></html>', 200),
        ]);

        Livewire::actingAs($user)
            ->test(WebPropertyDetail::class, ['propertySlug' => 'discountbackloading-com-au'])
            ->call('refreshCurrentConversionLinks')
            ->assertHasNoErrors();

        $legacyEndpoints = $property->fresh()->conversionLinkSummary()['legacy_endpoints'];

        $this->assertSame(
            'https://removalist.net/booking/create',
            $legacyEndpoints['legacy_booking_endpoint']['resolved_url']
        );
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

    public function test_fleet_owned_subdomain_audit_is_clean_when_only_canonical_moveroo_subdomain_is_linked(): void
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

        $canonicalSubdomain = Domain::factory()->create([
            'domain' => 'quotes.wemove.com.au',
            'is_active' => true,
        ]);

        $legacySubdomain = Domain::factory()->create([
            'domain' => 'removalportal.wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-com-au',
            'name' => 'wemove.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
            'target_moveroo_subdomain_url' => 'https://quotes.wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $canonicalSubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $legacySubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://wemove.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://quotes.wemove.com.au/quote/household">Get Quote</a>
                            <a href="https://quotes.wemove.com.au/contact">Contact</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        $this->assertSame(0, Artisan::call('fleet:audit-owned-subdomain-links', ['propertySlug' => 'wemove-com-au']));
        $this->assertStringContainsString('clean: no owned-subdomain drift links found', Artisan::output());
    }

    public function test_fleet_owned_subdomain_audit_flags_links_to_other_owned_subdomains(): void
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

        $canonicalSubdomain = Domain::factory()->create([
            'domain' => 'mymovehub.backloadingremovals.com.au',
            'is_active' => true,
        ]);

        $legacySubdomain = Domain::factory()->create([
            'domain' => 'removalist.backloadingremovals.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'backloadingremovals-com-au',
            'name' => 'backloadingremovals.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://backloadingremovals.com.au',
            'target_moveroo_subdomain_url' => 'https://mymovehub.backloadingremovals.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $canonicalSubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $legacySubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        $domain->tags()->syncWithoutDetaching([$tag->id]);

        Http::fake([
            'https://backloadingremovals.com.au' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://mymovehub.backloadingremovals.com.au/quote/household">Canonical quote</a>
                            <a href="https://removalist.backloadingremovals.com.au/payments">Legacy portal</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        $this->assertSame(1, Artisan::call('fleet:audit-owned-subdomain-links', ['propertySlug' => 'backloadingremovals-com-au']));
        $output = Artisan::output();

        $this->assertStringContainsString('drift: canonical=mymovehub.backloadingremovals.com.au', $output);
        $this->assertStringContainsString('removalist.backloadingremovals.com.au', $output);
        $this->assertStringContainsString('https://removalist.backloadingremovals.com.au/payments', $output);
    }
}
