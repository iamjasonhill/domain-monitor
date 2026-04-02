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
        'websites_root' => env('DOMAIN_MONITOR_WEBSITES_ROOT', '/Users/jasonhill/Projects/websites'),
        'overrides' => [
            'again.com.au' => [
                'slug' => 'again-com-au',
                'name' => 'Again.com.au',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'again-com-au-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/again-com-au-astro',
                    'framework' => 'Astro',
                ],
            ],
            'moveroo.com.au' => [
                'slug' => 'moveroo-website',
                'name' => 'Moveroo Website',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'moveroo-website-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/moveroo-website-astro',
                    'framework' => 'Astro',
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
                    'repo_name' => 'cartransport-au-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/cartransport-au-astro',
                    'framework' => 'Astro',
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
                    'repo_name' => 'cartransportwithpersonalitems-com-au-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/cartransportwithpersonalitems-com-au-astro',
                    'framework' => 'Astro',
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
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
                    'framework' => 'WordPress',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
                    'framework' => 'WordPress',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
                    'framework' => 'WordPress',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                'property_type' => 'website',
                'priority' => 100,
                'notes' => 'Operationally critical removals quoting platform. Keep alerts, analytics, and infrastructure issues highly visible.',
            ],
            'vehicle.net.au' => [
                'slug' => 'vehicle-net-au',
                'name' => 'vehicle.net.au',
                'property_type' => 'website',
                'priority' => 95,
                'notes' => 'Operationally critical vehicle quoting platform. Treat health and alerting issues as high priority.',
            ],
            'interstate-removals.com.au' => [
                'slug' => 'interstate-removals-com-au',
                'name' => 'interstate-removals.com.au',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                'repository' => [
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'repo_name' => '_wp-house',
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
                    'framework' => 'WordPress',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
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
                    'repo_name' => 'movingcars-com-au-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/movingcars-com-au-astro',
                    'framework' => 'Astro',
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
                'slug' => 'moving-again',
                'name' => 'Moving Again',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'moving-again-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/moving-again-astro',
                    'framework' => 'Astro',
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
                'slug' => 'ma-car-transport',
                'name' => 'Moving Again Car Transport',
                'property_type' => 'website',
                'repository' => [
                    'repo_name' => 'moveroo/ma-catrans-program',
                    'repo_provider' => 'github',
                    'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
                    'local_path' => '/Users/jasonhill/Projects/websites/ma-car-transport-astro',
                    'framework' => 'Astro',
                    'is_controller' => true,
                    'deployment_provider' => 'vercel',
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
                'slug' => 'moving-insurance',
                'name' => 'Moving Insurance',
                'property_type' => 'marketing_site',
                'repository' => [
                    'repo_name' => 'moving-insurance-astro',
                    'local_path' => '/Users/jasonhill/Projects/websites/moving-insurance-astro',
                    'framework' => 'Astro',
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
                    'local_path' => '/Users/jasonhill/Projects/websites/transportnondrivablecars-com-au-php',
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
    | website fleet, and whether repo/controller, Matomo, and Search Console
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
                'description' => 'This domain is in the managed website fleet and should have repository, Matomo, and Search Console coverage.',
            ],
            'complete' => [
                'name' => 'coverage.complete',
                'priority' => 80,
                'color' => '#16a34a',
                'description' => 'This managed domain currently has repository, Matomo, and Search Console coverage in place.',
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
                'description' => 'This managed domain has automation coverage in place, including manual CSV evidence where required.',
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
                'description' => 'Automation is in place, but manual Search Console CSV evidence is still pending for this domain.',
            ],
        ],
    ],
];
