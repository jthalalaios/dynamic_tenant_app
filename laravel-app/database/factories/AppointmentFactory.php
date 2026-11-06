<?php

namespace Database\Factories;

use App\Enums\AppointmentReason;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            // Inherit the patient's tenant so factories never create cross-tenant rows
            'tenant_id' => fn (array $attributes) => Patient::query()->withoutGlobalScopes()->find($attributes['patient_id'])->tenant_id,
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 30,
            'reason' => fake()->randomElement(AppointmentReason::cases()),
            'status' => AppointmentStatus::Scheduled,
        ];
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
