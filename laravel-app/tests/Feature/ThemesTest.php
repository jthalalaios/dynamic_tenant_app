<?php

use App\Enums\TenantType;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->enableTokenIssuing();
    $this->clinic = $this->createTenant(TenantType::Doctor);
    $this->shop = $this->createTenant(TenantType::Marketplace);
    $this->doctor = $this->createUser($this->clinic, 'doctor');
    $this->seller = $this->createUser($this->shop, 'manager');
});

function themed(string $theme): array
{
    return ['X-App-Theme' => $theme];
}

it('lets a multi frontend (no theme header) sign in to every theme', function () {
    $this->postJson('/api/v1/auth/login', ['email' => $this->doctor->email, 'password' => 'password'])->assertOk();
    $this->postJson('/api/v1/auth/login', ['email' => $this->seller->email, 'password' => 'password'])->assertOk();
});

it('scopes login to the standalone frontend theme without revealing the account', function () {
    $this->postJson('/api/v1/auth/login', ['email' => $this->doctor->email, 'password' => 'password'], themed('doctor'))
        ->assertOk()
        ->assertJsonPath('session.tenant.type', 'doctor');

    // A clinic account on the market frontend gets the same error as wrong credentials
    $this->postJson('/api/v1/auth/login', ['email' => $this->doctor->email, 'password' => 'password'], themed('marketplace'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email' => __('auth.failed')]);
});

it('refuses tokens used from another theme frontend', function () {
    $this->actingAsUser($this->doctor)->getJson('/api/v1/auth/me', themed('doctor'))->assertOk();

    $this->actingAsUser($this->doctor)->getJson('/api/v1/auth/me', themed('marketplace'))
        ->assertForbidden()
        ->assertJsonPath('message', __('messages.theme_mismatch'));
});

it('rejects unknown theme headers', function () {
    $this->getJson('/api/v1/app', themed('bakery'))->assertNotFound();
});

it('registers on a standalone frontend without sending a type, and only for its theme', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Owner',
        'email' => 'owner@solo.test',
        'password' => 'Str0ng-Passw0rd!',
    ], themed('doctor'))
        ->assertCreated()
        ->assertJsonPath('session.tenant.type', 'doctor');

    $this->postJson('/api/v1/auth/register', [
        'type' => 'marketplace',
        'name' => 'Owner',
        'email' => 'owner@sneaky.test',
        'password' => 'Str0ng-Passw0rd!',
    ], themed('doctor'))->assertJsonValidationErrors('type');
});

it('only mails reset links to accounts of the frontend theme, pointing at that theme frontend', function () {
    Notification::fake();
    config(['tenancy.themes.frontend_urls.doctor' => 'https://clinic.example.test']);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->seller->email], themed('doctor'))->assertOk();
    Notification::assertNotSentTo($this->seller, ResetPasswordNotification::class);

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $this->doctor->email], themed('doctor'))->assertOk();
    Notification::assertSentTo($this->doctor, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
        return str_starts_with($notification->toMail($this->doctor)->actionUrl, 'https://clinic.example.test/reset-password?');
    });
});

describe('a backend serving a single theme (TENANCY_THEMES=doctor)', function () {
    beforeEach(fn () => config(['tenancy.themes.enabled' => ['doctor']]));

    it('advertises only that theme', function () {
        $this->getJson('/api/v1/app')->assertOk()->assertJsonPath('themes', ['doctor']);
        $this->getJson('/api/v1/app', themed('marketplace'))->assertNotFound();
    });

    it('locks out accounts and tokens of the disabled theme', function () {
        $this->postJson('/api/v1/auth/login', ['email' => $this->seller->email, 'password' => 'password'])
            ->assertUnprocessable();

        $this->actingAsUser($this->seller)->getJson('/api/v1/marketplace/products')->assertForbidden();
        $this->actingAsUser($this->doctor)->getJson('/api/v1/doctor/patients')->assertOk();
    });

    it('only registers members of that theme', function () {
        $this->postJson('/api/v1/auth/register', [
            'type' => 'marketplace', 'name' => 'O',
            'email' => 'o@shop.test', 'password' => 'Str0ng-Passw0rd!',
        ])->assertJsonValidationErrors('type');

        expect(User::query()->where('email', 'o@shop.test')->exists())->toBeFalse();
    });
});
