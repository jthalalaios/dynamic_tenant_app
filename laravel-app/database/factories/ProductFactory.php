<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory()->marketplace(),
            'sku' => fake()->unique()->bothify('SKU-####-??'),
            'name' => ucfirst(fake()->words(2, true)),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['audio', 'wearables', 'home', 'accessories']),
            'price_cents' => fake()->numberBetween(500, 50000),
            'currency' => 'EUR',
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
