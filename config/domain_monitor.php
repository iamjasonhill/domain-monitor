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
    | Data retention (pruning)
    |--------------------------------------------------------------------------
    |
    | How long we retain history tables before pruning old records.
    |
    */
    'prune_domain_checks_days' => (int) env('DOMAIN_MONITOR_PRUNE_DOMAIN_CHECKS_DAYS', 14),
    'prune_eligibility_checks_days' => (int) env('DOMAIN_MONITOR_PRUNE_ELIGIBILITY_CHECKS_DAYS', 14),
    'prune_alerts_days' => (int) env('DOMAIN_MONITOR_PRUNE_ALERTS_DAYS', 14),
];
