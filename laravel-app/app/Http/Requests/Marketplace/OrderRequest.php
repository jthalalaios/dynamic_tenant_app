<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class OrderRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('tenant_id', tenant_id())->whereNull('deleted_at')],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }
}
