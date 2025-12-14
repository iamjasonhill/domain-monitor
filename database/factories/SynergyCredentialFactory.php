<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Crypt;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SynergyCredential>
 */
class SynergyCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reseller_id' => fake()->numerify('RES#######'),
            'api_key_encrypted' => Crypt::encryptString(fake()->uuid()),
            'api_url' => 'https://api.synergywholesale.com/soap/soap.php?wsdl',
            'is_active' => true,
            'last_sync_at' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
