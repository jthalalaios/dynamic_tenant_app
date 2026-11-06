<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'status' => $this->status->value,
            'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->transitions()),
            'total_cents' => $this->total_cents,
            'currency' => $this->currency,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit_price_cents' => $item->unit_price_cents,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->line_total_cents,
            ])),
            'placed_by' => $this->whenLoaded('placedBy', fn () => $this->placedBy ? ['id' => $this->placedBy->id, 'name' => $this->placedBy->name] : null),
            'created_at' => to_user_timezone($this->created_at),
        ];
    }
}
