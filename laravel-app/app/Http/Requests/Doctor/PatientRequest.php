<?php

namespace App\Http\Requests\Doctor;

use App\Http\Requests\ApiRequest;

class PatientRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'first_name' => [$required, 'string', 'max:80'],
            'last_name' => [$required, 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'date_of_birth' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'nullable', 'in:female,male,other'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
