<?php

namespace App\Services\Marketplace;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Place an order, reserving stock atomically.
     *
     * @param  array{customer_name: string, customer_email?: ?string, items: list<array{product_id: int, quantity: int}>}  $data
     */
    public function place(array $data, User $actor): Order
    {
        return DB::transaction(function () use ($data, $actor) {
            // Merge duplicate lines, then lock rows in a stable order to avoid deadlocks
            $quantities = collect($data['items'])
                ->groupBy('product_id')
                ->map(fn ($lines) => (int) $lines->sum('quantity'))
                ->sortKeys();

            $products = Product::query()
                ->whereKey($quantities->keys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $currencies = $products->pluck('currency')->unique();
            if ($currencies->count() > 1) {
                throw new BusinessRuleException(__('messages.mixed_currencies'), 'mixed_currencies', 422);
            }

            $lines = [];
            foreach ($quantities as $productId => $quantity) {
                $product = $products->get($productId);

                if ($product === null || ! $product->is_active) {
                    throw ValidationException::withMessages(['items' => __('messages.product_unavailable')]);
                }

                if ($product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => __('messages.insufficient_stock', ['product' => $product->name, 'stock' => $product->stock]),
                    ]);
                }

                $product->decrement('stock', $quantity);

                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price_cents' => $product->price_cents,
                    'quantity' => $quantity,
                    'line_total_cents' => $product->price_cents * $quantity,
                ];
            }

            $order = Order::query()->create([
                'number' => $this->nextNumber(),
                'customer_name' => $data['customer_name'],
                'customer_email' => $data['customer_email'] ?? null,
                'status' => OrderStatus::Pending,
                'total_cents' => array_sum(array_column($lines, 'line_total_cents')),
                'currency' => $currencies->first() ?? 'EUR',
                'placed_by' => $actor->getKey(),
            ]);

            $order->items()->createMany($lines);

            return $order->load('items');
        });
    }

    public function transition(Order $order, OrderStatus $next): Order
    {
        return DB::transaction(function () use ($order, $next) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->getKey());

            if (! $order->status->canTransitionTo($next)) {
                throw new BusinessRuleException(
                    __('messages.invalid_transition', ['from' => $order->status->value, 'to' => $next->value]),
                    'invalid_status_transition',
                    422,
                );
            }

            // Cancelling releases the reserved stock back to the catalogue
            if ($next === OrderStatus::Cancelled) {
                foreach ($order->items as $item) {
                    if ($item->product_id !== null) {
                        Product::query()->withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
                    }
                }
            }

            $order->update(['status' => $next]);

            return $order;
        });
    }

    private function nextNumber(): string
    {
        do {
            $number = 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Order::query()->withTrashed()->where('number', $number)->exists());

        return $number;
    }
}
