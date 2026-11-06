<?php

namespace App\Http\Requests\Marketplace;

use App\Enums\OrderStatus;
use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(OrderStatus::class)->except([OrderStatus::Pending])],
        ];
    }

    public function status(): OrderStatus
    {
        return OrderStatus::from($this->validated('status'));
    }
}
