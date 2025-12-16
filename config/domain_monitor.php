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
];
