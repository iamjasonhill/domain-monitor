<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WebPropertyFleetTechnicalSeoAuditSummaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_property_summary_has_stable_empty_fleet_seo_audit_shape(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('no-audit-site', 'no-audit.example');

        $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', $property->slug)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.has_audit', false)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.status', null)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.summary_counts.fail', 0)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.execution_modes', [])
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.attention_findings', []);
    }

    public function test_web_property_summary_exposes_latest_fleet_seo_audit_without_raw_evidence(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('audited-site', 'audited.example');
        $olderRun = FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $property->id,
            'started_at' => now()->subDay(),
            'summary_counts' => [
                'pass' => 1,
                'fail' => 0,
                'manual_review' => 0,
                'unknown' => 0,
                'not_applicable' => 0,
            ],
        ]);
        $latestRun = FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $property->id,
            'execution_modes' => ['http_fetch', 'html_parse', 'bounded_crawl'],
            'url_cap' => 25,
            'catalog_version' => '2026-05-16-executable-runtime-contract',
            'catalog_checksum' => str_repeat('b', 64),
            'started_at' => now(),
            'finished_at' => now()->addMinute(),
            'summary_counts' => [
                'pass' => 20,
                'fail' => 1,
                'manual_review' => 2,
                'unknown' => 1,
                'not_applicable' => 1,
                'not_checked_due_to_limit' => 3,
            ],
        ]);
        $finding = MonitoringFinding::factory()->create([
            'web_property_id' => $property->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'lane' => 'fleet_technical_seo_full_audit',
            'finding_type' => 'fleet_technical_seo.robots.present_and_fetchable',
            'title' => 'Fleet SEO audit found missing robots.txt',
        ]);
        FleetTechnicalSeoAuditResult::factory()->create([
            'fleet_technical_seo_audit_run_id' => $latestRun->id,
            'check_id' => 'robots.present_and_fetchable',
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_FAIL,
            'evidence_confidence' => FleetTechnicalSeoAuditResult::CONFIDENCE_HIGH,
            'monitoring_finding_id' => $finding->id,
            'evidence' => [
                'raw_html' => '<html>do not expose me</html>',
            ],
        ]);
        FleetTechnicalSeoAuditResult::factory()->create([
            'fleet_technical_seo_audit_run_id' => $olderRun->id,
            'check_id' => 'crawl.http_status_ok',
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_PASS,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.has_audit', true)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.status', 'fail')
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.latest_run_id', $latestRun->id)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.catalog_version', '2026-05-16-executable-runtime-contract')
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.catalog_checksum', str_repeat('b', 64))
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.execution_modes', ['http_fetch', 'html_parse', 'bounded_crawl'])
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.url_cap', 25)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.skipped_url_count', 3)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.summary_counts.pass', 20)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.summary_counts.fail', 1)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.source_system', 'domain-monitor')
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.source_path', '/api/web-properties/'.$property->slug)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.attention_findings.0.id', $finding->id)
            ->assertJsonMissingPath('web_properties.0.fleet_technical_seo_audit_summary.raw_html')
            ->assertJsonMissingPath('web_properties.0.fleet_technical_seo_audit_summary.results');
    }

    public function test_web_property_apis_remain_available_before_late_optional_tables_are_migrated(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('pre-migration-site', 'pre-migration.example');
        Schema::dropIfExists('fleet_technical_seo_audit_results');
        Schema::dropIfExists('fleet_technical_seo_audit_runs');
        Schema::dropIfExists('web_property_conversion_surfaces');
        Schema::dropIfExists('monitoring_findings');

        $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', $property->slug)
            ->assertJsonPath('web_properties.0.conversion_surfaces', [])
            ->assertJsonPath('web_properties.0.monitoring_summary.open_findings_count', 0)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.has_audit', false)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.summary_counts.fail', 0)
            ->assertJsonPath('web_properties.0.fleet_technical_seo_audit_summary.attention_findings', []);

        $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties?per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $property->slug);
    }

    private function makeProperty(string $slug, string $domainName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);
        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::query()->create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }
}
