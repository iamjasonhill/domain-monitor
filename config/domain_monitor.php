<?php

return [
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
                    ],
                ],
            ],
            'discountbackloading.com.au' => [
                'slug' => 'discountbackloading-com-au',
                'name' => 'discountbackloading.com.au',
                'property_type' => 'website',
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '13',
                        'external_name' => 'Discount Backloading',
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
                        'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
                    ],
                ],
            ],
        ],
    ],
];
