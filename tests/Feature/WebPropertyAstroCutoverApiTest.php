<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebPropertyAstroCutoverApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_fleet_can_record_astro_cutover_and_trigger_immediate_baseline_refresh(): void
    {
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-website',
            'name' => 'WeMove Website',
            'status' => 'active',
            'platform' => 'WordPress',
            'target_platform' => 'Astro',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '42',
            'external_name' => 'WeMove',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $cutoverAt = Carbon::parse('2026-04-16T10:30:00+10:00');

        Artisan::shouldReceive('call')
            ->once()
            ->with('analytics:sync-search-console-baseline', \Mockery::on(function (array $arguments) use ($cutoverAt): bool {
                return $arguments['--domain'] === 'wemove.com.au'
                    && $arguments['--baseline-type'] === 'astro_cutover'
                    && $arguments['--captured-by'] === 'fleet'
                    && str_contains((string) $arguments['--notes'], 'Astro cutover checkpoint recorded for wemove-website')
                    && str_contains((string) $arguments['--notes'], $cutoverAt->toIso8601String());
            }))
            ->andReturn(0);

        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('Synced Search Console baseline for wemove.com.au.');

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/wemove-website/astro-cutover', [
            'astro_cutover_at' => $cutoverAt->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('property_slug', 'wemove-website')
            ->assertJsonPath('astro_cutover.recorded_at', $cutoverAt->toIso8601String())
            ->assertJsonPath('astro_cutover.baseline_refresh_requested', true)
            ->assertJsonPath('astro_cutover.baseline_refresh.status', 'synced')
            ->assertJsonPath('astro_cutover.baseline_refresh.baseline_type', 'astro_cutover')
            ->assertJsonPath('data.platform_migration.current_platform', 'WordPress')
            ->assertJsonPath('data.platform_migration.target_platform', 'Astro')
            ->assertJsonPath('data.platform_migration.astro_cutover_at', $cutoverAt->toIso8601String());

        $this->assertSame(
            $cutoverAt->toIso8601String(),
            $property->fresh()->astro_cutover_at?->toIso8601String()
        );
    }

    public function test_cutover_is_still_recorded_when_baseline_refresh_is_skipped(): void
    {
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $domain = Domain::factory()->create([
            'domain' => 'skip-baseline.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'skip-baseline-site',
            'name' => 'Skip Baseline Site',
            'status' => 'active',
            'platform' => 'WordPress',
            'target_platform' => 'Astro',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        Artisan::shouldReceive('call')->never();
        Artisan::shouldReceive('output')->never();

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/skip-baseline-site/astro-cutover', [
            'astro_cutover_at' => '2026-04-16T09:15:00Z',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('astro_cutover.baseline_refresh.status', 'skipped')
            ->assertJsonPath('astro_cutover.baseline_refresh.message', 'Property does not have a Matomo analytics binding yet.')
            ->assertJsonPath('data.platform_migration.astro_cutover_at', '2026-04-16T09:15:00+10:00');

        $this->assertNotNull($property->fresh()->astro_cutover_at);
    }
}
