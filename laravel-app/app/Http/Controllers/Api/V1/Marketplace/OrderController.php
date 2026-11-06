<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Enums\OrderStatus;
use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\OrderRequest;
use App\Http\Requests\Marketplace\OrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Marketplace\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

class OrderController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public function __construct(private readonly OrderService $orders) {}

    public static function middleware(): array
    {
        return self::permissionMiddleware('orders', ['status' => 'update']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->withCount('items')
            ->with('placedBy:id,name')
            ->when($request->enum('status', OrderStatus::class), fn ($q, OrderStatus $s) => $q->where('status', $s))
            ->when($request->string('search')->trim()->value(), fn ($q, string $term) => $q->where(
                fn ($q) => $q->whereLike('number', "%{$term}%")->orWhereLike('customer_name', "%{$term}%")
            ))
            ->latest()
            ->paginate($this->perPage($request));

        return OrderResource::collection($orders);
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->orders->place($request->validated(), $request->user());

        return (new OrderResource($order->load('placedBy:id,name')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['items', 'placedBy:id,name']));
    }

    public function status(OrderStatusRequest $request, Order $order): OrderResource
    {
        $order = $this->orders->transition($order, $request->status());

        return new OrderResource($order->load(['items', 'placedBy:id,name']));
    }
}
