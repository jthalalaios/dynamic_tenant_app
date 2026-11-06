<?php

use App\Enums\TenantType;
use App\Models\Patient;
use App\Models\Role;

beforeEach(function () {
    $this->clinic = $this->createTenant(TenantType::Doctor);
    $this->admin = $this->createUser($this->clinic, 'admin');
    $this->doctor = $this->createUser($this->clinic, 'doctor');
    $this->receptionist = $this->createUser($this->clinic, 'receptionist');
});

it('enforces per-action permissions from the roles', function () {
    $patient = Patient::factory()->for($this->clinic)->create();

    // Receptionist: may view and create, but not delete
    $this->actingAsUser($this->receptionist)->getJson('/api/v1/doctor/patients')->assertOk();
    $this->actingAsUser($this->receptionist)->deleteJson("/api/v1/doctor/patients/{$patient->id}")
        ->assertForbidden()
        ->assertJsonPath('message', __('messages.forbidden'));

    // Admin implicitly holds every permission of the tenant type
    $this->actingAsUser($this->admin)->deleteJson("/api/v1/doctor/patients/{$patient->id}")->assertNoContent();
    expect($patient->fresh()->trashed())->toBeTrue();
});

it('keeps administration endpoints for users holding users.* / roles.*', function () {
    $this->actingAsUser($this->doctor)->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAsUser($this->doctor)->getJson('/api/v1/admin/roles')->assertForbidden();

    $this->actingAsUser($this->admin)->getJson('/api/v1/admin/users')->assertOk()->assertJsonCount(3, 'data');
    // admin, doctor, receptionist + member (self-registered users)
    $this->actingAsUser($this->admin)->getJson('/api/v1/admin/roles')->assertOk()->assertJsonCount(4, 'data');
});

it('lists only the permissions grantable by the tenant type, grouped', function () {
    $groups = $this->actingAsUser($this->admin)->getJson('/api/v1/admin/permissions')
        ->assertOk()
        ->json('data.*.group');

    expect($groups)->toContain('patients', 'appointments', 'users', 'roles')
        ->not->toContain('products', 'orders');
});

it('creates a custom role and its permissions take effect immediately', function () {
    $roleId = $this->actingAsUser($this->admin)->postJson('/api/v1/admin/roles', [
        'name' => 'Billing clerk',
        'permissions' => ['patients.view', 'reports.view'],
    ])->assertCreated()
        ->assertJsonPath('data.slug', 'billing-clerk')
        ->assertJsonPath('data.permissions', ['patients.view', 'reports.view'])
        ->json('data.id');

    $clerk = $this->createUser($this->clinic);
    $this->actingAsUser($clerk)->getJson('/api/v1/doctor/reports/summary')->assertForbidden();

    $this->actingAsUser($this->admin)->patchJson("/api/v1/admin/users/{$clerk->id}", ['role_ids' => [$roleId]])->assertOk();
    $this->actingAsUser($clerk)->getJson('/api/v1/doctor/reports/summary')->assertOk();

    // Revoking the permission from the role invalidates the cached grants
    $this->actingAsUser($this->admin)->patchJson("/api/v1/admin/roles/{$roleId}", ['permissions' => ['patients.view']])->assertOk();
    $this->actingAsUser($clerk)->getJson('/api/v1/doctor/reports/summary')->assertForbidden();
});

it('refuses permissions that belong to another tenant type', function () {
    $this->actingAsUser($this->admin)->postJson('/api/v1/admin/roles', [
        'name' => 'Confused',
        'permissions' => ['products.create'],
    ])->assertJsonValidationErrors('permissions.0');
});

it('protects system roles', function () {
    $adminRole = Role::query()->where('tenant_id', $this->clinic->id)->where('slug', 'admin')->first();

    $this->actingAsUser($this->admin)->deleteJson("/api/v1/admin/roles/{$adminRole->id}")
        ->assertUnprocessable()
        ->assertJsonPath('code', 'system_role_locked');

    $this->actingAsUser($this->admin)->patchJson("/api/v1/admin/roles/{$adminRole->id}", ['is_admin' => false])
        ->assertJsonPath('code', 'system_role_locked');
});

it('never leaves a tenant without an administrator', function () {
    $doctorRole = Role::query()->where('tenant_id', $this->clinic->id)->where('slug', 'doctor')->value('id');

    $this->actingAsUser($this->admin)
        ->patchJson("/api/v1/admin/users/{$this->admin->id}", ['role_ids' => [$doctorRole]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'last_admin');

    $this->actingAsUser($this->admin)->deleteJson("/api/v1/admin/users/{$this->admin->id}")
        ->assertJsonPath('code', 'self_deletion');
});

it('lets an administrator who is also a doctor hold both roles', function () {
    $dual = $this->createUser($this->clinic, 'admin', 'doctor');

    $this->actingAsUser($dual)->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.access.is_admin', true)
        ->assertJsonPath('data.access.roles', fn (array $roles) => collect($roles)->sort()->values()->all() === ['admin', 'doctor']);
});

it('creates team members with roles from the same tenant only', function () {
    $foreignRole = Role::query()->where('tenant_id', $this->createTenant()->id)->value('id');

    $this->actingAsUser($this->admin)->postJson('/api/v1/admin/users', [
        'name' => 'New Hire',
        'email' => 'hire@clinic.test',
        'password' => 'Str0ng-Passw0rd!',
        'role_ids' => [$foreignRole],
    ])->assertJsonValidationErrors('role_ids.0');
});

it('integrates the ACL with Laravel\'s Gate and the global helpers', function () {
    expect($this->doctor->can('patients.view'))->toBeTrue()
        ->and($this->doctor->can('patients.delete'))->toBeFalse()
        ->and($this->admin->can('patients.delete'))->toBeTrue()
        ->and($this->admin->can('products.view'))->toBeFalse()
        ->and(has_permission(['patients.delete', 'patients.view'], $this->receptionist))->toBeTrue()
        ->and(has_all_permissions(['patients.delete', 'patients.view'], $this->receptionist))->toBeFalse()
        ->and(has_role('receptionist', $this->receptionist))->toBeTrue()
        ->and(is_admin($this->doctor))->toBeFalse();
});
