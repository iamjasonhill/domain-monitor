<?php

namespace Database\Factories;

use App\Models\MonitoringFinding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringFinding>
 */
class MonitoringFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstDetectedAt = fake()->dateTimeBetween('-7 days', '-1 day');
        $lastDetectedAt = (clone $firstDetectedAt)->modify('+'.fake()->numberBetween(1, 48).' hours');
        $status = fake()->randomElement([MonitoringFinding::STATUS_OPEN, MonitoringFinding::STATUS_RECOVERED]);
        $now = now();
        $recoveredAt = null;

        if ($status === MonitoringFinding::STATUS_RECOVERED && $lastDetectedAt <= $now) {
            $recoveredAt = fake()->dateTimeBetween($lastDetectedAt, $now);
        }

        return [
            'issue_id' => 'dm:'.fake()->uuid().':'.fake()->lexify('????????????????'),
            'lane' => fake()->randomElement(['marketing_integrity', 'critical_live', 'seo_agent_readiness', 'deep_audit']),
            'finding_type' => fake()->randomElement([
                'marketing.ga4_install',
                'marketing.conversion_surface_ga4',
                'seo.agent_readiness',
                'critical.redirect_policy',
            ]),
            'issue_type' => fake()->randomElement(['incident', 'regression', 'readiness_gap', 'cleanup']),
            'scope_type' => 'web_property',
            'domain_id' => \App\Models\Domain::factory(),
            'web_property_id' => \App\Models\WebProperty::factory(),
            'status' => $status,
            'title' => fake()->sentence(6),
            'summary' => fake()->sentence(),
            'first_detected_at' => $firstDetectedAt,
            'last_detected_at' => $lastDetectedAt,
            'recovered_at' => $recoveredAt,
            'evidence' => [
                'verdict' => fake()->randomElement(['missing_expected_measurement_id', 'missing_ga4', 'wrong_measurement_id', 'duplicate_streams']),
            ],
        ];
    }
}
