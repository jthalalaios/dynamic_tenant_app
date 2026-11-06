<?php

use App\Enums\TenantType;
use App\Models\Product;

beforeEach(function () {
    $this->shop = $this->createTenant(TenantType::Marketplace);
    $this->sales = $this->createUser($this->shop, 'sales');
    $this->manager = $this->createUser($this->shop, 'manager');
    $this->product = Product::factory()->for($this->shop)->create(['price_cents' => 1250, 'stock' => 5]);
});

it('places an order, snapshots prices and reserves stock', function () {
    $this->actingAsUser($this->sales)->postJson('/api/v1/marketplace/orders', [
        'customer_name' => 'Jane',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
            ['product_id' => $this->product->id, 'quantity' => 1],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_cents', 3750)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.quantity', 3);

    expect($this->product->fresh()->stock)->toBe(2);
});

it('rejects orders exceeding stock and leaves stock untouched', function () {
    $this->actingAsUser($this->sales)->postJson('/api/v1/marketplace/orders', [
        'customer_name' => 'Jane',
        'items' => [['product_id' => $this->product->id, 'quantity' => 6]],
    ])->assertJsonValidationErrors('items');

    expect($this->product->fresh()->stock)->toBe(5);
});

it('releases stock when an order is cancelled', function () {
    $orderId = $this->actingAsUser($this->sales)->postJson('/api/v1/marketplace/orders', [
        'customer_name' => 'Jane',
        'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
    ])->json('data.id');

    // Sales associates cannot change order status
    $this->actingAsUser($this->sales)->patchJson("/api/v1/marketplace/orders/{$orderId}/status", ['status' => 'cancelled'])->assertForbidden();

    $this->actingAsUser($this->manager)->patchJson("/api/v1/marketplace/orders/{$orderId}/status", ['status' => 'cancelled'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($this->product->fresh()->stock)->toBe(5);
});

it('cannot order products of another tenant', function () {
    $foreign = Product::factory()->for($this->createTenant(TenantType::Marketplace))->create(['stock' => 10]);

    $this->actingAsUser($this->sales)->postJson('/api/v1/marketplace/orders', [
        'customer_name' => 'Jane',
        'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
    ])->assertJsonValidationErrors('items.0.product_id');

    expect($foreign->fresh()->stock)->toBe(10);
});
