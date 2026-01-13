<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UptimeIncident>
 */
class UptimeIncidentFactory extends Factory
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
            'started_at' => now()->subMinutes(rand(10, 60)),
            'ended_at' => null,
            'status_code' => 503,
            'error_message' => 'Service Unavailable',
        ];
    }
}
