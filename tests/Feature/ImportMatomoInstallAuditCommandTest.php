<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMatomoInstallAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_matomo_install_audit_for_mapped_sources(): void
    {
        $property = WebProperty::factory()->create([
            'slug' => 'car-transport-personal-items',
            'name' => 'Car Transport Personal Items',
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '8',
            'external_name' => 'Old Name',
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $path = tempnam(sys_get_temp_dir(), 'matomo-audit-');
        file_put_contents($path, json_encode([
            'source_system' => 'matamo',
            'contract_version' => 1,
            'report_type' => 'install_verification',
            'generated_at' => now()->toIso8601String(),
            'install_audits' => [
                [
                    'id_site' => '8',
                    'site_name' => 'Car Transport Personal Items',
                    'expected_tracker_host' => 'stats.redirection.com.au',
                    'verdict' => 'installed_match',
                    'best_url' => 'https://cartransportwithpersonalitems.com.au/',
                    'detected_site_ids' => ['8'],
                    'detected_tracker_hosts' => ['stats.redirection.com.au'],
                    'summary' => 'Matomo snippet detected with the expected tracker host and site ID.',
                ],
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('analytics:import-matomo-audit', ['path' => $path])
            ->expectsOutput('Imported 1 Matomo install audit records.')
            ->assertSuccessful();

        $audit = AnalyticsInstallAudit::query()->where('property_analytics_source_id', $source->id)->firstOrFail();

        $this->assertSame($property->id, $audit->web_property_id);
        $this->assertSame('installed_match', $audit->install_verdict);
        $this->assertSame('https://cartransportwithpersonalitems.com.au/', $audit->best_url);
        $this->assertSame(['8'], $audit->detected_site_ids);
        $this->assertSame(['stats.redirection.com.au'], $audit->detected_tracker_hosts);

        $source->refresh();
        $this->assertSame('Car Transport Personal Items', $source->external_name);

        @unlink($path);
    }
}
