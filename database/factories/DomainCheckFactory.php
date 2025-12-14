<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DomainCheck>
 */
class DomainCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 hour', 'now');
        $finishedAt = (clone $startedAt)->modify('+'.fake()->numberBetween(50, 5000).' milliseconds');
        $duration = $finishedAt->getTimestamp() - $startedAt->getTimestamp();

        return [
            'domain_id' => \App\Models\Domain::factory(),
            'check_type' => fake()->randomElement(['http', 'ssl', 'dns', 'uptime', 'downtime', 'platform', 'hosting']),
            'status' => fake()->randomElement(['ok', 'warn', 'fail']),
            'response_code' => fake()->optional()->randomElement([200, 301, 302, 404, 500, 503]),
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'duration_ms' => $duration,
            'error_message' => fake()->optional(0.3)->sentence(),
            'payload' => fake()->optional()->randomElement([
                ['url' => fake()->url(), 'status' => 'ok'],
                ['ssl_valid' => true, 'days_until_expiry' => fake()->numberBetween(1, 365)],
                ['dns_records' => fake()->numberBetween(1, 10)],
            ]),
            'metadata' => fake()->optional()->randomElement([
                ['ip' => fake()->ipv4(), 'server' => fake()->word()],
                ['certificate_issuer' => fake()->company()],
            ]),
            'retry_count' => fake()->numberBetween(0, 3),
        ];
    }
}
