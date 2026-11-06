<?php

namespace App\Http\Requests\Admin;

use App\Authorization\PermissionRegistry;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends ApiRequest
{
    public function rules(PermissionRegistry $registry): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_admin' => ['sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            // Only permissions valid for this tenant type can be granted (no doctor perms in a shop)
            'permissions.*' => ['string', 'distinct', Rule::in($registry->namesFor(tenant()->type))],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (array_key_exists('is_admin', $data)) {
            $data['is_admin'] = $this->boolean('is_admin');
        }

        return $data;
    }
}
