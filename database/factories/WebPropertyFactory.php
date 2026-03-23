<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WebProperty>
 */
class WebPropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' Website';

        return [
            'slug' => Str::slug($name.'-'.fake()->unique()->word()),
            'name' => $name,
            'property_type' => fake()->randomElement(['marketing_site', 'programmatic_site', 'app']),
            'status' => fake()->randomElement(['active', 'planned', 'paused']),
            'production_url' => fake()->url(),
            'platform' => fake()->randomElement(['Astro', 'Laravel', 'WordPress']),
            'priority' => fake()->numberBetween(1, 3),
        ];
    }
}
