<?php

use App\Enums\TenantType;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->enableTokenIssuing();
    $this->tenant = $this->createTenant(TenantType::Doctor);
});

it('logs in with valid credentials and returns a bearer token plus the session', function () {
    $user = $this->createUser($this->tenant, 'doctor');

    $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('session.user.id', $user->id)
        ->assertJsonPath('session.tenant.type', 'doctor')
        ->assertJsonPath('session.access.is_admin', false)
        ->assertJsonPath('session.access.roles', ['doctor']);

    expect($response->json('access_token'))->toBeString()->not->toBeEmpty()
        ->and($response->json('session.access.permissions'))->toContain('appointments.check-in')
        ->not->toContain('patients.delete');

    // The issued JWT authenticates subsequent requests
    $this->withToken($response->json('access_token'))
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.user.email', $user->email);

    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects wrong credentials without revealing whether the email exists', function (string $email) {
    $this->createUser($this->tenant, 'doctor')->update(['email' => 'known@example.com']);

    $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'wrong-password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);
})->with(['known@example.com', 'unknown@example.com']);

it('refuses disabled accounts and inactive tenants', function () {
    $disabled = User::factory()->for($this->tenant)->inactive()->create();

    $this->postJson('/api/v1/auth/login', ['email' => $disabled->email, 'password' => 'password'])
        ->assertUnprocessable();

    $inactiveTenant = Tenant::factory()->create(['is_active' => false]);
    $user = User::factory()->for($inactiveTenant)->create();

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
        ->assertUnprocessable();
});

it('revokes the token on logout', function () {
    $user = $this->createUser($this->tenant, 'doctor');
    $token = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->json('access_token');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    // Force the guard to re-resolve the user from the token on the next request
    app('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns a JSON 401 for unauthenticated requests', function () {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized()
        ->assertJsonStructure(['message']);
});

it('registers into the theme workspace as a chat-only member', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'type' => 'marketplace',
        'name' => 'Newcomer',
        'email' => 'new@sunrise.test',
        'password' => 'Str0ng-Passw0rd!',
        'timezone' => 'Europe/Athens',
    ])->assertCreated()
        ->assertJsonPath('session.tenant.type', 'marketplace')
        ->assertJsonPath('session.tenant.slug', config('tenancy.workspaces.marketplace.slug'))
        ->assertJsonPath('session.access.is_admin', false)
        ->assertJsonPath('session.access.roles', ['member'])
        ->assertJsonPath('session.access.permissions', ['chat.view']);

    // A second sign-up joins the same workspace: one organization per theme
    $this->postJson('/api/v1/auth/register', [
        'type' => 'marketplace', 'name' => 'Second', 'email' => 'second@sunrise.test', 'password' => 'Str0ng-Passw0rd!',
    ])->assertCreated()->assertJsonPath('session.tenant.id', $response->json('session.tenant.id'));

    $workspace = Tenant::query()->findOrFail($response->json('session.tenant.id'));
    expect(Tenant::query()->where('type', 'marketplace')->where('slug', $workspace->slug)->count())->toBe(1)
        ->and($workspace->roles()->pluck('slug')->sort()->values()->all())->toBe(['admin', 'manager', 'member', 'sales']);
});

it('ignores a sent organization name: there is nothing to create', function () {
    $this->postJson('/api/v1/auth/register', [
        'organization' => 'My Own Clinic', 'type' => 'doctor', 'name' => 'Doc',
        'email' => 'doc@own.test', 'password' => 'Str0ng-Passw0rd!',
    ])->assertCreated()->assertJsonPath('session.tenant.slug', config('tenancy.workspaces.doctor.slug'));

    expect(Tenant::query()->where('name', 'My Own Clinic')->exists())->toBeFalse();
});

it('updates profile preferences and rejects invalid timezones', function () {
    $user = $this->createUser($this->tenant, 'doctor');

    $this->actingAsUser($user)
        ->patchJson('/api/v1/auth/profile', ['timezone' => 'Europe/Athens', 'locale' => 'el'])
        ->assertOk()
        ->assertJsonPath('data.user.timezone', 'Europe/Athens')
        ->assertJsonPath('data.preferences.locale', 'el');

    $this->actingAsUser($user)
        ->patchJson('/api/v1/auth/profile', ['timezone' => 'Mars/Olympus'])
        ->assertJsonValidationErrors('timezone');
});

it('throttles repeated login attempts for the same email', function () {
    foreach (range(1, 5) as $_) {
        $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'guess'])->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', ['email' => 'victim@example.com', 'password' => 'guess'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After');
});
