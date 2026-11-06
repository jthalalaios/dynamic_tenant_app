<?php

namespace Database\Factories;

use App\Enums\TenantType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'type' => TenantType::Doctor,
            'timezone' => 'UTC',
            'locale' => 'en',
            'is_active' => true,
        ];
    }

    public function doctor(): static
    {
        return $this->state(['type' => TenantType::Doctor]);
    }

    public function marketplace(): static
    {
        return $this->state(['type' => TenantType::Marketplace]);
    }
}
