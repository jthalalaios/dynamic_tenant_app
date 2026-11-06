<?php

use App\Enums\TenantType;
use App\Models\User;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->enableTokenIssuing();
    $this->tenant = $this->createTenant(TenantType::Doctor);
});

/** Turn the emailed frontend link into the API call the frontend makes */
function verificationApiUrl(string $frontendLink): string
{
    $path = parse_url($frontendLink, PHP_URL_PATH);              // /verify-email/{id}/{hash}
    $query = parse_url($frontendLink, PHP_URL_QUERY);            // expires=…&signature=…

    return '/api/v1/auth/email/verify/'.substr($path, strlen('/verify-email/')).'?'.$query;
}

it('emails a verification link pointing at the account theme frontend on sign up', function () {
    Notification::fake();
    config(['tenancy.themes.frontend_urls.marketplace' => 'https://market.example.test']);

    $this->postJson('/api/v1/auth/register', [
        'type' => 'marketplace', 'name' => 'Owner',
        'email' => 'owner@shop.test', 'password' => 'Str0ng-Passw0rd!',
    ])->assertCreated()
        ->assertJsonPath('session.user.must_verify_email', true)
        ->assertJsonPath('session.user.email_verified_at', null);

    $user = User::query()->where('email', 'owner@shop.test')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
        return str_starts_with($notification->toMail($user)->actionUrl, "https://market.example.test/verify-email/{$user->id}/");
    });
});

it('lets unverified users reach their session but not the app features', function () {
    $user = User::factory()->for($this->tenant)->withRoles('doctor')->unverified()->create();

    $this->actingAsUser($user)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.must_verify_email', true);
    $this->actingAsUser($user)->getJson('/api/v1/doctor/patients')
        ->assertForbidden()
        ->assertJsonPath('code', 'email_unverified');
});

it('verifies the email from the signed link, without a session', function () {
    Notification::fake();
    $user = User::factory()->for($this->tenant)->withRoles('doctor')->unverified()->create();
    $user->sendEmailVerificationNotification();

    $link = null;
    Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $n) use ($user, &$link) {
        $link = $n->toMail($user)->actionUrl;

        return true;
    });

    $this->postJson(verificationApiUrl($link))->assertOk()->assertJsonPath('verified', true);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAsUser($user->fresh())->getJson('/api/v1/doctor/patients')->assertOk();

    // Re-opening the link is harmless
    $this->postJson(verificationApiUrl($link))->assertOk();
});

it('rejects tampered or expired links', function () {
    $user = User::factory()->for($this->tenant)->unverified()->create();
    $hash = sha1($user->email);

    $this->postJson("/api/v1/auth/email/verify/{$user->id}/{$hash}?expires=9999999999&signature=forged")
        ->assertForbidden()
        ->assertJsonPath('code', 'invalid_signature');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('rejects a validly signed link for a different email hash', function () {
    $user = User::factory()->for($this->tenant)->unverified()->create();
    $url = URL::temporarySignedRoute('api.v1.auth.verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('other@example.com')], absolute: false);

    $this->postJson($url)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the link to signed-in unverified users only', function () {
    Notification::fake();
    $unverified = User::factory()->for($this->tenant)->unverified()->create();
    $verified = User::factory()->for($this->tenant)->create();

    $this->actingAsUser($unverified)->postJson('/api/v1/auth/email/resend')->assertOk()->assertJsonPath('verified', false);
    Notification::assertSentTo($unverified, VerifyEmailNotification::class);

    $this->actingAsUser($verified)->postJson('/api/v1/auth/email/resend')->assertOk()->assertJsonPath('verified', true);
    Notification::assertNotSentTo($verified, VerifyEmailNotification::class);
});

it('reports a mail outage on resend instead of crashing', function () {
    // Nothing listens on port 1: the SMTP transport fails like a down or misconfigured server
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
    $user = User::factory()->for($this->tenant)->unverified()->create();

    $this->actingAsUser($user)->postJson('/api/v1/auth/email/resend')
        ->assertStatus(503)
        ->assertJsonPath('code', 'mail_unavailable')
        ->assertJsonPath('message', __('messages.verification_mail_failed'));
});

it('can be switched off entirely', function () {
    Notification::fake();
    config(['tenancy.auth.verify_email' => false]);
    $user = User::factory()->for($this->tenant)->withRoles('doctor')->unverified()->create();

    $this->actingAsUser($user)->getJson('/api/v1/doctor/patients')->assertOk();
    $this->actingAsUser($user)->postJson('/api/v1/auth/email/resend')->assertJsonPath('verified', true);
    Notification::assertNothingSent();
});
