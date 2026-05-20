<?php

namespace Database\Factories;

use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\FleetTechnicalSeoUnknownTriageCandidate;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FleetTechnicalSeoUnknownTriageCandidate>
 */
class FleetTechnicalSeoUnknownTriageCandidateFactory extends Factory
{
    public function definition(): array
    {
        $property = WebProperty::factory();
        $run = FleetTechnicalSeoAuditRun::factory();
        $result = FleetTechnicalSeoAuditResult::factory();
        $checkId = fake()->randomElement(['robots.present_and_fetchable', 'analytics.google_evidence_owned_by_mm_google']);
        $auditProfile = 'fleet_technical_seo_deep';
        $coverageUnit = 'web_property:'.fake()->slug();
        $ownerRoute = fake()->randomElement(['domain-monitor', 'fleet', 'mm-google', 'control']);

        return [
            'dedupe_key' => hash('sha256', $auditProfile.'|'.$coverageUnit.'|'.$checkId.'|'.$ownerRoute.'|'.Str::uuid()->toString()),
            'web_property_id' => $property,
            'domain_id' => null,
            'property_slug' => fake()->slug(),
            'audit_profile' => $auditProfile,
            'coverage_unit' => $coverageUnit,
            'check_id' => $checkId,
            'owner_route' => $ownerRoute,
            'latest_audit_run_id' => $run,
            'latest_audit_result_id' => $result,
            'retry_count' => 2,
            'first_seen_at' => now()->subDay(),
            'last_seen_at' => now(),
            'status' => FleetTechnicalSeoUnknownTriageCandidate::STATUS_OPEN,
            'candidate_payload' => ['check_id' => $checkId],
        ];
    }
}
