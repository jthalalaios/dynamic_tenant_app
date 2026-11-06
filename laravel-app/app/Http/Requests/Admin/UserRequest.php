<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Create (POST) and partial update (PUT/PATCH) of a team member.
 */
class UserRequest extends ApiRequest
{
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'email' => [$required, 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => $creating
                ? ['required', 'string', Password::defaults()]
                : ['sometimes', 'nullable', 'string', Password::defaults()],
            'timezone' => ['sometimes', 'nullable', 'timezone:all'],
            'locale' => ['sometimes', 'nullable', Rule::in(config('tenancy.locales'))],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => [$creating ? 'present' : 'sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->where('tenant_id', tenant_id())],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $this->boolean('is_active');
        }

        if (array_key_exists('role_ids', $data)) {
            $data['role_ids'] = array_map('intval', $data['role_ids']);
        }

        return $data;
    }
}
