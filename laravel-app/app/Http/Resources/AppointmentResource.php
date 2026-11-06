<?php

namespace App\Http\Resources;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => to_user_timezone($this->scheduled_at),
            'duration_minutes' => $this->duration_minutes,
            'reason' => $this->reason->value,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->transitions()),
            'checked_in_at' => to_user_timezone($this->checked_in_at),
            'completed_at' => to_user_timezone($this->completed_at),
            'notes' => $this->notes,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? ['id' => $this->doctor->id, 'name' => $this->doctor->name] : null),
            'checked_in_by' => $this->whenLoaded('checkedInBy', fn () => $this->checkedInBy ? ['id' => $this->checkedInBy->id, 'name' => $this->checkedInBy->name] : null),
        ];
    }
}
