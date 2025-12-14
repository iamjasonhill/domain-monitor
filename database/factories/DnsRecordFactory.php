<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DnsRecord>
 */
class DnsRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'SOA', 'TXT', 'SRV'];
        $type = fake()->randomElement($types);

        return [
            'domain_id' => \App\Models\Domain::factory(),
            'host' => fake()->domainName(),
            'type' => $type,
            'value' => match ($type) {
                'A' => fake()->ipv4(),
                'AAAA' => fake()->ipv6(),
                'CNAME' => fake()->domainName(),
                'MX' => fake()->domainName(),
                'NS' => fake()->domainName(),
                'TXT' => fake()->sentence(),
                default => fake()->domainName(),
            },
            'ttl' => fake()->randomElement([300, 600, 3600, 86400]),
            'priority' => $type === 'MX' ? fake()->numberBetween(10, 100) : null,
            'record_id' => fake()->optional()->uuid(),
            'synced_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
