<?php

namespace App\Http\Controllers\Api\V1\Marketplace;

use App\Http\Controllers\Concerns\ResourcePermissions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketplace\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProductController extends Controller implements HasMiddleware
{
    use ResourcePermissions;

    public static function middleware(): array
    {
        return self::permissionMiddleware('products');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->when($request->string('search')->trim()->value(), fn ($q, string $term) => $q->where(
                fn ($q) => $q->whereLike('name', "%{$term}%")->orWhereLike('sku', "%{$term}%")
            ))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')->value()))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->orderBy('name')
            ->paginate($this->perPage($request));

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->payload());

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->payload());

        return new ProductResource($product);
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
