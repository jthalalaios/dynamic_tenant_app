<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'created_at' => to_user_timezone($this->created_at),
        ];
    }
}
