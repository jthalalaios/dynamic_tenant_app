<?php

use App\Enums\TenantType;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\Workspaces;

/*
| One workspace (organization) per theme: sign-ups join it, and organizations
| created by the old "sign up = new organization" flow are merged into it.
*/

it('creates a missing workspace once, with every system role', function () {
    $workspaces = app(Workspaces::class);

    $clinic = $workspaces->for(TenantType::Doctor);
    $again = $workspaces->for(TenantType::Doctor);

    expect($again->id)->toBe($clinic->id)
        ->and($clinic->slug)->toBe(config('tenancy.workspaces.doctor.slug'))
        ->and($clinic->name)->toBe(config('tenancy.workspaces.doctor.name'))
        ->and($clinic->roles()->pluck('slug')->sort()->values()->all())->toBe(['admin', 'doctor', 'member', 'receptionist']);
});

it('adds roles introduced later without touching customised ones', function () {
    $clinic = $this->createTenant(TenantType::Doctor, ['slug' => config('tenancy.workspaces.doctor.slug')]);
    Role::query()->withoutGlobalScopes()->where('tenant_id', $clinic->id)->where('slug', 'member')->delete();
    $doctor = Role::query()->withoutGlobalScopes()->where('tenant_id', $clinic->id)->where('slug', 'doctor')->firstOrFail();
    $doctor->permissions()->detach();

    app(Workspaces::class)->for(TenantType::Doctor);

    expect(Role::query()->withoutGlobalScopes()->where('tenant_id', $clinic->id)->where('slug', 'member')->exists())->toBeTrue()
        ->and($doctor->permissions()->count())->toBe(0);
});

it('refuses sign-ups while the workspace is deactivated', function () {
    $this->createTenant(TenantType::Doctor, ['slug' => config('tenancy.workspaces.doctor.slug'), 'is_active' => false]);

    $this->postJson('/api/v1/auth/register', [
        'type' => 'doctor', 'name' => 'Doc', 'email' => 'doc@closed.test', 'password' => 'Str0ng-Passw0rd!',
    ])->assertUnprocessable()->assertJsonPath('code', 'registration_closed');

    expect(User::query()->withoutGlobalScopes()->where('email', 'doc@closed.test')->exists())->toBeFalse();
});

it('merges extra organizations into their theme workspace', function () {
    $clinic = $this->createTenant(TenantType::Doctor, ['slug' => config('tenancy.workspaces.doctor.slug')]);
    $shop = $this->createTenant(TenantType::Marketplace, ['slug' => config('tenancy.workspaces.marketplace.slug')]);
    $clinicAdmin = $this->createUser($clinic, 'admin');

    // Leftovers of the old flow: each sign-up became the admin of its own organization
    $extraClinic = $this->createTenant(TenantType::Doctor, ['name' => 'tester clinic']);
    $extraShop = $this->createTenant(TenantType::Marketplace, ['name' => 'tester shop']);
    $doctorUser = $this->createUser($extraClinic, 'admin');
    $shopUser = $this->createUser($extraShop, 'admin');
    $room = ChatRoom::query()->withoutGlobalScopes()->create(['tenant_id' => $extraClinic->id, 'name' => 'Old', 'slug' => 'old']);
    ChatMessage::query()->withoutGlobalScopes()->create(['tenant_id' => $extraClinic->id, 'room_id' => $room->id, 'user_id' => $doctorUser->id, 'body' => 'hi']);

    (require database_path('migrations/2026_11_06_310000_consolidate_workspaces.php'))->up();

    // Users kept, now plain members of their theme's workspace
    $doctorUser->refresh();
    $shopUser->refresh();
    expect($doctorUser->tenant_id)->toBe($clinic->id)
        ->and($doctorUser->roles()->withoutGlobalScopes()->pluck('slug')->all())->toBe(['member'])
        ->and($doctorUser->isAdmin())->toBeFalse()
        ->and($shopUser->tenant_id)->toBe($shop->id)
        ->and($shopUser->roles()->withoutGlobalScopes()->pluck('slug')->all())->toBe(['member']);

    // The extra organizations are gone with what was scoped to them
    expect(Tenant::query()->withTrashed()->whereKey([$extraClinic->id, $extraShop->id])->exists())->toBeFalse()
        ->and(ChatRoom::query()->withoutGlobalScopes()->whereKey($room->id)->exists())->toBeFalse()
        ->and(Tenant::query()->count())->toBe(2);

    // The workspaces got their default rooms; the existing admin is untouched
    expect(ChatRoom::query()->withoutGlobalScopes()->where('tenant_id', $clinic->id)->pluck('slug')->sort()->values()->all())
        ->toBe(['admins', 'announcements', 'doctors', 'general'])
        ->and($clinicAdmin->fresh()->isAdmin())->toBeTrue();

    // And the moved user can sign in
    $this->enableTokenIssuing();
    $this->postJson('/api/v1/auth/login', ['email' => $doctorUser->email, 'password' => 'password'])
        ->assertOk()->assertJsonPath('session.tenant.id', $clinic->id);
});
