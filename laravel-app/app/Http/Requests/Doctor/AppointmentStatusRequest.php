<?php

namespace App\Http\Requests\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class AppointmentStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AppointmentStatus::class)->except([AppointmentStatus::Scheduled])],
        ];
    }

    public function status(): AppointmentStatus
    {
        return AppointmentStatus::from($this->validated('status'));
    }
}
