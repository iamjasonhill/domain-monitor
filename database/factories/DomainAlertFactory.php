<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DomainAlert>
 */
class DomainAlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $triggeredAt = fake()->dateTimeBetween('-1 week', 'now');
        $resolved = fake()->boolean(30); // 30% chance of being resolved

        return [
            'domain_id' => \App\Models\Domain::factory(),
            'alert_type' => fake()->randomElement(['downtime', 'ssl_expiring', 'dns_changed', 'ssl_expired', 'domain_expiring']),
            'severity' => fake()->randomElement(['info', 'warn', 'critical']),
            'triggered_at' => $triggeredAt,
            'resolved_at' => $resolved ? fake()->dateTimeBetween($triggeredAt, 'now') : null,
            'notification_sent_at' => fake()->optional(0.7)->dateTimeBetween($triggeredAt, 'now'),
            'acknowledged_at' => fake()->optional(0.4)->dateTimeBetween($triggeredAt, 'now'),
            'auto_resolve' => fake()->boolean(20), // 20% chance of auto-resolve
            'payload' => fake()->optional()->randomElement([
                ['message' => 'Domain is down', 'status_code' => 503],
                ['days_until_expiry' => fake()->numberBetween(1, 30)],
                ['dns_changes' => fake()->numberBetween(1, 5)],
            ]),
        ];
    }
}
