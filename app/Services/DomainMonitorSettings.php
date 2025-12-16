<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class DomainMonitorSettings
{
    private const string RECENT_FAILURES_HOURS_KEY = 'domain_monitor.recent_failures_hours';

    private const int MIN_RECENT_FAILURES_HOURS = 1;

    private const int MAX_RECENT_FAILURES_HOURS = 168;

    public function recentFailuresHours(): int
    {
        return Cache::rememberForever(self::RECENT_FAILURES_HOURS_KEY, function (): int {
            $default = (int) config('domain_monitor.recent_failures_hours', 24);

            $raw = AppSetting::query()
                ->where('key', self::RECENT_FAILURES_HOURS_KEY)
                ->value('value');

            if ($raw === null || $raw === '') {
                return $default;
            }

            $hours = (int) $raw;
            if ($hours < self::MIN_RECENT_FAILURES_HOURS || $hours > self::MAX_RECENT_FAILURES_HOURS) {
                return $default;
            }

            return $hours;
        });
    }

    public function setRecentFailuresHours(int $hours): void
    {
        $hours = max(self::MIN_RECENT_FAILURES_HOURS, min(self::MAX_RECENT_FAILURES_HOURS, $hours));

        AppSetting::query()->updateOrCreate(
            ['key' => self::RECENT_FAILURES_HOURS_KEY],
            ['value' => (string) $hours]
        );

        Cache::forget(self::RECENT_FAILURES_HOURS_KEY);
    }
}
