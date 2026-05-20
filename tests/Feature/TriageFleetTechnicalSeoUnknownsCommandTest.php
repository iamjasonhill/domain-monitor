<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\FleetTechnicalSeoUnknownTriageCandidate;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class TriageFleetTechnicalSeoUnknownsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_deduped_unknown_triage_candidate_after_threshold(): void
    {
        $property = $this->makeProperty('unknown.example.au');

        $this->createUnknownResult($property, now()->subHours(30), [
            'check_id' => 'robots.present_and_fetchable',
            'owner_system' => 'domain-monitor',
            'evidence' => ['error' => 'timeout one'],
        ]);
        $latestResult = $this->createUnknownResult($property, now()->subHours(2), [
            'check_id' => 'robots.present_and_fetchable',
            'owner_system' => 'domain-monitor',
            'evidence' => ['error' => 'timeout two'],
        ]);

        $exitCode = Artisan::call('monitoring:triage-fleet-technical-seo-unknowns', [
            '--threshold' => 2,
            '--min-age-hours' => 24,
        ]);

        $candidate = FleetTechnicalSeoUnknownTriageCandidate::query()->firstOrFail();

        $this->assertSame(0, $exitCode);
        $this->assertSame('unknown-example-au', $candidate->property_slug);
        $this->assertSame('fleet_technical_seo_deep', $candidate->audit_profile);
        $this->assertSame('web_property:unknown-example-au', $candidate->coverage_unit);
        $this->assertSame('robots.present_and_fetchable', $candidate->check_id);
        $this->assertSame('domain-monitor', $candidate->owner_route);
        $this->assertSame(2, $candidate->retry_count);
        $this->assertSame($latestResult->id, $candidate->latest_audit_result_id);
        $this->assertSame('timeout two', data_get($candidate->candidate_payload, 'latest_evidence.error'));
    }

    public function test_it_waits_for_threshold_and_minimum_age(): void
    {
        $property = $this->makeProperty('young-unknown.example.au');

        $this->createUnknownResult($property, now()->subHours(2), [
            'check_id' => 'sitemap.canonical_endpoint_fetchable',
            'owner_system' => 'domain-monitor',
        ]);
        $this->createUnknownResult($property, now()->subHour(), [
            'check_id' => 'sitemap.canonical_endpoint_fetchable',
            'owner_system' => 'domain-monitor',
        ]);

        $exitCode = Artisan::call('monitoring:triage-fleet-technical-seo-unknowns', [
            '--threshold' => 2,
            '--min-age-hours' => 24,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('fleet_technical_seo_unknown_triage_candidates', 0);
        $this->assertStringContainsString('No Fleet technical SEO unknown triage candidates met the threshold.', Artisan::output());
    }

    public function test_dry_run_lists_without_storing_candidate(): void
    {
        $property = $this->makeProperty('dry-run-unknown.example.au');

        $this->createUnknownResult($property, now()->subHours(30), [
            'check_id' => 'analytics.google_evidence_owned_by_mm_google',
            'owner_system' => 'MM-Google',
        ]);
        $this->createUnknownResult($property, now()->subHours(3), [
            'check_id' => 'analytics.google_evidence_owned_by_mm_google',
            'owner_system' => 'MM-Google',
        ]);

        $exitCode = Artisan::call('monitoring:triage-fleet-technical-seo-unknowns', [
            '--threshold' => 2,
            '--min-age-hours' => 24,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseCount('fleet_technical_seo_unknown_triage_candidates', 0);
        $this->assertStringContainsString('[dry-run] dry-run-unknown-example-au analytics.google_evidence_owned_by_mm_google retry_count=2 owner=mm-google', Artisan::output());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUnknownResult(WebProperty $property, \DateTimeInterface $startedAt, array $attributes = []): FleetTechnicalSeoAuditResult
    {
        $run = FleetTechnicalSeoAuditRun::factory()->create([
            'web_property_id' => $property->id,
            'trigger_type' => 'fleet_technical_seo_deep',
            'started_at' => $startedAt,
            'finished_at' => \Illuminate\Support\Carbon::instance($startedAt)->addMinutes(3),
        ]);

        return FleetTechnicalSeoAuditResult::factory()->create(array_merge([
            'fleet_technical_seo_audit_run_id' => $run->id,
            'check_id' => 'robots.present_and_fetchable',
            'target_type' => 'web_property',
            'target_url' => null,
            'result_status' => FleetTechnicalSeoAuditResult::STATUS_UNKNOWN,
            'evidence_confidence' => FleetTechnicalSeoAuditResult::CONFIDENCE_LOW,
            'evidence' => [],
            'owner_system' => 'domain-monitor',
        ], $attributes));
    }

    private function makeProperty(string $domainName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);
        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $domainName,
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://'.$domainName.'/',
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
