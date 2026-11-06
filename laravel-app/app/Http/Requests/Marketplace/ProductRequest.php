<?php

namespace App\Http\Requests\Marketplace;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends ApiRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'sku' => [$required, 'string', 'max:64', Rule::unique('products', 'sku')->where('tenant_id', tenant_id())->ignore($this->route('product'))],
            'name' => [$required, 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'price_cents' => [$required, 'integer', 'min:0', 'max:100000000'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'stock' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function payload(): array
    {
        $data = $this->validated();

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = $this->boolean('is_active');
        }

        return $data;
    }
}
