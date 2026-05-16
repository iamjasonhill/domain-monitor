<?php

namespace Database\Factories;

use App\Models\FleetTechnicalSeoAuditResult;
use App\Models\FleetTechnicalSeoAuditRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetTechnicalSeoAuditResult>
 */
class FleetTechnicalSeoAuditResultFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fleet_technical_seo_audit_run_id' => FleetTechnicalSeoAuditRun::factory(),
            'check_id' => fake()->randomElement([
                'crawl.http_status_ok',
                'robots.present_and_fetchable',
                'title.present_unique_and_relevant',
                'security.https_valid_and_canonical',
            ]),
            'target_type' => fake()->randomElement(['web_property', 'url']),
            'target_url' => fake()->optional()->url(),
            'result_status' => fake()->randomElement([
                FleetTechnicalSeoAuditResult::STATUS_PASS,
                FleetTechnicalSeoAuditResult::STATUS_FAIL,
                FleetTechnicalSeoAuditResult::STATUS_NOT_APPLICABLE,
                FleetTechnicalSeoAuditResult::STATUS_MANUAL_REVIEW,
                FleetTechnicalSeoAuditResult::STATUS_UNKNOWN,
            ]),
            'evidence_confidence' => fake()->randomElement([
                FleetTechnicalSeoAuditResult::CONFIDENCE_HIGH,
                FleetTechnicalSeoAuditResult::CONFIDENCE_MEDIUM,
                FleetTechnicalSeoAuditResult::CONFIDENCE_LOW,
            ]),
            'evidence' => [
                'message' => fake()->sentence(),
            ],
            'owner_system' => fake()->randomElement(['domain-monitor', 'Fleet', 'site-repo']),
        ];
    }
}
