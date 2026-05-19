<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fleet Focus
    |--------------------------------------------------------------------------
    |
    | Manual grouping for the current Fleet working set. Membership lives on
    | the primary/canonical domain tag so it can be managed with the existing
    | domain tag system, while property priority remains per web property.
    |
    */
    'fleet_focus' => [
        'tag_name' => env('DOMAIN_MONITOR_FLEET_FOCUS_TAG', 'fleet.live'),
        'domains' => [
            'allianceremovals.com.au',
            'backloading-au.com.au',
            'backloading-services.com.au',
            'backloadingremovals.com.au',
            'beauy.com.au',
            'cartransport.au',
            'cartransport.movingagain.com.au',
            'cartransportaus.com.au',
            'cartransportwithpersonalitems.com.au',
            'discountbackloading.com.au',
            'interstate-car-transport.com.au',
            'interstate-removals.com.au',
            'interstatecarcarriers.com.au',
            'movemycar.com.au',
            'mover.com.au',
            'moveroo.com.au',
            'movingagain.com.au',
            'movingcars.com.au',
            'movinginsurance.com.au',
            'perthinterstateremovalists.com.au',
            'removalist.net',
            'removalsinterstate.com.au',
            'supercheapcartransport.com.au',
            'transportnondrivablecars.com.au',
            'vehicle.net.au',
            'wemove.com.au',
        ],
        'repository_controlled_domains' => [
            'transportnondrivablecars.com.au',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversion Surfaces
    |--------------------------------------------------------------------------
    |
    | Quote and booking hostnames often ride on shared runtimes. Keep their
    | runtime metadata centralized here so backfills and UI writes stay aligned
    | with the maintained Laravel implementation.
    |
    */
    'conversion_surfaces' => [
        'default_quote_surface' => [
            'surface_type' => 'quote_subdomain',
            'journey_type' => 'mixed_quote',
            'runtime_driver' => 'Laravel',
            'runtime_label' => 'Moveroo Removals 2026',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'defined',
            'notes' => 'Backfilled from the property quote-subdomain target.',
        ],
        'overrides' => [
            'properties' => [
                'vehicle-net-au' => [
                    'journey_type' => 'vehicle_quote',
                    'runtime_label' => 'Moveroo Cars 2026',
                    'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo-Cars-2026',
                    'notes' => 'Legacy vehicle quoting surface attached to Moveroo Cars 2026. Phase-out is in progress, so keep visibility high but do not expand or normalize this surface onto the maintained removals runtime.',
                    'replace_notes' => true,
                ],
            ],
            'hostnames' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Published Brand Surfaces
    |--------------------------------------------------------------------------
    |
    | Versioned, read-only runtime surface payloads for MoverooCombined.
    | Keep this pilot allowlist narrow until Bossman records a wider rollout.
    |
    */
    'published_brand_surfaces' => [
        'pilot_host_allowlist' => [
            'quotes.moveroo.com.au',
            'mymoveportal.discountbackloading.com.au',
            'quotes.interstate-removals.com.au',
            'quoting.movingcars.com.au',
            'portal.supercheapcartransport.com.au',
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
        ],
        'hostnames' => [
            'quotes.moveroo.com.au' => [
                'surface_slug' => 'moveroo-quotes-household-v1',
                'surface_type' => 'quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'moveroo.com.au',
                'brand' => [
                    'display_name' => 'Moveroo',
                    'brand_key' => 'moveroo',
                    'tagline' => 'Move with confidence',
                    'mark_text' => 'M',
                ],
                'copy' => [
                    'eyebrow' => 'Moveroo Household Quotes',
                    'headline' => 'Get your moving quote',
                    'subheading' => 'Tell us about your move and we will prepare the next step.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Moveroo helps Australians plan household moves with clearer quote and booking paths.',
                ],
                'theme' => [
                    'theme_key' => 'moveroo',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#ffffff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#f8fafc',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => '/quote/household',
                    'household_quote_url' => '/quote/household',
                    'booking_url' => '/booking/create',
                    'contact_url' => '/contact',
                    'customer_portal_url' => '/customer/login',
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T00:00:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#208',
                    'source_marketing_url' => 'https://moveroo.com.au',
                ],
            ],
            'mymoveportal.discountbackloading.com.au' => [
                'surface_slug' => 'discountbackloading-mymoveportal-quote-v1',
                'surface_type' => 'quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'discountbackloading.com.au',
                'brand' => [
                    'display_name' => 'Discount Backloading',
                    'brand_key' => 'discountbackloading',
                    'tagline' => 'Backloading and moving quotes',
                    'mark_text' => 'DB',
                ],
                'copy' => [
                    'eyebrow' => 'Discount Backloading Quotes',
                    'headline' => 'Get your Discount Backloading quote',
                    'subheading' => 'Use the current Discount Backloading quote portal for household and vehicle quote paths.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Discount Backloading quote paths are published from Domain Monitor target-link truth.',
                ],
                'theme' => [
                    'theme_key' => 'discountbackloading',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#f97316',
                        'accent_strong' => '#c2410c',
                        'background' => '#fff7ed',
                        'text' => '#1f1308',
                        'muted_text' => '#6b4a2f',
                        'surface' => '#ffffff',
                        'border' => '#fed7aa',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://mymoveportal.discountbackloading.com.au/quote/household',
                    'household_quote_url' => 'https://mymoveportal.discountbackloading.com.au/quote/household',
                    'vehicle_quote_url' => 'https://mymoveportal.discountbackloading.com.au/quote/vehicle',
                    'booking_url' => 'https://mymoveportal.discountbackloading.com.au/booking/create',
                    'contact_url' => 'https://mymoveportal.discountbackloading.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T16:53:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#213',
                    'source_marketing_url' => 'https://discountbackloading.com.au',
                ],
            ],
            'quotes.interstate-removals.com.au' => [
                'property_slug' => 'interstate-removals-com-au',
                'surface_slug' => 'interstate-removals-quotes-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'interstate-removals.com.au',
                'brand' => [
                    'display_name' => 'Interstate Removals',
                    'brand_key' => 'interstate-removals',
                    'tagline' => 'Interstate moving quotes',
                    'mark_text' => 'IR',
                ],
                'copy' => [
                    'eyebrow' => 'Interstate Removals Quotes',
                    'headline' => 'Get your interstate removals quote',
                    'subheading' => 'Use the current Interstate Removals quote host for household and vehicle quote paths.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Interstate Removals quote paths are published as a controlled second-pilot surface.',
                ],
                'theme' => [
                    'theme_key' => 'interstate-removals',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#bfdbfe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://quotes.interstate-removals.com.au/quote/household',
                    'household_quote_url' => 'https://quotes.interstate-removals.com.au/quote/household',
                    'vehicle_quote_url' => 'https://quotes.interstate-removals.com.au/quote/vehicle',
                    'booking_url' => 'https://quotes.interstate-removals.com.au/booking/create',
                    'contact_url' => 'https://quotes.interstate-removals.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:23:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#215',
                    'source_marketing_url' => 'https://interstate-removals.com.au',
                ],
            ],
            'quoting.movingcars.com.au' => [
                'property_slug' => 'movingcars-com-au',
                'surface_slug' => 'movingcars-quoting-vehicle-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'movingcars.com.au',
                'brand' => [
                    'display_name' => 'Moving Cars',
                    'brand_key' => 'movingcars',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'MC',
                ],
                'copy' => [
                    'eyebrow' => 'Moving Cars Quotes',
                    'headline' => 'Get your Moving Cars transport quote',
                    'subheading' => 'Use the current Moving Cars quoting host for vehicle transport enquiries.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Moving Cars is published as a controlled second-pilot vehicle quote surface.',
                ],
                'theme' => [
                    'theme_key' => 'movingcars',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#475569',
                        'surface' => '#ffffff',
                        'border' => '#a5f3fc',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.movingcars.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.movingcars.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.movingcars.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:23:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#215',
                    'source_marketing_url' => 'https://movingcars.com.au',
                ],
            ],
            'portal.supercheapcartransport.com.au' => [
                'property_slug' => 'supercheapcartransport-com-au',
                'surface_slug' => 'supercheapcartransport-portal-vehicle-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'supercheapcartransport.com.au',
                'brand' => [
                    'display_name' => 'Super Cheap Car Transport',
                    'brand_key' => 'supercheapcartransport',
                    'tagline' => 'Car transport quotes',
                    'mark_text' => 'SC',
                ],
                'copy' => [
                    'eyebrow' => 'Super Cheap Car Transport Quotes',
                    'headline' => 'Get your car transport quote',
                    'subheading' => 'Use the current Super Cheap Car Transport portal for vehicle transport enquiries.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Super Cheap Car Transport is published as a controlled second-pilot vehicle quote surface.',
                ],
                'theme' => [
                    'theme_key' => 'supercheapcartransport',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#dc2626',
                        'accent_strong' => '#991b1b',
                        'background' => '#fff1f2',
                        'text' => '#1f1111',
                        'muted_text' => '#57534e',
                        'surface' => '#ffffff',
                        'border' => '#fecdd3',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://portal.supercheapcartransport.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://portal.supercheapcartransport.com.au/quote/vehicle',
                    'contact_url' => 'https://portal.supercheapcartransport.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:23:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#215',
                    'source_marketing_url' => 'https://supercheapcartransport.com.au',
                ],
            ],
            'mymovehub.backloading-services.com.au' => [
                'property_slug' => 'backloading-services-com-au',
                'surface_slug' => 'backloading-services-mymovehub-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'backloading-services.com.au',
                'brand' => [
                    'display_name' => 'Backloading Services',
                    'brand_key' => 'backloading-services',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'BS',
                ],
                'copy' => [
                    'eyebrow' => 'Backloading Services Quotes',
                    'headline' => 'Get your Backloading Services quote',
                    'subheading' => 'Use the current Backloading Services app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Backloading Services is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'backloading-services',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#f97316',
                        'accent_strong' => '#c2410c',
                        'background' => '#fff7ed',
                        'text' => '#1f1308',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://mymovehub.backloading-services.com.au/quote/household',
                    'household_quote_url' => 'https://mymovehub.backloading-services.com.au/quote/household',
                    'vehicle_quote_url' => 'https://mymovehub.backloading-services.com.au/quote/vehicle',
                    'booking_url' => 'https://mymovehub.backloading-services.com.au/booking/create',
                    'contact_url' => 'https://mymovehub.backloading-services.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://backloading-services.com.au',
                ],
            ],
            'mymovehub.backloadingremovals.com.au' => [
                'property_slug' => 'backloadingremovals-com-au',
                'surface_slug' => 'backloadingremovals-mymovehub-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'backloadingremovals.com.au',
                'brand' => [
                    'display_name' => 'Backloading Removals',
                    'brand_key' => 'backloadingremovals',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'BR',
                ],
                'copy' => [
                    'eyebrow' => 'Backloading Removals Quotes',
                    'headline' => 'Get your Backloading Removals quote',
                    'subheading' => 'Use the current Backloading Removals app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Backloading Removals is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'backloadingremovals',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#f97316',
                        'accent_strong' => '#c2410c',
                        'background' => '#fff7ed',
                        'text' => '#1f1308',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://mymovehub.backloadingremovals.com.au/quote/household',
                    'household_quote_url' => 'https://mymovehub.backloadingremovals.com.au/quote/household',
                    'vehicle_quote_url' => 'https://mymovehub.backloadingremovals.com.au/quote/vehicle',
                    'booking_url' => 'https://mymovehub.backloadingremovals.com.au/booking/create',
                    'contact_url' => 'https://mymovehub.backloadingremovals.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://backloadingremovals.com.au',
                ],
            ],
            'portal.movemycar.com.au' => [
                'property_slug' => 'movemycar-com-au',
                'surface_slug' => 'movemycar-portal-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'movemycar.com.au',
                'brand' => [
                    'display_name' => 'Move My Car',
                    'brand_key' => 'movemycar',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'MM',
                ],
                'copy' => [
                    'eyebrow' => 'Move My Car Quotes',
                    'headline' => 'Get your Move My Car transport quote',
                    'subheading' => 'Use the current Move My Car app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Move My Car is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'movemycar',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://portal.movemycar.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://portal.movemycar.com.au/quote/vehicle',
                    'contact_url' => 'https://portal.movemycar.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://movemycar.com.au',
                ],
            ],
            'quotes.wemove.com.au' => [
                'property_slug' => 'wemove-com-au',
                'surface_slug' => 'wemove-quotes-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'wemove.com.au',
                'brand' => [
                    'display_name' => 'We Move',
                    'brand_key' => 'wemove',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'WM',
                ],
                'copy' => [
                    'eyebrow' => 'We Move Quotes',
                    'headline' => 'Get your We Move quote',
                    'subheading' => 'Use the current We Move app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'We Move is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'wemove',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://quotes.wemove.com.au/quote/household',
                    'household_quote_url' => 'https://quotes.wemove.com.au/quote/household',
                    'vehicle_quote_url' => 'https://quotes.wemove.com.au/quote/vehicle',
                    'booking_url' => 'https://quotes.wemove.com.au/booking/create',
                    'contact_url' => 'https://quotes.wemove.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://wemove.com.au',
                ],
            ],
            'quoting.backloading-au.com.au' => [
                'property_slug' => 'backloading-au-com-au',
                'surface_slug' => 'backloading-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'backloading-au.com.au',
                'brand' => [
                    'display_name' => 'Backloading Australia',
                    'brand_key' => 'backloading-au',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'BA',
                ],
                'copy' => [
                    'eyebrow' => 'Backloading Australia Quotes',
                    'headline' => 'Get your Backloading Australia quote',
                    'subheading' => 'Use the current Backloading Australia app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Backloading Australia is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'backloading-au',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#f97316',
                        'accent_strong' => '#c2410c',
                        'background' => '#fff7ed',
                        'text' => '#1f1308',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://quoting.backloading-au.com.au/quote/household',
                    'household_quote_url' => 'https://quoting.backloading-au.com.au/quote/household',
                    'vehicle_quote_url' => 'https://quoting.backloading-au.com.au/quote/vehicle',
                    'booking_url' => 'https://quoting.backloading-au.com.au/booking/create',
                    'contact_url' => 'https://quoting.backloading-au.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://backloading-au.com.au',
                ],
            ],
            'quoting.cartransport.au' => [
                'property_slug' => 'cartransport-au',
                'surface_slug' => 'cartransport-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'cartransport.au',
                'brand' => [
                    'display_name' => 'cartransport.au',
                    'brand_key' => 'cartransport-au',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'CT',
                ],
                'copy' => [
                    'eyebrow' => 'cartransport.au Quotes',
                    'headline' => 'Get your car transport quote',
                    'subheading' => 'Use the current cartransport.au app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'cartransport.au is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'cartransport-au',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.cartransport.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.cartransport.au/quote/vehicle',
                    'contact_url' => 'https://quoting.cartransport.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://cartransport.au',
                ],
            ],
            'quoting.cartransportaus.com.au' => [
                'property_slug' => 'cartransportaus-com-au',
                'surface_slug' => 'cartransportaus-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'cartransportaus.com.au',
                'brand' => [
                    'display_name' => 'Car Transport Aus',
                    'brand_key' => 'cartransportaus',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'CA',
                ],
                'copy' => [
                    'eyebrow' => 'Car Transport Aus Quotes',
                    'headline' => 'Get your Car Transport Aus quote',
                    'subheading' => 'Use the current Car Transport Aus app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Car Transport Aus is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'cartransportaus',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.cartransportaus.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.cartransportaus.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.cartransportaus.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://cartransportaus.com.au',
                ],
            ],
            'quoting.cartransportwithpersonalitems.com.au' => [
                'property_slug' => 'cartransportwithpersonalitems-com-au',
                'surface_slug' => 'cartransportwithpersonalitems-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'cartransportwithpersonalitems.com.au',
                'brand' => [
                    'display_name' => 'Car Transport With Personal Items',
                    'brand_key' => 'cartransportwithpersonalitems',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'CP',
                ],
                'copy' => [
                    'eyebrow' => 'Car Transport Personal Items Quotes',
                    'headline' => 'Get your car transport quote',
                    'subheading' => 'Use the current Car Transport With Personal Items app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Car Transport With Personal Items is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'cartransportwithpersonalitems',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.cartransportwithpersonalitems.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.cartransportwithpersonalitems.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.cartransportwithpersonalitems.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://cartransportwithpersonalitems.com.au',
                ],
            ],
            'quoting.interstate-car-transport.com.au' => [
                'property_slug' => 'interstate-car-transport-com-au',
                'surface_slug' => 'interstate-car-transport-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'interstate-car-transport.com.au',
                'brand' => [
                    'display_name' => 'Interstate Car Transport',
                    'brand_key' => 'interstate-car-transport',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'IC',
                ],
                'copy' => [
                    'eyebrow' => 'Interstate Car Transport Quotes',
                    'headline' => 'Get your interstate car transport quote',
                    'subheading' => 'Use the current Interstate Car Transport app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Interstate Car Transport is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'interstate-car-transport',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.interstate-car-transport.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.interstate-car-transport.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.interstate-car-transport.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://interstate-car-transport.com.au',
                ],
            ],
            'quoting.interstatecarcarriers.com.au' => [
                'property_slug' => 'interstatecarcarriers-com-au',
                'surface_slug' => 'interstatecarcarriers-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'interstatecarcarriers.com.au',
                'brand' => [
                    'display_name' => 'Interstate Car Carriers',
                    'brand_key' => 'interstatecarcarriers',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'ICC',
                ],
                'copy' => [
                    'eyebrow' => 'Interstate Car Carriers Quotes',
                    'headline' => 'Get your interstate car carrier quote',
                    'subheading' => 'Use the current Interstate Car Carriers app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Interstate Car Carriers is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'interstatecarcarriers',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.interstatecarcarriers.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.interstatecarcarriers.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.interstatecarcarriers.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://interstatecarcarriers.com.au',
                ],
            ],
            'quoting.perthinterstateremovalists.com.au' => [
                'property_slug' => 'perthinterstateremovalists-com-au',
                'surface_slug' => 'perthinterstateremovalists-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'perthinterstateremovalists.com.au',
                'brand' => [
                    'display_name' => 'Perth Interstate Removalists',
                    'brand_key' => 'perthinterstateremovalists',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'PI',
                ],
                'copy' => [
                    'eyebrow' => 'Perth Interstate Removalists Quotes',
                    'headline' => 'Get your Perth interstate removals quote',
                    'subheading' => 'Use the current Perth Interstate Removalists app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Perth Interstate Removalists is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'perthinterstateremovalists',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://quoting.perthinterstateremovalists.com.au/quote/household',
                    'household_quote_url' => 'https://quoting.perthinterstateremovalists.com.au/quote/household',
                    'vehicle_quote_url' => 'https://quoting.perthinterstateremovalists.com.au/quote/vehicle',
                    'booking_url' => 'https://quoting.perthinterstateremovalists.com.au/booking/create',
                    'contact_url' => 'https://quoting.perthinterstateremovalists.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://perthinterstateremovalists.com.au',
                ],
            ],
            'quoting.removalsinterstate.com.au' => [
                'property_slug' => 'removalsinterstate-com-au',
                'surface_slug' => 'removalsinterstate-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'removalsinterstate.com.au',
                'brand' => [
                    'display_name' => 'Removals Interstate',
                    'brand_key' => 'removalsinterstate',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'RI',
                ],
                'copy' => [
                    'eyebrow' => 'Removals Interstate Quotes',
                    'headline' => 'Get your Removals Interstate quote',
                    'subheading' => 'Use the current Removals Interstate app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Removals Interstate is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'removalsinterstate',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://quoting.removalsinterstate.com.au/quote/household',
                    'household_quote_url' => 'https://quoting.removalsinterstate.com.au/quote/household',
                    'vehicle_quote_url' => 'https://quoting.removalsinterstate.com.au/quote/vehicle',
                    'booking_url' => 'https://quoting.removalsinterstate.com.au/booking/create',
                    'contact_url' => 'https://quoting.removalsinterstate.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://removalsinterstate.com.au',
                ],
            ],
            'quoting.transportnondrivablecars.com.au' => [
                'property_slug' => 'transportnondrivablecars-com-au',
                'surface_slug' => 'transportnondrivablecars-quoting-v1',
                'surface_type' => 'quote',
                'journey_type' => 'vehicle_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'transportnondrivablecars.com.au',
                'brand' => [
                    'display_name' => 'Transport Non Drivable Cars',
                    'brand_key' => 'transportnondrivablecars',
                    'tagline' => 'Vehicle transport quotes',
                    'mark_text' => 'TN',
                ],
                'copy' => [
                    'eyebrow' => 'Transport Non Drivable Cars Quotes',
                    'headline' => 'Get your non-drivable car transport quote',
                    'subheading' => 'Use the current Transport Non Drivable Cars app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your vehicle quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Transport Non Drivable Cars is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'transportnondrivablecars',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#0891b2',
                        'accent_strong' => '#0e7490',
                        'background' => '#ecfeff',
                        'text' => '#102a31',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'vehicle.quote',
                    'primary_cta_url' => 'https://quoting.transportnondrivablecars.com.au/quote/vehicle',
                    'vehicle_quote_url' => 'https://quoting.transportnondrivablecars.com.au/quote/vehicle',
                    'contact_url' => 'https://quoting.transportnondrivablecars.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'cars@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://transportnondrivablecars.com.au',
                ],
            ],
            'removalistquotes.movingagain.com.au' => [
                'property_slug' => 'movingagain-com-au',
                'surface_slug' => 'movingagain-removalistquotes-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'movingagain.com.au',
                'brand' => [
                    'display_name' => 'Moving Again',
                    'brand_key' => 'movingagain',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'MA',
                ],
                'copy' => [
                    'eyebrow' => 'Moving Again Removalist Quotes',
                    'headline' => 'Get your Moving Again quote',
                    'subheading' => 'Use the current Moving Again app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Moving Again is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'movingagain',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => false,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://removalistquotes.movingagain.com.au/quote/household',
                    'household_quote_url' => 'https://removalistquotes.movingagain.com.au/quote/household',
                    'booking_url' => 'https://removalistquotes.movingagain.com.au/booking/create',
                    'contact_url' => 'https://removalistquotes.movingagain.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://movingagain.com.au',
                ],
            ],
            'removalists.moveroo.com.au' => [
                'property_slug' => 'moveroo-com-au',
                'surface_slug' => 'moveroo-removalists-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'moveroo.com.au',
                'brand' => [
                    'display_name' => 'Moveroo',
                    'brand_key' => 'moveroo',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'M',
                ],
                'copy' => [
                    'eyebrow' => 'Moveroo Removalist Quotes',
                    'headline' => 'Get your Moveroo removalist quote',
                    'subheading' => 'Use the current Moveroo app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Moveroo is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'moveroo',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => false,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://removalists.moveroo.com.au/quote/household',
                    'household_quote_url' => 'https://removalists.moveroo.com.au/quote/household',
                    'booking_url' => 'https://removalists.moveroo.com.au/booking/create',
                    'contact_url' => 'https://removalists.moveroo.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://moveroo.com.au',
                ],
            ],
            'removalportal.interstate-removals.com.au' => [
                'property_slug' => 'interstate-removals-com-au',
                'surface_slug' => 'interstate-removals-removalportal-v1',
                'surface_type' => 'portal',
                'journey_type' => 'portal',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'interstate-removals.com.au',
                'brand' => [
                    'display_name' => 'Interstate Removals',
                    'brand_key' => 'interstate-removals',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'IR',
                ],
                'copy' => [
                    'eyebrow' => 'Interstate Removals Portal',
                    'headline' => 'Open the Interstate Removals portal',
                    'subheading' => 'Use the current Interstate Removals app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Open portal',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Interstate Removals is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'interstate-removals',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => false,
                    'show_vehicle_quote_link' => false,
                    'show_booking_link' => false,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => true,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'customer.portal',
                    'primary_cta_url' => 'https://removalportal.interstate-removals.com.au/contact',
                    'contact_url' => 'https://removalportal.interstate-removals.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://interstate-removals.com.au',
                ],
            ],
            'removalquotes.backloading-services.com.au' => [
                'property_slug' => 'backloading-services-com-au',
                'surface_slug' => 'backloading-services-removalquotes-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'backloading-services.com.au',
                'brand' => [
                    'display_name' => 'Backloading Services',
                    'brand_key' => 'backloading-services',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'BS',
                ],
                'copy' => [
                    'eyebrow' => 'Backloading Services Quote Host',
                    'headline' => 'Get your Backloading Services quote',
                    'subheading' => 'Use the current Backloading Services app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Backloading Services is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'backloading-services',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#f97316',
                        'accent_strong' => '#c2410c',
                        'background' => '#fff7ed',
                        'text' => '#1f1308',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://removalquotes.backloading-services.com.au/quote/household',
                    'household_quote_url' => 'https://removalquotes.backloading-services.com.au/quote/household',
                    'vehicle_quote_url' => 'https://removalquotes.backloading-services.com.au/quote/vehicle',
                    'booking_url' => 'https://removalquotes.backloading-services.com.au/booking/create',
                    'contact_url' => 'https://removalquotes.backloading-services.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://backloading-services.com.au',
                ],
            ],
            'moving.allianceremovals.com.au' => [
                'property_slug' => 'allianceremovals-com-au',
                'surface_slug' => 'allianceremovals-moving-v1',
                'surface_type' => 'quote',
                'journey_type' => 'mixed_quote',
                'canonical_role' => 'primary',
                'owning_marketing_domain' => 'allianceremovals.com.au',
                'brand' => [
                    'display_name' => 'Alliance Removals',
                    'brand_key' => 'allianceremovals',
                    'tagline' => 'Moving and backloading quotes',
                    'mark_text' => 'AR',
                ],
                'copy' => [
                    'eyebrow' => 'Alliance Removals Quotes',
                    'headline' => 'Get your Alliance Removals quote',
                    'subheading' => 'Use the current Alliance Removals app-served host for this controlled third-pilot surface.',
                    'primary_cta_label' => 'Start your quote',
                    'secondary_cta_label' => 'Contact us',
                    'footer_blurb' => 'Alliance Removals is published as a controlled third-pilot brand surface.',
                ],
                'theme' => [
                    'theme_key' => 'allianceremovals',
                    'mode' => 'auto',
                    'fonts' => [
                        'body_family' => 'Inter',
                        'heading_family' => 'Inter',
                    ],
                    'colors' => [
                        'accent' => '#2563eb',
                        'accent_strong' => '#1d4ed8',
                        'background' => '#eff6ff',
                        'text' => '#111827',
                        'muted_text' => '#4b5563',
                        'surface' => '#ffffff',
                        'border' => '#dbeafe',
                    ],
                    'radius_scale' => 'rounded',
                    'shadow_style' => 'soft',
                    'exact_tokens' => [],
                ],
                'navigation' => [
                    'show_household_quote_link' => true,
                    'show_vehicle_quote_link' => true,
                    'show_booking_link' => true,
                    'show_contact_link' => true,
                    'show_customer_portal_link' => false,
                    'show_customer_portal_in_header' => false,
                    'show_provider_login_link' => false,
                    'show_admin_link' => false,
                ],
                'links' => [
                    'primary_cta_route' => 'household.quote',
                    'primary_cta_url' => 'https://moving.allianceremovals.com.au/quote/household',
                    'household_quote_url' => 'https://moving.allianceremovals.com.au/quote/household',
                    'vehicle_quote_url' => 'https://moving.allianceremovals.com.au/quote/vehicle',
                    'booking_url' => 'https://moving.allianceremovals.com.au/booking/create',
                    'contact_url' => 'https://moving.allianceremovals.com.au/contact',
                    'customer_portal_url' => null,
                ],
                'contact' => [
                    'public_email' => 'removals@moveroo.com.au',
                ],
                'provenance' => [
                    'approved_by' => 'domain-monitor',
                    'approved_at' => '2026-05-19T18:47:00+10:00',
                    'source' => 'domain_monitor',
                    'change_ref' => 'domain-monitor#217',
                    'source_marketing_url' => 'https://allianceremovals.com.au',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runtime Analytics Host Overrides
    |--------------------------------------------------------------------------
    |
    | Explicit runtime hostname classifications for known active hosts that
    | are not yet represented as first-class conversion surfaces or direct
    | property target URLs.
    |
    */
    'runtime_analytics' => [
        'host_overrides' => [
            [
                'hostname' => 'quotes.interstateremovalists.net.au',
                'property_slug' => 'interstateremovalists-net-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'decommissioned_subdomain',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'discountbackloading.moveroo.com.au',
                'property_slug' => 'discountbackloading-com-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'superseded_moveroo_subdomain',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'perth.moveroo.com.au',
                'property_slug' => 'perthinterstateremovalists-com-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'superseded_by_quoting_perthinterstateremovalists',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'backloadingremovals.moveroo.com.au',
                'property_slug' => 'backloadingremovals-com-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'superseded_moveroo_subdomain',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'removalist.backloadingremovals.com.au',
                'property_slug' => 'backloadingremovals-com-au',
                'class' => 'login_customer_provider_app_shell_host',
                'decision' => 'expected_miss',
                'reason' => 'legacy_portal_host_without_runtime_attribution',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'quoting.mover.com.au',
                'property_slug' => 'mover-com-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'superseded_by_quoteandbook_mover_com_au',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'wemove.moveroo.com.au',
                'property_slug' => 'wemove-com-au',
                'class' => 'login_customer_provider_app_shell_host',
                'decision' => 'expected_miss',
                'reason' => 'portal_host_without_runtime_attribution',
                'warning_policy' => 'suppress',
            ],
            [
                'hostname' => 'quotes.interstate-removals.com.au',
                'property_slug' => 'interstate-removals-com-au',
                'class' => 'conversion_host',
                'decision' => 'exported',
                'reason' => 'moveroocombined_conversion_host_override',
                'journey_type' => 'mixed_quote',
                'warning_policy' => 'warn',
            ],
            [
                'hostname' => 'removalquotes.backloading-services.com.au',
                'property_slug' => 'backloading-services-com-au',
                'class' => 'conversion_host',
                'decision' => 'exported',
                'reason' => 'moveroocombined_conversion_host_override',
                'journey_type' => 'mixed_quote',
                'warning_policy' => 'warn',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain Monitor UI Windows
    |--------------------------------------------------------------------------
    |
    | Centralized time windows used by dashboard widgets and list filters.
    | These are intentionally simple so production tuning can happen via env.
    |
    */
    'recent_failures_hours' => (int) env('DOMAIN_MONITOR_RECENT_FAILURES_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Probe logging
    |--------------------------------------------------------------------------
    |
    | Hosting/platform detection often probes dead, parked, or broken domains.
    | Keep the low-level probe noise available in development, but suppress it
    | in production unless explicitly enabled.
    |
    */
    'log_probe_failures' => (bool) env('DOMAIN_MONITOR_LOG_PROBE_FAILURES', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Data retention (pruning)
    |--------------------------------------------------------------------------
    |
    | How long we retain history tables before pruning old records.
    |
    */
    'prune_domain_checks_days' => (int) env('DOMAIN_MONITOR_PRUNE_DOMAIN_CHECKS_DAYS', 14),
    'prune_eligibility_checks_days' => (int) env('DOMAIN_MONITOR_PRUNE_ELIGIBILITY_CHECKS_DAYS', 14),
    'prune_alerts_days' => (int) env('DOMAIN_MONITOR_PRUNE_ALERTS_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Web Property Bootstrap
    |--------------------------------------------------------------------------
    |
    | Conservative bootstrap settings for seeding the web_properties layer
    | from the domain inventory. Start with narrow overrides and let the
    | bootstrap command create a reviewable baseline instead of trying to
    | infer every relationship automatically.
    |
    */
    'web_property_bootstrap' => [
        'websites_root' => env('DOMAIN_MONITOR_WEBSITES_ROOT', '/Users/jasonhill/Projects/Business/websites'),
        'attach_legacy_matomo_sources' => env('DOMAIN_MONITOR_BOOTSTRAP_LEGACY_MATOMO_SOURCES', false),
        'overrides' => [
            'again.com.au' => [
                'slug' => 'again-com-au',
                'name' => 'Again.com.au',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'again-com-au-astro',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/again-com-au-astro',
                    'framework' => 'Astro',
                ],
            ],
            'moveroo.com.au' => [
                'slug' => 'moveroo-com-au',
                'name' => 'Moveroo Website',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'MM-moveroo.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/moveroowebsite.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-moveroo.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'moveroowebsite',
                    'deployment_project_id' => 'prj_ibD57znQpOtny1qcDGdnsyRChj1M',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '6',
                        'external_name' => 'Moveroo website',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'cartransport.au' => [
                'slug' => 'cartransport-au',
                'name' => 'cartransport.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-cartransport.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/moveroo/ws-cartranspor-au.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-cartransport.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'netlify',
                    'deployment_project_id' => '87dc85b0-b392-4f33-a1a0-4e1439052880',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '11',
                        'external_name' => 'cartransport.au',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'cartransportwithpersonalitems.com.au' => [
                'slug' => 'cartransportwithpersonalitems-com-au',
                'name' => 'cartransportwithpersonalitems.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-cartransportwithpersonalitems.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/cartransportwithpersonalitems-astro.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-cartransportwithpersonalitems.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'cartransportwithpersonalitems-astro',
                    'deployment_project_id' => 'prj_zOYvqgoYrBKz4HwU7NZ324sX4eLt',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '8',
                        'external_name' => 'Car Transport Personal Items',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'discountbackloading.com.au' => [
                'slug' => 'discountbackloading-com-au',
                'name' => 'discountbackloading.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-discountbackloading.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/MM-discountbackloading.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-discountbackloading.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'mm-discountbackloading',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '13',
                        'external_name' => 'Discount Backloading',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'backloading-au.com.au' => [
                'slug' => 'backloading-au-com-au',
                'name' => 'backloading-au.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '14',
                        'external_name' => 'Backloading Australia',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'backloadingremovals.com.au' => [
                'slug' => 'backloadingremovals-com-au',
                'name' => 'backloadingremovals.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '15',
                        'external_name' => 'Backloading Removals',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'interstatecarcarriers.com.au' => [
                'slug' => 'interstatecarcarriers-com-au',
                'name' => 'interstatecarcarriers.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-interstatecarcarriers',
                    'repo_url' => 'https://github.com/iamjasonhill/MM-interstatecarcarriers',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-interstatecarcarriers.com.au',
                    'framework' => 'Astro',
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'mm-interstatecarcarriers',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '16',
                        'external_name' => 'Interstate Car Carriers',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'mover.com.au' => [
                'slug' => 'mover-com-au',
                'name' => 'mover.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '17',
                        'external_name' => 'Mover.com.au',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'perthinterstateremovalists.com.au' => [
                'slug' => 'perthinterstateremovalists-com-au',
                'name' => 'perthinterstateremovalists.com.au',
                'property_type' => 'website',
                'target_contact_us_page_url' => 'https://quoting.perthinterstateremovalists.com.au/contact',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '18',
                        'external_name' => 'Perth Interstate Removalists',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'interstate-car-transport.com.au' => [
                'slug' => 'interstate-car-transport-com-au',
                'name' => 'interstate-car-transport.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '19',
                        'external_name' => 'Interstate Car Transport',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'wemove.com.au' => [
                'slug' => 'wemove-com-au',
                'name' => 'wemove.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-wemove.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/MM-wemove.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-wemove.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'mm-wemove',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '21',
                        'external_name' => 'We Move',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'backloading-services.com.au' => [
                'slug' => 'backloading-services-com-au',
                'name' => 'backloading-services.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '23',
                        'external_name' => 'Backloading Services',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'removalist.net' => [
                'slug' => 'removalist-net',
                'name' => 'removalist.net',
                'property_type' => 'app',
                'priority' => 100,
                'notes' => 'Operationally critical removals quoting platform. Apex homepage GA4 is optional because normal quote attribution is handled on branded quote/conversion surfaces.',
                'analytics_monitoring' => [
                    'homepage_ga4_required' => false,
                    'quote_handoff_required' => false,
                    'reason' => 'Operational app shell; users should normally enter through branded marketing sites or quote subdomains.',
                ],
                'repository' => [
                    'repo_name' => 'moveroocombined',
                    'repo_url' => 'https://github.com/iamjasonhill/moveroocombined',
                    'local_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
                    'framework' => 'Laravel',
                ],
            ],
            'vehicle.net.au' => [
                'slug' => 'vehicle-net-au',
                'name' => 'vehicle.net.au',
                'property_type' => 'app',
                'priority' => 95,
                'notes' => 'Legacy vehicle quoting platform attached to the separate Moveroo Cars 2026 runtime. Apex homepage GA4 is optional because normal attribution is handled by vehicle quote/conversion surfaces such as quoting.vehicle.net.au.',
                'analytics_monitoring' => [
                    'homepage_ga4_required' => false,
                    'quote_handoff_required' => false,
                    'reason' => 'Operational app shell; users should normally enter through branded marketing sites or vehicle quote subdomains.',
                ],
                'repository' => [
                    'repo_name' => 'laravel',
                    'repo_url' => 'https://github.com/iamjasonhill/laravel',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/laravel',
                    'framework' => 'Laravel',
                ],
            ],
            'interstate-removals.com.au' => [
                'slug' => 'interstate-removals-com-au',
                'name' => 'interstate-removals.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '22',
                        'external_name' => 'Interstate Removals',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'beauy.com.au' => [
                'slug' => 'beauy-com-au',
                'name' => 'beauy.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '30',
                        'external_name' => 'beauy.com.au',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'removalsinterstate.com.au' => [
                'slug' => 'removalsinterstate-com-au',
                'name' => 'removalsinterstate.com.au',
                'property_type' => 'website',
                'target_household_quote_url' => 'https://quoting.removalsinterstate.com.au/quote/household',
                'target_household_booking_url' => 'https://quoting.removalsinterstate.com.au/booking/create',
                'target_vehicle_quote_url' => 'https://quoting.removalsinterstate.com.au/quote/vehicle',
                'target_moveroo_subdomain_url' => 'https://quoting.removalsinterstate.com.au/',
                'target_contact_us_page_url' => 'https://quoting.removalsinterstate.com.au/contact',
                'target_legacy_bookings_replacement_url' => 'https://quoting.removalsinterstate.com.au/booking/create',
                'target_legacy_payments_replacement_url' => 'https://quoting.removalsinterstate.com.au/contact',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '29',
                        'external_name' => 'Removals Interstate',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'allianceremovals.com.au' => [
                'slug' => 'allianceremovals-com-au',
                'name' => 'allianceremovals.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '24',
                        'external_name' => 'Alliance Removals',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'movemycar.com.au' => [
                'slug' => 'movemycar-com-au',
                'name' => 'movemycar.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '25',
                        'external_name' => 'Move My Car',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'supercheapcartransport.com.au' => [
                'slug' => 'supercheapcartransport-com-au',
                'name' => 'supercheapcartransport.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-supercheapcartransport',
                    'repo_url' => 'https://github.com/iamjasonhill/MM-supercheapcartransport',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-supercheapcartransport',
                    'framework' => 'Astro',
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'mm-supercheapcartransport',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '26',
                        'external_name' => 'Super Cheap Car Transport',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'cartransportaus.com.au' => [
                'slug' => 'cartransportaus-com-au',
                'name' => 'cartransportaus.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/_wp-house',
                    'framework' => 'WordPress',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '27',
                        'external_name' => 'Car Transport Aus',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'movingcars.com.au' => [
                'slug' => 'movingcars-com-au',
                'name' => 'movingcars.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'MM-movingcars.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/astrosites2026.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-movingcars.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'astrosites2026',
                    'deployment_project_id' => 'prj_TLUsNXogLgMC0xStHFmM5XdoFB1M',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '12',
                        'external_name' => 'movingcars.com.au',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],

            ],
            'movingagain.com.au' => [
                'slug' => 'movingagain-com-au',
                'name' => 'Moving Again',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'MM-movingagain.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/WS-Moving-Again.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-movingagain.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'ws-moving-again',
                    'deployment_project_id' => 'prj_d8PcMjvn7HUh4Pq0PnkqkFAOBxfs',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '9',
                        'external_name' => 'Moving Again',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'cartransport.movingagain.com.au' => [
                'slug' => 'cartransport-movingagain-com-au',
                'name' => 'Moving Again Car Transport',
                'property_type' => 'website',
                'platform' => 'Astro',
                'target_platform' => 'Astro',
                'repository' => [
                    'repo_name' => 'MM-cartransport.movingagain.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-cartransport.movingagain.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'ma-catrans-program',
                    'deployment_project_id' => 'prj_wEfisk5vy7yPmMRCUetKaxEMM8UW',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '20',
                        'external_name' => 'Moving Again Car Transport',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'movinginsurance.com.au' => [
                'slug' => 'movinginsurance-com-au',
                'name' => 'Moving Insurance',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'MM-movinginsurance.com.au',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/iamjasonhill/WS-movinginsurance.git',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/MM-movinginsurance.com.au',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
                    'deployment_project_name' => 'ws-movinginsurance',
                    'deployment_project_id' => 'prj_lNASlgeo2N8yQp92KKMXyTXAftsB',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '10',
                        'external_name' => 'Moving Insurance',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
            'transportnondrivablecars.com.au' => [
                'slug' => 'transportnondrivablecars-com-au',
                'name' => 'transportnondrivablecars.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'transportnondrivablecars-com-au-php',
                    'local_path' => '/Users/jasonhill/Projects/Business/websites/transportnondrivablecars-com-au-php',
                    'framework' => 'Custom PHP',
                ],
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '2',
                        'external_name' => 'Non Drivable',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                    ],
                ],
            ],
        ],
    ],

    'search_console_issue_catalog' => [
        'page_with_redirect_in_sitemap' => [
            'label' => 'Page with redirect',
            'labels' => ['Page with redirect'],
            'count_field' => 'pages_with_redirect',
        ],
        'blocked_by_robots_in_indexing' => [
            'label' => 'Blocked by robots.txt',
            'labels' => ['Blocked by robots.txt'],
            'count_field' => 'blocked_by_robots',
        ],
        'duplicate_without_user_selected_canonical' => [
            'label' => 'Duplicate without user-selected canonical',
            'labels' => ['Duplicate without user-selected canonical'],
            'count_field' => 'duplicate_without_user_selected_canonical',
        ],
        'alternate_with_canonical' => [
            'label' => 'Alternative page with proper canonical tag',
            'labels' => ['Alternative page with proper canonical tag'],
            'count_field' => 'alternate_with_canonical',
        ],
        'excluded_by_noindex' => [
            'label' => "Excluded by 'noindex' tag",
            'labels' => ["Excluded by 'noindex' tag", 'Excluded by ‘noindex’ tag'],
            'count_field' => null,
        ],
        'not_found_404' => [
            'label' => 'Not found (404)',
            'labels' => ['Not found (404)'],
            'count_field' => 'not_found_404',
        ],
        'crawled_currently_not_indexed' => [
            'label' => 'Crawled - currently not indexed',
            'labels' => ['Crawled - currently not indexed'],
            'count_field' => 'crawled_currently_not_indexed',
        ],
        'discovered_currently_not_indexed' => [
            'label' => 'Discovered - currently not indexed',
            'labels' => ['Discovered - currently not indexed', 'Discovered – currently not indexed'],
            'count_field' => 'discovered_currently_not_indexed',
        ],
        'google_chose_different_canonical' => [
            'label' => 'Duplicate, Google chose different canonical than user',
            'labels' => ['Duplicate, Google chose different canonical than user'],
            'count_field' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Queue Standards
    |--------------------------------------------------------------------------
    |
    | The dashboard queue should start to describe recurring issue families
    | and which managed platform baselines they belong to. Keep this narrow
    | and operationally useful: enough to identify fleet-standard gaps without
    | pretending every incident is fully auto-classified.
    |
    */
    'priority_queue_standards' => [
        'platform_profiles' => [
            'wordpress_house_managed' => [
                'baseline_surface' => 'shared_wordpress_house_and_live_host_config',
            ],
            'astro_marketing_managed' => [
                'baseline_surface' => 'shared_astro_repo_conventions_and_host_config',
            ],
        ],
        'controls' => [
            'transport.uptime_health' => [
                'issue_families' => ['health.uptime'],
            ],
            'transport.http_health' => [
                'issue_families' => ['health.http'],
            ],
            'transport.tls' => [
                'issue_families' => ['transport.tls'],
            ],
            'dns.health' => [
                'issue_families' => ['dns.health'],
            ],
            'email.security_baseline' => [
                'issue_families' => ['email.security_baseline'],
            ],
            'security.headers_baseline' => [
                'issue_families' => ['security.headers_baseline'],
            ],
            'seo.fundamentals' => [
                'issue_families' => ['seo.fundamentals'],
            ],
            'seo.robots_and_sitemap_consistency' => [
                'issue_families' => [
                    'page_with_redirect_in_sitemap',
                    'blocked_by_robots_in_indexing',
                ],
            ],
            'seo.canonical_consistency' => [
                'issue_families' => [
                    'duplicate_without_user_selected_canonical',
                    'alternate_with_canonical',
                    'google_chose_different_canonical',
                ],
            ],
            'seo.indexation_coverage' => [
                'issue_families' => [
                    'excluded_by_noindex',
                    'not_found_404',
                    'crawled_currently_not_indexed',
                    'discovered_currently_not_indexed',
                ],
            ],
            'seo.broken_links' => [
                'issue_families' => ['seo.broken_links'],
            ],
            'reputation.health' => [
                'issue_families' => ['reputation.health'],
            ],
            'domain.eligibility' => [
                'issue_families' => ['domain.eligibility'],
            ],
            'domain.expiry' => [
                'issue_families' => ['domain.expiry'],
            ],
            'control.website_fleet_coverage' => [
                'issue_families' => ['control.coverage_required'],
                'rollout_scope' => 'domain_only',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Coverage Tags
    |--------------------------------------------------------------------------
    |
    | These tags mark which primary domains belong to the fully managed
    | website fleet, and whether repo/controller, GA4, and Search Console
    | coverage are complete or still have gaps.
    |
    */
    'coverage_tags' => [
        'manual_exclusion_tag' => [
            'name' => 'coverage.excluded',
            'priority' => 95,
            'color' => '#6b7280',
            'description' => 'This domain is intentionally excluded from the managed fleet coverage baseline.',
        ],
        'tags' => [
            'required' => [
                'name' => 'coverage.required',
                'priority' => 90,
                'color' => '#2563eb',
                'description' => 'This domain is in the managed website fleet and should have repository, GA4, and Search Console coverage.',
            ],
            'complete' => [
                'name' => 'coverage.complete',
                'priority' => 80,
                'color' => '#16a34a',
                'description' => 'This managed domain currently has repository, GA4, and Search Console coverage in place.',
            ],
            'gap' => [
                'name' => 'coverage.gap',
                'priority' => 85,
                'color' => '#dc2626',
                'description' => 'This managed domain should be fully covered, but one or more coverage controls still need attention.',
            ],
        ],
        'automation_tags' => [
            'required' => [
                'name' => 'automation.required',
                'priority' => 70,
                'color' => '#7c3aed',
                'description' => 'This managed domain should participate in the full automation checklist.',
            ],
            'complete' => [
                'name' => 'automation.complete',
                'priority' => 60,
                'color' => '#16a34a',
                'description' => 'This managed domain has active automation coverage in place through repository, GA4, Search Console, and baseline sync.',
            ],
            'gap' => [
                'name' => 'automation.gap',
                'priority' => 65,
                'color' => '#dc2626',
                'description' => 'This managed domain still has one or more automation checklist gaps.',
            ],
            'manual_csv_pending' => [
                'name' => 'automation.manual_csv_pending',
                'priority' => 68,
                'color' => '#ca8a04',
                'description' => 'Legacy tag retained only so existing manual CSV backlog tags can be removed from active automation coverage.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Lanes
    |--------------------------------------------------------------------------
    |
    | Explicit monitoring lanes keep fast live incidents separate from slower
    | marketing/readiness audits. The first runnable lane is
    | marketing_integrity verifies live GA4 behavior, quote handoff, and
    | homepage indexability, seo_agent_readiness covers crawl/agent-facing
    | signals, fleet_astro_technical_seo adds a weekly Fleet-scoped Astro
    | baseline, controller_metadata catches stale cutover authority, critical_live
    | handles redirect hygiene on the preferred root host, and deep_audit
    | currently starts with a deduped broken-links pass.
    |
    */
    'monitoring_lanes' => [
        'critical_live' => [
            'cadence' => 'frequent',
            'issue_type' => 'incident',
            'checks' => [
                'uptime',
                'http',
                'ssl',
                'redirect_policy',
            ],
        ],
        'marketing_integrity' => [
            'cadence' => 'daily',
            'issue_type' => 'regression',
            'checks' => [
                'ga4_install',
                'conversion_surface_ga4',
                'indexability',
                'quote_handoff_integrity',
            ],
        ],
        'seo_agent_readiness' => [
            'cadence' => 'daily',
            'issue_type' => 'readiness_gap',
            'checks' => [
                'structured_data',
                'agent_readiness',
            ],
        ],
        'fleet_astro_technical_seo' => [
            'cadence' => 'weekly',
            'issue_type' => 'cleanup',
            'checks' => [
                'indexability',
                'redirect_policy',
            ],
            'scope' => [
                'fleet_focus' => true,
                'execution_surface' => 'astro_repo_controlled',
            ],
        ],
        'controller_metadata' => [
            'cadence' => 'daily',
            'issue_type' => 'cleanup',
            'checks' => [
                'controller_metadata_drift',
            ],
        ],
        'deep_audit' => [
            'cadence' => 'weekly',
            'issue_type' => 'cleanup',
            'checks' => [
                'broken_links',
                'external_links',
            ],
        ],
    ],

    'external_reference_policy' => [
        'policy_standard' => [
            'owner' => 'Fleet',
            'status' => 'pending_fleet_standard',
            'fleet_issue' => 'https://github.com/iamjasonhill/MM-fleet-program/issues/36',
            'control_policy_issue' => 'https://github.com/iamjasonhill/MM-Control-Plane/issues/67',
        ],
        'authority_reference_hosts' => [
            'abs.gov.au',
            'ato.gov.au',
            'business.gov.au',
        ],
        'approved_scoped_hosts' => [],
        'approved_registry_hosts' => [
            [
                'host' => 'movinginsurance.com.au',
                'category' => 'approved_fleet_reference',
                'reason' => 'Moving Insurance is an accepted fleet-adjacent moving insurance reference.',
                'registry_source' => 'fleet_reviewed',
                'scope' => 'fleet',
            ],
            [
                'host' => 'selfstorage.com.au',
                'category' => 'approved_storage_reference',
                'reason' => 'Self Storage is an accepted self-storage reference destination.',
                'registry_source' => 'fleet_reviewed',
                'scope' => 'fleet',
            ],
            [
                'host' => 'agriculture.gov.au',
                'category' => 'approved_government_reference',
                'reason' => 'Official Australian Government biosecurity and interstate travel guidance.',
                'registry_source' => 'fleet_reviewed',
                'scope' => 'interstate_quarantine_biosecurity',
            ],
        ],
        'approved_partner_hosts' => [],
        'disallowed_hosts' => [
            'example-spam.test',
        ],
    ],
];
