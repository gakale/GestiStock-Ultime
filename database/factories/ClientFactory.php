<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isCompany = $this->faker->boolean(70); // 70% de chance d'être une entreprise
        
        return [
            'type' => $isCompany ? 'company' : 'individual',
            'company_name' => $isCompany ? $this->faker->company() : null,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_number' => $this->faker->phoneNumber(),
            'vat_number' => $isCompany ? 'FR' . $this->faker->numerify('#########') : null,
            'billing_address_line1' => $this->faker->streetAddress(),
            'billing_address_line2' => $this->faker->boolean(30) ? $this->faker->secondaryAddress() : null,
            'billing_city' => $this->faker->city(),
            'billing_state_province' => $this->faker->state(),
            'billing_postal_code' => $this->faker->postcode(),
            'billing_country' => 'France',
            'notes' => $this->faker->boolean(40) ? $this->faker->paragraph() : null,
            'is_active' => $this->faker->boolean(90), // 90% de clients actifs
        ];
    }
}
