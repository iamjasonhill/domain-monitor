<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MatomoLegacyRefreshScheduleTest extends TestCase
{
    public function test_legacy_matomo_refresh_jobs_are_not_scheduled_by_default(): void
    {
        $exitCode = Artisan::call('schedule:list', [
            '--no-ansi' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('analytics:refresh-matomo-install-audits', $output);
        $this->assertStringNotContainsString('analytics:refresh-weekly-search-console-baselines', $output);
        $this->assertStringContainsString('analytics:refresh-automation-coverage', $output);
    }
}
