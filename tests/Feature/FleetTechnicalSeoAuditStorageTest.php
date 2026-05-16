<?php

namespace Tests\Feature;

use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\MonitoringFinding;
use App\Models\WebProperty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetTechnicalSeoAuditStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_run_stores_catalog_execution_context_and_summary_counts(): void
    {
        $property = WebProperty::factory()->create();

        $run = FleetTechnicalSeoAuditRun::create([
            'web_property_id' => $property->id,
            'trigger_type' => 'operator_requested',
            'url_cap' => 25,
            'execution_modes' => ['http_fetch', 'html_parse', 'bounded_crawl'],
            'catalog_version' => '2026-05-16-executable-runtime-contract',
            'catalog_checksum' => str_repeat('a', 64),
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'summary_counts' => [
                'pass' => 21,
                'fail' => 1,
                'not_applicable' => 5,
                'manual_review' => 2,
                'unknown' => 1,
            ],
        ]);

        $this->assertNotSame('', $run->id);
        $this->assertTrue($run->webProperty->is($property));
        $this->assertSame(['http_fetch', 'html_parse', 'bounded_crawl'], $run->execution_modes);
        $this->assertSame(25, $run->url_cap);
        $this->assertSame(1, $run->summary_counts['fail']);
        $this->assertSame($run->id, $property->fleetTechnicalSeoAuditRuns()->firstOrFail()->id);
    }

    public function test_audit_results_store_check_evidence_and_optional_attention_links(): void
    {
        $property = WebProperty::factory()->create();
        $finding = MonitoringFinding::factory()->create([
            'web_property_id' => $property->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'lane' => 'fleet_technical_seo',
            'finding_type' => 'fleet_technical_seo.security.https_valid_and_canonical',
        ]);
        $run = FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $property->id,
            'execution_modes' => ['http_fetch'],
            'summary_counts' => [
                'pass' => 0,
                'fail' => 1,
                'not_applicable' => 0,
                'manual_review' => 0,
                'unknown' => 0,
            ],
        ]);

        $result = FleetTechnicalSeoAuditResult::create([
            'fleet_technical_seo_audit_run_id' => $run->id,
            'check_id' => 'security.https_valid_and_canonical',
            'target_type' => 'url',
            'target_url' => 'https://example.com/',
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_FAIL,
            'evidence_confidence' => FleetTechnicalSeoAuditResult::CONFIDENCE_HIGH,
            'evidence' => [
                'status' => 'ssl_cert_expired',
                'expires_at' => '2026-05-15T00:00:00+10:00',
            ],
            'owner_system' => 'domain-monitor',
            'monitoring_finding_id' => $finding->id,
            'owner_issue_url' => 'https://github.com/iamjasonhill/domain-monitor/issues/999',
        ]);

        $this->assertNotSame('', $result->id);
        $this->assertTrue($result->auditRun->is($run));
        $this->assertTrue($result->monitoringFinding->is($finding));
        $this->assertSame('ssl_cert_expired', $result->evidence['status']);
        $this->assertSame($result->id, $run->results()->firstOrFail()->id);
        $this->assertSame($result->id, $finding->fleetTechnicalSeoAuditResults()->firstOrFail()->id);
    }

    public function test_monitoring_findings_remain_valid_without_full_audit_results(): void
    {
        $finding = MonitoringFinding::factory()->create([
            'status' => MonitoringFinding::STATUS_OPEN,
            'lane' => 'critical_live',
            'finding_type' => 'critical.http_status',
        ]);

        $this->assertDatabaseHas('monitoring_findings', [
            'id' => $finding->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'lane' => 'critical_live',
        ]);
        $this->assertSame(0, $finding->fleetTechnicalSeoAuditResults()->count());
    }

    public function test_manual_review_results_expose_review_payload_and_owner_issue_candidate_shape(): void
    {
        $result = FleetTechnicalSeoAuditResult::factory()->create([
            'check_id' => 'crawl.unexpected_soft_404_absent',
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_MANUAL_REVIEW,
            'evidence_confidence' => FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM,
            'owner_system' => 'site-repo',
            'evidence' => [
                'manual_review' => [
                    'status' => 'pending',
                    'reason' => 'Rendered page looks like a placeholder but needs operator judgement.',
                    'reviewer' => null,
                    'reviewed_at' => null,
                    'notes' => 'This note must remain review evidence, not Attention text.',
                ],
                'owner_issue_candidate' => [
                    'can_create_issue' => true,
                    'owner_repo' => 'iamjasonhill/example-site',
                    'dedupe_key' => 'example-site:crawl.unexpected_soft_404_absent',
                    'reason' => 'Durable actionable owner identified.',
                ],
            ],
        ]);

        $this->assertTrue($result->requiresManualReview());
        $this->assertSame('pending', $result->manualReviewPayload()['status']);
        $this->assertTrue($result->ownerIssueCandidate()['can_create_issue']);
        $this->assertSame('iamjasonhill/example-site', $result->ownerIssueCandidate()['owner_repo']);
    }
}
