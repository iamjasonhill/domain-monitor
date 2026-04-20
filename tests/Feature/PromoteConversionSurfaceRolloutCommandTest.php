<?php

namespace Tests\Feature;

use App\Models\AnalyticsEventContract;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use App\Models\WebPropertyEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PromoteConversionSurfaceRolloutCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_conversion_surfaces_and_primary_event_contract_assignments(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-com-au',
            'name' => 'moveroo.com.au',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-9F3Y80LEQL',
            'external_name' => 'Moveroo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $contract = AnalyticsEventContract::create([
            'key' => 'moveroo-full-funnel-v1',
            'name' => 'Moveroo Full Funnel',
            'version' => 'v1',
            'contract_type' => 'ga4_web_and_backend',
            'status' => 'active',
        ]);

        $assignment = WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $contract->id,
            'is_primary' => true,
            'rollout_status' => 'defined',
            'notes' => 'Initial contract backfill.',
        ]);

        $surface = WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'hostname' => 'quotes.moveroo.com.au',
            'surface_type' => 'quote_subdomain',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'defined',
        ]);

        $exitCode = Artisan::call('conversion-surfaces:promote-rollout', [
            '--runtime-path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            '--surface-status' => 'instrumented',
            '--event-contract-status' => 'instrumented',
            '--evidence-source' => 'codebase',
            '--notes' => 'Shared quote runtime includes analytics placeholders and queued quote submission dispatch.',
            '--evidence-file' => [
                '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026/resources/views/layouts/guest.blade.php',
                '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026/app/Listeners/TrackAnalyticsListener.php',
            ],
        ]);

        $this->assertSame(0, $exitCode);

        $surface->refresh();
        $assignment->refresh();

        $this->assertSame('instrumented', $surface->rollout_status);
        $this->assertStringContainsString('[source=codebase]', (string) $surface->notes);
        $this->assertStringContainsString('TrackAnalyticsListener.php', (string) $surface->notes);

        $this->assertSame('instrumented', $assignment->rollout_status);
        $this->assertStringContainsString('[source=codebase]', (string) $assignment->notes);
    }

    public function test_it_respects_excluded_properties_and_does_not_downgrade_existing_statuses(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-com-au',
            'name' => 'wemove.com.au',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $surface = WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'hostname' => 'quotes.wemove.com.au',
            'surface_type' => 'quote_subdomain',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'verified',
            'notes' => 'Already manually verified.',
        ]);

        $exitCode = Artisan::call('conversion-surfaces:promote-rollout', [
            '--runtime-path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            '--exclude-property' => ['wemove-com-au'],
            '--surface-status' => 'instrumented',
            '--notes' => 'Should not apply.',
        ]);

        $this->assertSame(0, $exitCode);

        $surface->refresh();

        $this->assertSame('verified', $surface->rollout_status);
        $this->assertSame('Already manually verified.', $surface->notes);
    }
}
