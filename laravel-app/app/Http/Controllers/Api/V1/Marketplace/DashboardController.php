<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 10;

    public function __invoke(): JsonResponse
    {
        $startOfDay = now(user_timezone())->startOfDay()->utc();
        $revenueStatuses = [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered];

        return response()->json([
            'data' => [
                'stats' => [
                    'products' => Product::query()->count(),
                    'active_products' => Product::query()->where('is_active', true)->count(),
                    'units_in_stock' => (int) Product::query()->sum('stock'),
                    'low_stock' => Product::query()->where('stock', '<', self::LOW_STOCK_THRESHOLD)->count(),
                    'orders_today' => Order::query()->where('created_at', '>=', $startOfDay)->count(),
                    'pending_orders' => Order::query()->where('status', OrderStatus::Pending)->count(),
                    'revenue_cents' => (int) Order::query()->whereIn('status', $revenueStatuses)->sum('total_cents'),
                ],
                'low_stock' => ProductResource::collection(
                    Product::query()->where('stock', '<', self::LOW_STOCK_THRESHOLD)->orderBy('stock')->limit(5)->get()
                ),
                'recent_orders' => OrderResource::collection(
                    Order::query()->withCount('items')->latest()->limit(5)->get()
                ),
            ],
        ]);
    }
}
