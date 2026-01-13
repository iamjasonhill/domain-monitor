<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;

class DomainMonitorSettings
{
    private const string RECENT_FAILURES_HOURS_KEY = 'domain_monitor.recent_failures_hours';

    private const string PRUNE_DOMAIN_CHECKS_DAYS_KEY = 'domain_monitor.prune_domain_checks_days';

    private const string PRUNE_ELIGIBILITY_CHECKS_DAYS_KEY = 'domain_monitor.prune_eligibility_checks_days';

    private const string PRUNE_ALERTS_DAYS_KEY = 'domain_monitor.prune_alerts_days';

    private const int MIN_RECENT_FAILURES_HOURS = 1;

    private const int MAX_RECENT_FAILURES_HOURS = 168;

    private const int MIN_PRUNE_DAYS = 1;

    private const int MAX_PRUNE_DAYS = 3650;

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

    public function pruneDomainChecksDays(): int
    {
        return Cache::rememberForever(self::PRUNE_DOMAIN_CHECKS_DAYS_KEY, function (): int {
            $default = (int) config('domain_monitor.prune_domain_checks_days', 90);

            $raw = AppSetting::query()
                ->where('key', self::PRUNE_DOMAIN_CHECKS_DAYS_KEY)
                ->value('value');

            if ($raw === null || $raw === '') {
                return $default;
            }

            $days = (int) $raw;
            if ($days < self::MIN_PRUNE_DAYS || $days > self::MAX_PRUNE_DAYS) {
                return $default;
            }

            return $days;
        });
    }

    public function setPruneDomainChecksDays(int $days): void
    {
        $days = max(self::MIN_PRUNE_DAYS, min(self::MAX_PRUNE_DAYS, $days));

        AppSetting::query()->updateOrCreate(
            ['key' => self::PRUNE_DOMAIN_CHECKS_DAYS_KEY],
            ['value' => (string) $days]
        );

        Cache::forget(self::PRUNE_DOMAIN_CHECKS_DAYS_KEY);
    }

    public function pruneEligibilityChecksDays(): int
    {
        return Cache::rememberForever(self::PRUNE_ELIGIBILITY_CHECKS_DAYS_KEY, function (): int {
            $default = (int) config('domain_monitor.prune_eligibility_checks_days', 180);

            $raw = AppSetting::query()
                ->where('key', self::PRUNE_ELIGIBILITY_CHECKS_DAYS_KEY)
                ->value('value');

            if ($raw === null || $raw === '') {
                return $default;
            }

            $days = (int) $raw;
            if ($days < self::MIN_PRUNE_DAYS || $days > self::MAX_PRUNE_DAYS) {
                return $default;
            }

            return $days;
        });
    }

    public function setPruneEligibilityChecksDays(int $days): void
    {
        $days = max(self::MIN_PRUNE_DAYS, min(self::MAX_PRUNE_DAYS, $days));

        AppSetting::query()->updateOrCreate(
            ['key' => self::PRUNE_ELIGIBILITY_CHECKS_DAYS_KEY],
            ['value' => (string) $days]
        );

        Cache::forget(self::PRUNE_ELIGIBILITY_CHECKS_DAYS_KEY);
    }

    public function pruneAlertsDays(): int
    {
        return Cache::rememberForever(self::PRUNE_ALERTS_DAYS_KEY, function (): int {
            $default = (int) config('domain_monitor.prune_alerts_days', 14);

            $raw = AppSetting::query()
                ->where('key', self::PRUNE_ALERTS_DAYS_KEY)
                ->value('value');

            if ($raw === null || $raw === '') {
                return $default;
            }

            $days = (int) $raw;
            if ($days < self::MIN_PRUNE_DAYS || $days > self::MAX_PRUNE_DAYS) {
                return $default;
            }

            return $days;
        });
    }

    public function setPruneAlertsDays(int $days): void
    {
        $days = max(self::MIN_PRUNE_DAYS, min(self::MAX_PRUNE_DAYS, $days));

        AppSetting::query()->updateOrCreate(
            ['key' => self::PRUNE_ALERTS_DAYS_KEY],
            ['value' => (string) $days]
        );

        Cache::forget(self::PRUNE_ALERTS_DAYS_KEY);
    }
}
