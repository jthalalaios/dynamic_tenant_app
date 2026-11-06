<?php

use App\Enums\TenantType;
use App\Models\Patient;
use App\Models\Product;

beforeEach(function () {
    $this->clinicA = $this->createTenant(TenantType::Doctor);
    $this->clinicB = $this->createTenant(TenantType::Doctor);
    $this->admin = $this->createUser($this->clinicA, 'admin');
});

it('lists only the current tenant records', function () {
    Patient::factory()->count(2)->for($this->clinicA)->create();
    Patient::factory()->count(3)->for($this->clinicB)->create();

    $this->actingAsUser($this->admin)
        ->getJson('/api/v1/doctor/patients')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('cannot read, update or delete another tenant\'s record through route binding', function () {
    $foreign = Patient::factory()->for($this->clinicB)->create();

    $this->actingAsUser($this->admin)->getJson("/api/v1/doctor/patients/{$foreign->id}")
        ->assertNotFound()
        ->assertJsonPath('message', __('messages.not_found'));
    $this->actingAsUser($this->admin)->putJson("/api/v1/doctor/patients/{$foreign->id}", ['first_name' => 'Hacked'])->assertNotFound();
    $this->actingAsUser($this->admin)->deleteJson("/api/v1/doctor/patients/{$foreign->id}")->assertNotFound();

    expect($foreign->fresh()->first_name)->not->toBe('Hacked')
        ->and($foreign->fresh()->trashed())->toBeFalse();
});

it('stamps the tenant on create and ignores a client supplied tenant_id', function () {
    $this->actingAsUser($this->admin)
        ->postJson('/api/v1/doctor/patients', ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'tenant_id' => $this->clinicB->id])
        ->assertCreated();

    expect(Patient::query()->withoutGlobalScopes()->where('last_name', 'Lovelace')->value('tenant_id'))->toBe($this->clinicA->id);
});

it('rejects foreign keys that point into another tenant', function () {
    $foreignPatient = Patient::factory()->for($this->clinicB)->create();

    $this->actingAsUser($this->admin)
        ->postJson('/api/v1/doctor/appointments', [
            'patient_id' => $foreignPatient->id,
            'scheduled_at' => now()->addDay()->toIso8601String(),
            'reason' => 'consultation',
        ])
        ->assertJsonValidationErrors('patient_id');
});

it('hides other tenants\' users from administrators', function () {
    $other = $this->createUser($this->clinicB, 'admin');

    $this->actingAsUser($this->admin)->getJson("/api/v1/admin/users/{$other->id}")->assertNotFound();
});

it('blocks features of the other tenant type', function () {
    $shop = $this->createTenant(TenantType::Marketplace);
    $shopAdmin = $this->createUser($shop, 'admin');

    $this->actingAsUser($shopAdmin)->getJson('/api/v1/doctor/patients')
        ->assertForbidden()
        ->assertJsonPath('message', __('messages.feature_unavailable'));

    $this->actingAsUser($this->admin)->getJson('/api/v1/marketplace/products')->assertForbidden();
});

it('locks out users of a deactivated tenant immediately', function () {
    $this->clinicA->update(['is_active' => false]);

    $this->actingAsUser($this->admin)->getJson('/api/v1/auth/me')->assertForbidden();
});

it('scopes marketplace products per tenant', function () {
    $shopA = $this->createTenant(TenantType::Marketplace);
    $shopB = $this->createTenant(TenantType::Marketplace);
    Product::factory()->for($shopA)->create(['sku' => 'SAME-1']);

    // The same SKU is allowed in another tenant
    $this->actingAsUser($this->createUser($shopB, 'admin'))
        ->postJson('/api/v1/marketplace/products', ['sku' => 'SAME-1', 'name' => 'Thing', 'price_cents' => 1000])
        ->assertCreated();
});
