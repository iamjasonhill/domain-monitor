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
            'status' => fake()->randomElement([\App\Models\MonitoringFinding::STATUS_OPEN, \App\Models\MonitoringFinding::STATUS_RECOVERED]),
            'title' => fake()->sentence(6),
            'summary' => fake()->sentence(),
            'first_detected_at' => $firstDetectedAt,
            'last_detected_at' => $lastDetectedAt,
            'recovered_at' => fake()->optional()->dateTimeBetween($lastDetectedAt, 'now'),
            'evidence' => [
                'verdict' => fake()->randomElement(['missing_ga4', 'wrong_measurement_id', 'duplicate_streams']),
            ],
        ];
    }
}
