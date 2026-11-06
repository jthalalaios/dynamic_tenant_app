<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'date_of_birth' => fake()->dateTimeBetween('-90 years', '-1 year')->format('Y-m-d'),
            'gender' => fake()->randomElement(['female', 'male', 'other']),
        ];
    }
}
