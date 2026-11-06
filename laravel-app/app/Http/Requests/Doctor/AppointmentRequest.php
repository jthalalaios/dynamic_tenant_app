<?php

namespace App\Http\Requests\Doctor;

use App\Enums\AppointmentReason;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'patient_id' => [$required, 'integer', Rule::exists('patients', 'id')->where('tenant_id', tenant_id())->whereNull('deleted_at')],
            'doctor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', tenant_id())],
            // Wall time in the viewer's timezone (or ISO-8601 with an offset)
            'scheduled_at' => [$required, 'date'],
            'duration_minutes' => ['sometimes', 'integer', 'min:5', 'max:480'],
            'reason' => [$required, Rule::enum(AppointmentReason::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = from_user_timezone($data['scheduled_at']);
        }

        return $data;
    }
}
