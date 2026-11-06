<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'locale' => ['sometimes', 'nullable', Rule::in(config('tenancy.locales'))],
            'current_password' => ['required_with:password', 'current_password:api'],
            'password' => ['sometimes', 'required', 'string', 'confirmed', Password::defaults()],
        ];
    }

    public function payload(): array
    {
        return collect($this->validated())->except(['current_password', 'password_confirmation'])->all();
    }
}
