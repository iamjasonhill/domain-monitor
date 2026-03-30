<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\AnalyticsSourceObservation;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
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

        $exitCode = Artisan::call('analytics:import-matomo-audit', ['path' => $path]);

        $this->assertSame(0, $exitCode);

        $audit = AnalyticsInstallAudit::query()->where('property_analytics_source_id', $source->id)->firstOrFail();

        $this->assertSame($property->id, $audit->web_property_id);
        $this->assertSame('installed_match', $audit->install_verdict);
        $this->assertSame('https://cartransportwithpersonalitems.com.au/', $audit->best_url);
        $this->assertSame(['8'], $audit->detected_site_ids);
        $this->assertSame(['stats.redirection.com.au'], $audit->detected_tracker_hosts);

        $source->refresh();
        $this->assertSame('Car Transport Personal Items', $source->external_name);

        $observation = AnalyticsSourceObservation::query()
            ->where('provider', 'matomo')
            ->where('external_id', '8')
            ->firstOrFail();

        $this->assertSame($property->id, $observation->matched_web_property_id);
        $this->assertSame($source->id, $observation->matched_property_analytics_source_id);
        $this->assertSame('installed_match', $observation->install_verdict);

        @unlink($path);
    }

    public function test_it_preserves_last_successful_audit_when_new_payload_only_reports_fetch_failed(): void
    {
        $property = WebProperty::factory()->create([
            'slug' => 'steady-site',
            'name' => 'Steady Site',
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '18',
            'external_name' => 'Steady Site',
            'is_primary' => true,
            'status' => 'active',
        ]);

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '18',
            'external_name' => 'Steady Site',
            'expected_tracker_host' => 'stats.redirection.com.au',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://steady.example.au/',
            'detected_site_ids' => ['18'],
            'detected_tracker_hosts' => ['stats.redirection.com.au'],
            'summary' => 'Previous good audit.',
            'checked_at' => now()->subDay(),
            'raw_payload' => ['id_site' => '18', 'verdict' => 'installed_match'],
        ]);

        $path = $this->writeAuditPayload([
            [
                'id_site' => '18',
                'site_name' => 'Steady Site',
                'expected_tracker_host' => 'stats.redirection.com.au',
                'verdict' => 'fetch_failed',
                'best_url' => 'https://steady.example.au/',
                'detected_site_ids' => [],
                'detected_tracker_hosts' => [],
                'summary' => 'Could not fetch any candidate URL to verify the Matomo install.',
            ],
        ]);

        $this->assertSame(0, Artisan::call('analytics:import-matomo-audit', ['path' => $path]));

        $audit = AnalyticsInstallAudit::query()->where('property_analytics_source_id', $source->id)->firstOrFail();
        $this->assertSame('installed_match', $audit->install_verdict);
        $this->assertSame('Previous good audit.', $audit->summary);

        $observation = AnalyticsSourceObservation::query()
            ->where('provider', 'matomo')
            ->where('external_id', '18')
            ->firstOrFail();

        $this->assertSame('fetch_failed', $observation->install_verdict);

        @unlink($path);
    }

    /**
     * @param  array<int, array<string, mixed>>  $audits
     */
    private function writeAuditPayload(array $audits): string
    {
        $path = tempnam(sys_get_temp_dir(), 'matomo-audit-');

        file_put_contents($path, json_encode([
            'source_system' => 'matamo',
            'contract_version' => 1,
            'report_type' => 'install_verification',
            'generated_at' => now()->toIso8601String(),
            'install_audits' => $audits,
        ], JSON_PRETTY_PRINT));

        return $path;
    }
}
