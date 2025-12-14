<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebsitePlatform>
 */
class WebsitePlatformFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain_id' => \App\Models\Domain::factory(),
            'platform_type' => fake()->randomElement(['WordPress', 'Laravel', 'Next.js', 'Shopify', 'Static', 'Other']),
            'platform_version' => fake()->optional()->semver(),
            'admin_url' => fake()->optional()->url(),
            'detection_confidence' => fake()->randomElement(['high', 'medium', 'low']),
            'last_detected' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
