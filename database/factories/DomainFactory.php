<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Domain>
 */
class DomainFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'domain' => fake()->domainName(),
            'project_key' => fake()->optional()->word(),
            'registrar' => fake()->optional()->company(),
            'hosting_provider' => fake()->optional()->randomElement(['Vercel', 'Render', 'Cloudflare', 'AWS', 'DigitalOcean', 'Linode']),
            'platform' => fake()->optional()->randomElement(['WordPress', 'Laravel', 'Next.js', 'Shopify', 'Static']),
            'expires_at' => fake()->optional()->dateTimeBetween('now', '+2 years'),
            'last_checked_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'check_frequency_minutes' => fake()->randomElement([15, 30, 60, 120]),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
