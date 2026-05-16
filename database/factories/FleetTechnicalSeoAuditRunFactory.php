<?php

namespace Database\Factories;

use App\Models\FleetTechnicalSeoAuditRun;
use App\Models\WebProperty;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FleetTechnicalSeoAuditRun>
 */
class FleetTechnicalSeoAuditRunFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subMinutes(fake()->numberBetween(5, 60));

        return [
            'web_property_id' => WebProperty::factory(),
            'trigger_type' => fake()->randomElement(['manual', 'scheduled', 'operator_requested']),
            'url_cap' => 25,
            'execution_modes' => ['http_fetch', 'html_parse', 'bounded_crawl'],
            'catalog_version' => '2026-05-16-executable-runtime-contract',
            'catalog_checksum' => hash('sha256', fake()->uuid()),
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->addMinutes(fake()->numberBetween(1, 5)),
            'summary_counts' => [
                'pass' => fake()->numberBetween(1, 20),
                'fail' => 0,
                'manual_review' => fake()->numberBetween(0, 3),
                'unknown' => 0,
                'not_applicable' => fake()->numberBetween(0, 5),
            ],
        ];
    }
}
