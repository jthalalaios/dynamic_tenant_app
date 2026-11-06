<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;

class InsightController extends Controller
{
    /**
     * Best sellers by units sold (cancelled orders excluded).
     */
    public function topProducts(): JsonResponse
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', tenant_id())
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->selectRaw('order_items.product_name as name, sum(order_items.quantity) as units, sum(order_items.line_total_cents) as revenue_cents')
            ->groupBy('order_items.product_name')
            ->orderByDesc('units')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $rows->map(fn ($r) => [
                'name' => $r->name,
                'units' => (int) $r->units,
                'revenue_cents' => (int) $r->revenue_cents,
            ]),
        ]);
    }
}
