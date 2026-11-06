<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ApiRequest;
use App\Tenancy\Themes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiRequest
{
    /**
     * `type` picks the workspace to join (the clinic or the shop). A standalone
     * frontend (X-App-Theme) does not need to send it: it only serves its own theme.
     */
    protected function prepareForValidation(): void
    {
        $requested = app(Themes::class)->requested();

        if ($requested !== null && blank($this->input('type'))) {
            $this->merge(['type' => $requested->value]);
        }
    }

    public function rules(): array
    {
        return [
            // Only themes this backend serves, and only the frontend's own theme when it is standalone
            'type' => ['required', 'string', Rule::in(app(Themes::class)->allowedValues())],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'timezone' => ['nullable', 'timezone:all'],
            'locale' => ['nullable', Rule::in(config('tenancy.locales'))],
        ];
    }
}
