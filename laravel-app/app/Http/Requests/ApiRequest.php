<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request: authorization is enforced by route/controller middleware,
 * so requests only validate and normalise input.
 */
abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validated input, normalised for the service layer. Override to transform.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
