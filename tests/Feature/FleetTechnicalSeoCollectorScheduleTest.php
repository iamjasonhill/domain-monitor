<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class FleetTechnicalSeoCollectorScheduleTest extends TestCase
{
    public function test_fleet_technical_seo_collectors_are_scheduled_as_evidence_only_batches(): void
    {
        $exitCode = Artisan::call('schedule:list', [
            '--no-ansi' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'monitoring:run-fleet-technical-seo-estate-audit --profile=fleet_technical_seo_smoke --limit=5 --continue-on-failure',
            $output
        );
        $this->assertStringContainsString(
            'monitoring:run-fleet-technical-seo-estate-audit --profile=fleet_technical_seo_deep --limit=5 --continue-on-failure',
            $output
        );
        $this->assertStringNotContainsString('--promote-findings', $output);
    }
}
