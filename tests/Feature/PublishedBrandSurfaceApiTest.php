<?php

namespace Tests\Feature;

use App\Models\AnalyticsEventContract;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use App\Models\WebPropertyEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishedBrandSurfaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_brand_surface_feed_returns_v1_pilot_payload_shape(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');
        config()->set('domain_monitor.published_brand_surfaces.pilot_host_allowlist', [
            'quotes.moveroo.com.au',
            'mymoveportal.discountbackloading.com.au',
            'quotes.interstate-removals.com.au',
            'quoting.movingcars.com.au',
            'portal.supercheapcartransport.com.au',
        ]);

        $this->createPilotSurface(
            propertySlug: 'moveroo-com-au',
            propertyName: 'Moveroo Website',
            siteKey: 'moveroo',
            primaryDomainName: 'moveroo.com.au',
            hostname: 'quotes.moveroo.com.au',
            journeyType: 'household_quote',
            repoName: 'MM-moveroo.com.au',
            measurementId: 'G-9F3Y80LEQL',
            eventContractKey: 'moveroo-full-funnel-v1',
        );

        $this->createPilotSurface(
            propertySlug: 'discountbackloading-com-au',
            propertyName: 'discountbackloading.com.au',
            siteKey: 'discountbackloading',
            primaryDomainName: 'discountbackloading.com.au',
            hostname: 'mymoveportal.discountbackloading.com.au',
            journeyType: 'mixed_quote',
            repoName: 'MM-discountbackloading.com.au',
            measurementId: 'G-DISCOUNT01',
            eventContractKey: 'discountbackloading-full-funnel-v1',
            productionUrl: 'https://discountbackloading.com.au',
            targetMoverooSubdomainUrl: 'https://mymoveportal.discountbackloading.com.au/',
            targetHouseholdQuoteUrl: 'https://mymoveportal.discountbackloading.com.au/quote/household',
            targetVehicleQuoteUrl: 'https://mymoveportal.discountbackloading.com.au/quote/vehicle',
            targetHouseholdBookingUrl: 'https://mymoveportal.discountbackloading.com.au/booking/create',
            targetContactUsPageUrl: 'https://mymoveportal.discountbackloading.com.au/contact',
            createConversionSurface: false,
        );

        $this->createPilotSurface(
            propertySlug: 'interstate-removals-com-au',
            propertyName: 'interstate-removals.com.au',
            siteKey: 'interstate-removals',
            primaryDomainName: 'interstate-removals.com.au',
            hostname: 'quotes.interstate-removals.com.au',
            journeyType: 'mixed_quote',
            repoName: '_wp-house',
            measurementId: 'G-INTERSTATE01',
            eventContractKey: 'interstate-removals-full-funnel-v1',
            createConversionSurface: false,
        );

        $this->createPilotSurface(
            propertySlug: 'movingcars-com-au',
            propertyName: 'movingcars.com.au',
            siteKey: 'movingcars',
            primaryDomainName: 'movingcars.com.au',
            hostname: 'quoting.movingcars.com.au',
            journeyType: 'vehicle_quote',
            repoName: 'MM-movingcars.com.au',
            measurementId: 'G-MOVINGCARS01',
            eventContractKey: 'movingcars-full-funnel-v1',
            createConversionSurface: false,
        );

        $this->createPilotSurface(
            propertySlug: 'supercheapcartransport-com-au',
            propertyName: 'supercheapcartransport.com.au',
            siteKey: 'supercheapcartransport',
            primaryDomainName: 'supercheapcartransport.com.au',
            hostname: 'portal.supercheapcartransport.com.au',
            journeyType: 'vehicle_quote',
            repoName: 'MM-supercheapcartransport',
            measurementId: 'G-SUPERCHEAP01',
            eventContractKey: 'supercheapcartransport-full-funnel-v1',
            createConversionSurface: false,
        );

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces')
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-published-brand-surfaces')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('generated_by', 'domain-monitor.published-brand-surfaces')
            ->assertJsonPath('pilot.host_allowlist.0', 'quotes.moveroo.com.au')
            ->assertJsonPath('pilot.host_allowlist.1', 'mymoveportal.discountbackloading.com.au')
            ->assertJsonPath('pilot.host_allowlist.2', 'quotes.interstate-removals.com.au')
            ->assertJsonPath('pilot.host_allowlist.3', 'quoting.movingcars.com.au')
            ->assertJsonPath('pilot.host_allowlist.4', 'portal.supercheapcartransport.com.au')
            ->assertJsonPath('authoritative.scope', 'moveroo_v1_authoritative_seed')
            ->assertJsonPath('authoritative.host_allowlist.0', 'mymoveportal.discountbackloading.com.au')
            ->assertJsonPath('authoritative.owning_marketing_domains.0', 'discountbackloading.com.au')
            ->assertJsonPath('surfaces.0.hostname', 'quotes.moveroo.com.au')
            ->assertJsonPath('surfaces.0.property_slug', 'moveroo-com-au')
            ->assertJsonPath('surfaces.0.surface_slug', 'moveroo-quotes-household-v1')
            ->assertJsonPath('surfaces.0.status', 'published')
            ->assertJsonPath('surfaces.0.surface_type', 'quote')
            ->assertJsonPath('surfaces.0.canonical_hostname', 'quotes.moveroo.com.au')
            ->assertJsonPath('surfaces.0.brand.display_name', 'Moveroo')
            ->assertJsonPath('surfaces.0.theme.colors.accent', '#2563eb')
            ->assertJsonPath('surfaces.0.navigation.show_household_quote_link', true)
            ->assertJsonPath('surfaces.0.behavior.allow_admin_links', false)
            ->assertJsonPath('surfaces.0.links.primary_cta_route', 'household.quote')
            ->assertJsonPath('surfaces.0.contact.public_email', 'removals@moveroo.com.au')
            ->assertJsonPath('surfaces.0.analytics.status', 'linked')
            ->assertJsonPath('surfaces.0.analytics.runtime_context_key', 'quotes.moveroo.com.au')
            ->assertJsonPath('surfaces.0.analytics.ga4.measurement_id', 'G-9F3Y80LEQL')
            ->assertJsonPath('surfaces.0.analytics.event_contract.key', 'moveroo-full-funnel-v1')
            ->assertJsonPath('surfaces.0.ownership.published_truth_owner', 'Domain Monitor')
            ->assertJsonPath('surfaces.0.ownership.runtime_renderer_owner', 'MoverooCombined')
            ->assertJsonPath('surfaces.0.ownership.site_repo_owner', 'MM-moveroo.com.au')
            ->assertJsonPath('surfaces.0.ownership.portfolio_routing_owner', 'Bossman')
            ->assertJsonPath('surfaces.0.provenance.change_ref', 'domain-monitor#208')
            ->assertJsonPath('surfaces.1.hostname', 'mymoveportal.discountbackloading.com.au')
            ->assertJsonPath('surfaces.1.property_slug', 'discountbackloading-com-au')
            ->assertJsonPath('surfaces.1.brand.display_name', 'Discount Backloading')
            ->assertJsonPath('surfaces.1.navigation.show_vehicle_quote_link', true)
            ->assertJsonPath('surfaces.1.navigation.show_customer_portal_link', false)
            ->assertJsonPath('surfaces.1.links.primary_cta_route', 'household.quote')
            ->assertJsonPath('surfaces.1.links.household_quote_url', 'https://mymoveportal.discountbackloading.com.au/quote/household')
            ->assertJsonPath('surfaces.1.links.vehicle_quote_url', 'https://mymoveportal.discountbackloading.com.au/quote/vehicle')
            ->assertJsonPath('surfaces.1.links.booking_url', 'https://mymoveportal.discountbackloading.com.au/booking/create')
            ->assertJsonPath('surfaces.1.links.contact_url', 'https://mymoveportal.discountbackloading.com.au/contact')
            ->assertJsonPath('surfaces.1.authority.mode', 'authoritative')
            ->assertJsonPath('surfaces.1.authority.owning_marketing_domain', 'discountbackloading.com.au')
            ->assertJsonPath('surfaces.1.authority.runtime_renderer_owner', 'MoverooCombined')
            ->assertJsonPath('surfaces.1.authority.fallback_policy', 'strict_no_local_brand_fallback')
            ->assertJsonMissingPath('surfaces.1.links.customer_portal_url')
            ->assertJsonPath('surfaces.2.hostname', 'quotes.interstate-removals.com.au')
            ->assertJsonPath('surfaces.2.property_slug', 'interstate-removals-com-au')
            ->assertJsonPath('surfaces.2.links.household_quote_url', 'https://quotes.interstate-removals.com.au/quote/household')
            ->assertJsonPath('surfaces.2.links.vehicle_quote_url', 'https://quotes.interstate-removals.com.au/quote/vehicle')
            ->assertJsonPath('surfaces.2.analytics.status', 'linked')
            ->assertJsonPath('surfaces.3.hostname', 'quoting.movingcars.com.au')
            ->assertJsonPath('surfaces.3.property_slug', 'movingcars-com-au')
            ->assertJsonPath('surfaces.3.navigation.show_vehicle_quote_link', true)
            ->assertJsonPath('surfaces.3.navigation.show_household_quote_link', false)
            ->assertJsonPath('surfaces.3.links.vehicle_quote_url', 'https://quoting.movingcars.com.au/quote/vehicle')
            ->assertJsonPath('surfaces.4.hostname', 'portal.supercheapcartransport.com.au')
            ->assertJsonPath('surfaces.4.property_slug', 'supercheapcartransport-com-au')
            ->assertJsonPath('surfaces.4.links.vehicle_quote_url', 'https://portal.supercheapcartransport.com.au/quote/vehicle')
            ->assertJsonCount(5, 'surfaces');
    }

    public function test_published_brand_surface_feed_is_constrained_to_the_pilot_allowlist(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');
        config()->set('domain_monitor.published_brand_surfaces.pilot_host_allowlist', [
            'quotes.moveroo.com.au',
        ]);
        config()->set('domain_monitor.published_brand_surfaces.authoritative_host_allowlist', [
            'mymoveportal.discountbackloading.com.au',
            'quotes.full-estate.com.au',
        ]);

        $this->createPilotSurface(
            propertySlug: 'moveroo-com-au',
            propertyName: 'Moveroo Website',
            siteKey: 'moveroo',
            primaryDomainName: 'moveroo.com.au',
            hostname: 'quotes.moveroo.com.au',
            journeyType: 'household_quote',
            repoName: 'MM-moveroo.com.au',
            measurementId: 'G-9F3Y80LEQL',
            eventContractKey: 'moveroo-full-funnel-v1',
        );

        $this->createPilotSurface(
            propertySlug: 'full-estate-com-au',
            propertyName: 'Full Estate Website',
            siteKey: 'fullestate',
            primaryDomainName: 'full-estate.com.au',
            hostname: 'quotes.full-estate.com.au',
            journeyType: 'household_quote',
            repoName: 'MM-full-estate',
            measurementId: 'G-FULLESTATE',
            eventContractKey: 'full-estate-full-funnel-v1',
        );

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces')
            ->assertOk()
            ->assertJsonCount(0, 'authoritative.host_allowlist')
            ->assertJsonCount(0, 'authoritative.owning_marketing_domains')
            ->assertJsonCount(1, 'surfaces')
            ->assertJsonPath('surfaces.0.hostname', 'quotes.moveroo.com.au');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces?hostname=quotes.full-estate.com.au')
            ->assertOk()
            ->assertJsonCount(0, 'surfaces');
    }

    public function test_published_brand_surface_feed_returns_configured_third_batch_hosts(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');

        $thirdBatchHostnames = [
            'mymovehub.backloading-services.com.au',
            'mymovehub.backloadingremovals.com.au',
            'portal.movemycar.com.au',
            'quotes.wemove.com.au',
            'quoting.backloading-au.com.au',
            'quoting.cartransport.au',
            'quoting.cartransportaus.com.au',
            'quoting.cartransportwithpersonalitems.com.au',
            'quoting.interstate-car-transport.com.au',
            'quoting.interstatecarcarriers.com.au',
            'quoting.perthinterstateremovalists.com.au',
            'quoting.removalsinterstate.com.au',
            'quoting.transportnondrivablecars.com.au',
            'removalistquotes.movingagain.com.au',
            'removalists.moveroo.com.au',
            'removalportal.interstate-removals.com.au',
            'removalquotes.backloading-services.com.au',
            'moving.allianceremovals.com.au',
        ];

        config()->set('domain_monitor.published_brand_surfaces.pilot_host_allowlist', $thirdBatchHostnames);
        $metadataByHostname = config('domain_monitor.published_brand_surfaces.hostnames');
        $this->assertIsArray($metadataByHostname);

        foreach ($thirdBatchHostnames as $hostname) {
            $this->assertArrayHasKey($hostname, $metadataByHostname);
            $metadata = $metadataByHostname[$hostname];
            $propertySlug = $metadata['property_slug'];
            $measurementKey = preg_replace('/[^A-Za-z0-9]/', '', $propertySlug) ?: 'SURFACE';

            if (WebProperty::query()->where('slug', $propertySlug)->exists()) {
                continue;
            }

            $this->createConfiguredProperty(
                propertySlug: $propertySlug,
                propertyName: (string) $metadata['owning_marketing_domain'],
                siteKey: (string) $metadata['brand']['brand_key'],
                primaryDomainName: (string) $metadata['owning_marketing_domain'],
                repoName: (string) ($metadata['controller_repo'] ?? '_wp-house'),
                measurementId: 'G-'.strtoupper(substr($measurementKey, 0, 10)),
                eventContractKey: $propertySlug.'-full-funnel-v1',
            );
        }

        $response = $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces')
            ->assertOk()
            ->assertJsonCount(count($thirdBatchHostnames), 'surfaces')
            ->assertJsonPath('surfaces.0.hostname', 'mymovehub.backloading-services.com.au')
            ->assertJsonPath('surfaces.0.links.household_quote_url', 'https://mymovehub.backloading-services.com.au/quote/household')
            ->assertJsonPath('surfaces.2.hostname', 'portal.movemycar.com.au')
            ->assertJsonPath('surfaces.2.links.vehicle_quote_url', 'https://portal.movemycar.com.au/quote/vehicle')
            ->assertJsonPath('surfaces.17.hostname', 'moving.allianceremovals.com.au')
            ->assertJsonPath('surfaces.17.links.booking_url', 'https://moving.allianceremovals.com.au/booking/create');

        $publishedHostnames = collect($response->json('surfaces'))->pluck('hostname')->all();

        foreach ([
            'cartransport.movingagain.com.au',
            'perth.moveroo.com.au',
            'quotes.interstateremovalists.net.au',
            'quoting.mover.com.au',
            'quoting.vehicle.net.au',
            'removalist.backloadingremovals.com.au',
            'interstate-removals.moveroo.com.au',
        ] as $classifiedHostname) {
            $this->assertNotContains($classifiedHostname, $publishedHostnames);
        }
    }

    public function test_brand_style_draft_feed_maps_app_hosts_to_source_domains_with_review_evidence(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surface-drafts')
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-brand-style-drafts')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('proposals.0.hostname', 'quoting.movingcars.com.au')
            ->assertJsonPath('proposals.0.source_marketing_domain', 'movingcars.com.au')
            ->assertJsonPath('proposals.0.approval_status', 'approved')
            ->assertJsonPath('proposals.0.publish_gate.can_publish', true)
            ->assertJsonPath('proposals.0.candidate.brand.display_name', 'Moving Cars')
            ->assertJsonPath('proposals.0.evidence.0.field', 'source_marketing_domain')
            ->assertJsonPath('proposals.0.evidence.0.confidence', 'high')
            ->assertJsonPath('proposals.1.hostname', 'mymovehub.backloading-services.com.au')
            ->assertJsonPath('proposals.1.source_marketing_domain', 'backloading-services.com.au')
            ->assertJsonPath('proposals.1.approval_status', 'needs_review')
            ->assertJsonPath('proposals.1.publish_gate.can_publish', false)
            ->assertJsonPath('proposals.1.publish_gate.reason', 'draft_requires_human_or_trusted_review')
            ->assertJsonPath('proposals.2.hostname', 'quotes.interstate-removals.com.au')
            ->assertJsonPath('proposals.2.source_marketing_domain', 'interstate-removals.com.au')
            ->assertJsonPath('proposals.2.approval_status', 'approved')
            ->assertJsonPath('proposals.2.publish_gate.can_publish', true)
            ->assertJsonCount(3, 'proposals');

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surface-drafts?hostname=quoting.movingcars.com.au')
            ->assertOk()
            ->assertJsonCount(1, 'proposals')
            ->assertJsonPath('proposals.0.hostname', 'quoting.movingcars.com.au');
    }

    public function test_only_approved_brand_style_drafts_annotate_the_published_feed(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');
        config()->set('domain_monitor.published_brand_surfaces.pilot_host_allowlist', [
            'quoting.movingcars.com.au',
            'mymovehub.backloading-services.com.au',
            'quotes.interstate-removals.com.au',
        ]);

        $metadataByHostname = config('domain_monitor.published_brand_surfaces.hostnames');
        $this->assertIsArray($metadataByHostname);

        foreach ([
            'quoting.movingcars.com.au',
            'mymovehub.backloading-services.com.au',
            'quotes.interstate-removals.com.au',
        ] as $hostname) {
            $metadata = $metadataByHostname[$hostname];
            $measurementKey = preg_replace('/[^A-Za-z0-9]/', '', (string) $metadata['property_slug']) ?: 'SURFACE';

            $this->createConfiguredProperty(
                propertySlug: (string) $metadata['property_slug'],
                propertyName: (string) $metadata['owning_marketing_domain'],
                siteKey: (string) $metadata['brand']['brand_key'],
                primaryDomainName: (string) $metadata['owning_marketing_domain'],
                repoName: '_wp-house',
                measurementId: 'G-'.strtoupper(substr($measurementKey, 0, 10)),
                eventContractKey: $metadata['property_slug'].'-full-funnel-v1',
            );
        }

        $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces')
            ->assertOk()
            ->assertJsonCount(3, 'surfaces')
            ->assertJsonPath('surfaces.0.hostname', 'quoting.movingcars.com.au')
            ->assertJsonPath('surfaces.0.brand_style_source.source_marketing_domain', 'movingcars.com.au')
            ->assertJsonPath('surfaces.0.brand_style_source.approval_status', 'approved')
            ->assertJsonPath('surfaces.0.brand_style_source.evidence_count', 3)
            ->assertJsonPath('surfaces.1.hostname', 'mymovehub.backloading-services.com.au')
            ->assertJsonMissingPath('surfaces.1.brand_style_source')
            ->assertJsonPath('surfaces.2.hostname', 'quotes.interstate-removals.com.au')
            ->assertJsonPath('surfaces.2.brand_style_source.source_marketing_domain', 'interstate-removals.com.au')
            ->assertJsonPath('surfaces.2.brand_style_source.approval_status', 'approved');
    }

    public function test_published_brand_surface_feed_returns_final_runtime_closeout_host_and_classifies_the_rest(): void
    {
        config()->set('services.domain_monitor.moveroo_removals_api_key', 'moveroo-runtime-token');
        config()->set('domain_monitor.published_brand_surfaces.pilot_host_allowlist', [
            'quoteandbook.mover.com.au',
        ]);

        $metadataByHostname = config('domain_monitor.published_brand_surfaces.hostnames');
        $this->assertIsArray($metadataByHostname);
        $this->assertArrayHasKey('quoteandbook.mover.com.au', $metadataByHostname);

        $metadata = $metadataByHostname['quoteandbook.mover.com.au'];
        $this->createConfiguredProperty(
            propertySlug: (string) $metadata['property_slug'],
            propertyName: (string) $metadata['owning_marketing_domain'],
            siteKey: (string) $metadata['brand']['brand_key'],
            primaryDomainName: (string) $metadata['owning_marketing_domain'],
            repoName: '_wp-house',
            measurementId: 'G-MOVERFINAL',
            eventContractKey: $metadata['property_slug'].'-full-funnel-v1',
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer moveroo-runtime-token',
        ])->getJson('/api/published-brand-surfaces')
            ->assertOk()
            ->assertJsonCount(1, 'surfaces')
            ->assertJsonPath('surfaces.0.hostname', 'quoteandbook.mover.com.au')
            ->assertJsonPath('surfaces.0.property_slug', 'mover-com-au')
            ->assertJsonPath('surfaces.0.navigation.show_household_quote_link', true)
            ->assertJsonPath('surfaces.0.navigation.show_vehicle_quote_link', true)
            ->assertJsonPath('surfaces.0.links.household_quote_url', 'https://quoteandbook.mover.com.au/quote/household')
            ->assertJsonPath('surfaces.0.links.vehicle_quote_url', 'https://quoteandbook.mover.com.au/quote/vehicle')
            ->assertJsonPath('surfaces.0.links.booking_url', 'https://quoteandbook.mover.com.au/booking/create')
            ->assertJsonPath('surfaces.0.analytics.status', 'linked');

        $remainingRuntimeHostnames = [
            'acraustralia.com',
            'again.com.au',
            'allianceremovals.com.au',
            'backload.net.au',
            'backloading-au.com.au',
            'backloading-services.com.au',
            'backloading.net.au',
            'backloadingremovalist.com.au',
            'backloadingremovals.com',
            'backloadingremovals.com.au',
            'backloadingremovals.moveroo.com.au',
            'backloads.net.au',
            'beauy.com.au',
            'bestinterstateremovals.com.au',
            'car-carrying.com.au',
            'cartransport.au',
            'cartransport.movingagain.com.au',
            'cartransport.net.au',
            'cartransportaus.com.au',
            'cartransportwithpersonalitems.com.au',
            'deftly.com.au',
            'discountbackloading.com',
            'discountbackloading.com.au',
            'discountbackloading.moveroo.com.au',
            'furnitureremovalist.com',
            'interstate-car-transport.com.au',
            'interstate-removalists.net.au',
            'interstate-removals.com.au',
            'interstate-removals.moveroo.com.au',
            'interstatecarcarriers.com.au',
            'interstateremovalists.au',
            'interstateremovalists.net.au',
            'jasonhill.com.au',
            'jhmh.com.au',
            'konradhill.com',
            'mandyhill.com.au',
            'movemycar.com.au',
            'mover.com.au',
            'moveroo.au',
            'moveroo.click',
            'moveroo.com.au',
            'moving.au',
            'moving.com.au',
            'movingagain.com',
            'movingagain.com.au',
            'movingagain.net',
            'movingagain.net.au',
            'movingcars.com.au',
            'movingcars.net.au',
            'movingcartons.com.au',
            'movinghome.com.au',
            'movinginsurance.com.au',
            'movinginterstate.com.au',
            'movingreviews.com.au',
            'nfgseo.com.au',
            'olliehill.com.au',
            'perth.moveroo.com.au',
            'perthinterstateremovalists.com.au',
            'pngchambers.com',
            'prepack.com.au',
            'quoteandbook.mover.com.au',
            'quotes.interstateremovalists.net.au',
            'quoting.mover.com.au',
            'quoting.vehicle.net.au',
            'redirection.com.au',
            'removalist.backloadingremovals.com.au',
            'removalist.net',
            'removals.au',
            'removals.com.au',
            'removalsinterstate.com.au',
            'rollover.com.au',
            'supercheapcartransport.com.au',
            'supercheapcartransport.net.au',
            'synonymous.com.au',
            'tinyurl.com.au',
            'transportnondrivablecars.com.au',
            'vehicle.net.au',
            'wemove.com.au',
            'wemove.moveroo.com.au',
        ];

        $classifiedRuntimeHostnames = config('domain_monitor.published_brand_surfaces.classified_runtime_hostnames');
        $this->assertIsArray($classifiedRuntimeHostnames);
        $this->assertCount(78, $classifiedRuntimeHostnames);

        foreach ($remainingRuntimeHostnames as $hostname) {
            if ($hostname === 'quoteandbook.mover.com.au') {
                $this->assertContains($hostname, config('domain_monitor.published_brand_surfaces.pilot_host_allowlist'));

                continue;
            }

            $this->assertArrayHasKey($hostname, $classifiedRuntimeHostnames, "{$hostname} is not classified");
            $this->assertNotEmpty($classifiedRuntimeHostnames[$hostname]['classification']);
            $this->assertNotEmpty($classifiedRuntimeHostnames[$hostname]['reason']);
        }
    }

    public function test_published_brand_surface_fixtures_match_the_contract_shape(): void
    {
        foreach ([
            base_path('docs/fixtures/published-brand-surfaces/household-quote.json'),
            base_path('docs/fixtures/published-brand-surfaces/discountbackloading-quote.json'),
            base_path('docs/fixtures/published-brand-surfaces/second-pilot-batch.json'),
            base_path('docs/fixtures/published-brand-surfaces/third-pilot-batch.json'),
            base_path('docs/fixtures/published-brand-surfaces/final-runtime-closeout.json'),
        ] as $fixturePath) {
            $payload = json_decode((string) file_get_contents($fixturePath), true);

            $this->assertIsArray($payload);
            $this->assertSame('domain-monitor-published-brand-surfaces', $payload['source_system'] ?? null);
            $this->assertSame(1, $payload['contract_version'] ?? null);
            $this->assertIsArray($payload['pilot']['host_allowlist'] ?? null);
            $this->assertIsArray($payload['surfaces'] ?? null);
            $this->assertNotEmpty($payload['surfaces']);

            foreach ($payload['surfaces'] as $surface) {
                foreach ([
                    'hostname',
                    'property_slug',
                    'surface_slug',
                    'status',
                    'surface_type',
                    'canonical_role',
                    'canonical_hostname',
                    'brand',
                    'copy',
                    'theme',
                    'navigation',
                    'behavior',
                    'links',
                    'contact',
                    'analytics',
                    'ownership',
                    'provenance',
                ] as $requiredField) {
                    $this->assertArrayHasKey($requiredField, $surface, "{$requiredField} missing from {$fixturePath}");
                }
            }
        }
    }

    private function createConfiguredProperty(
        string $propertySlug,
        string $propertyName,
        string $siteKey,
        string $primaryDomainName,
        string $repoName,
        string $measurementId,
        string $eventContractKey,
    ): void {
        $primaryDomain = Domain::factory()->create([
            'domain' => $primaryDomainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $propertySlug,
            'name' => $propertyName,
            'site_key' => $siteKey,
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => $repoName,
            'repo_provider' => 'github',
            'framework' => 'Laravel',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => $measurementId,
            'external_name' => $propertyName.' GA4',
            'provider_config' => [
                'site_key' => $siteKey,
                'measurement_id' => $measurementId,
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $eventContract = AnalyticsEventContract::create([
            'key' => $eventContractKey,
            'name' => $propertyName.' Full Funnel',
            'version' => 'v1',
            'contract_type' => 'ga4_web_and_backend',
            'status' => 'active',
        ]);

        WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $eventContract->id,
            'is_primary' => true,
            'rollout_status' => 'instrumented',
        ]);
    }

    private function createPilotSurface(
        string $propertySlug,
        string $propertyName,
        string $siteKey,
        string $primaryDomainName,
        string $hostname,
        string $journeyType,
        string $repoName,
        string $measurementId,
        string $eventContractKey,
        ?string $productionUrl = null,
        ?string $targetMoverooSubdomainUrl = null,
        ?string $targetHouseholdQuoteUrl = null,
        ?string $targetVehicleQuoteUrl = null,
        ?string $targetHouseholdBookingUrl = null,
        ?string $targetContactUsPageUrl = null,
        bool $createConversionSurface = true,
    ): void {
        $primaryDomain = Domain::factory()->create([
            'domain' => $primaryDomainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $propertySlug,
            'name' => $propertyName,
            'site_key' => $siteKey,
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => $productionUrl,
            'target_moveroo_subdomain_url' => $targetMoverooSubdomainUrl,
            'target_household_quote_url' => $targetHouseholdQuoteUrl,
            'target_vehicle_quote_url' => $targetVehicleQuoteUrl,
            'target_household_booking_url' => $targetHouseholdBookingUrl,
            'target_contact_us_page_url' => $targetContactUsPageUrl ?? '/contact',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => $repoName,
            'repo_provider' => 'github',
            'framework' => 'Laravel',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        $analyticsSource = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => $measurementId,
            'external_name' => $propertyName.' GA4',
            'provider_config' => [
                'site_key' => $siteKey,
                'measurement_id' => $measurementId,
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $eventContract = AnalyticsEventContract::create([
            'key' => $eventContractKey,
            'name' => $propertyName.' Full Funnel',
            'version' => 'v1',
            'contract_type' => 'ga4_web_and_backend',
            'status' => 'active',
        ]);

        $eventAssignment = WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $eventContract->id,
            'is_primary' => true,
            'rollout_status' => 'instrumented',
        ]);

        if (! $createConversionSurface) {
            return;
        }

        $surfaceDomain = Domain::factory()->create([
            'domain' => $hostname,
            'is_active' => true,
        ]);

        WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'domain_id' => $surfaceDomain->id,
            'hostname' => $hostname,
            'surface_type' => 'quote_subdomain',
            'journey_type' => $journeyType,
            'runtime_driver' => 'Laravel',
            'runtime_label' => $journeyType === 'vehicle_quote' ? 'Moveroo Cars 2026' : 'Moveroo Removals 2026',
            'runtime_path' => $journeyType === 'vehicle_quote'
                ? '/Users/jasonhill/Projects/laravel-projects/Moveroo-Cars-2026'
                : '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'instrumented',
            'property_analytics_source_id' => $analyticsSource->id,
            'web_property_event_contract_id' => $eventAssignment->id,
        ]);
    }
}
