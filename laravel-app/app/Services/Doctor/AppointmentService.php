<?php

namespace App\Services\Doctor;

use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private readonly PresenceService $presence) {}

    /**
     * Check a patient in. The acting user must be online; enforced here as well as
     * by route middleware so the rule holds for every caller (jobs, console, ...).
     */
    public function checkIn(Appointment $appointment, User $actor): Appointment
    {
        if (! $this->presence->isOnline($actor)) {
            throw new BusinessRuleException(__('messages.must_be_online'), 'user_offline');
        }

        return DB::transaction(function () use ($appointment, $actor) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->getKey());

            $this->assertTransition($appointment, AppointmentStatus::CheckedIn);

            $appointment->forceFill([
                'status' => AppointmentStatus::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by' => $actor->getKey(),
            ])->save();

            return $appointment;
        });
    }

    public function transition(Appointment $appointment, AppointmentStatus $next, User $actor): Appointment
    {
        if ($next === AppointmentStatus::CheckedIn) {
            return $this->checkIn($appointment, $actor);
        }

        return DB::transaction(function () use ($appointment, $next) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->getKey());

            $this->assertTransition($appointment, $next);

            $appointment->forceFill([
                'status' => $next,
                'completed_at' => $next === AppointmentStatus::Completed ? now() : $appointment->completed_at,
            ])->save();

            return $appointment;
        });
    }

    private function assertTransition(Appointment $appointment, AppointmentStatus $next): void
    {
        if (! $appointment->status->canTransitionTo($next)) {
            throw new BusinessRuleException(
                __('messages.invalid_transition', ['from' => $appointment->status->value, 'to' => $next->value]),
                'invalid_status_transition',
                422,
            );
        }
    }
}
