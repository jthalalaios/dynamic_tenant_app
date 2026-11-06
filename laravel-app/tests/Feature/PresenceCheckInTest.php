<?php

use App\Enums\AppointmentStatus;
use App\Enums\TenantType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\PresenceSession;

beforeEach(function () {
    $this->clinic = $this->createTenant(TenantType::Doctor);
    $this->receptionist = $this->createUser($this->clinic, 'receptionist');
    $this->appointment = Appointment::factory()
        ->for(Patient::factory()->for($this->clinic))
        ->create(['tenant_id' => $this->clinic->id]);
});

it('refuses check-in while the user is offline', function () {
    $this->actingAsUser($this->receptionist)
        ->postJson("/api/v1/doctor/appointments/{$this->appointment->id}/check-in")
        ->assertConflict()
        ->assertJsonPath('code', 'user_offline');

    expect($this->appointment->fresh()->status)->toBe(AppointmentStatus::Scheduled);
});

it('checks a patient in once the user is online', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online')
        ->assertOk()
        ->assertJsonPath('data.online', true);

    $this->actingAsUser($this->receptionist)
        ->postJson("/api/v1/doctor/appointments/{$this->appointment->id}/check-in")
        ->assertOk()
        ->assertJsonPath('data.status', 'checked_in')
        ->assertJsonPath('data.checked_in_by.id', $this->receptionist->id);
});

it('treats a session without a recent heartbeat as offline', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');

    $this->travel(config('tenancy.presence.timeout_minutes') + 1)->minutes();

    $this->actingAsUser($this->receptionist)->getJson('/api/v1/doctor/presence')->assertJsonPath('data.online', false);
    $this->actingAsUser($this->receptionist)
        ->postJson("/api/v1/doctor/appointments/{$this->appointment->id}/check-in")
        ->assertJsonPath('code', 'user_offline');
});

it('keeps the session alive with heartbeats and ends it when going offline', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');

    $this->travel(config('tenancy.presence.timeout_minutes') - 1)->minutes();
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/heartbeat')->assertJsonPath('data.online', true);
    $this->travel(config('tenancy.presence.timeout_minutes') - 1)->minutes();
    $this->actingAsUser($this->receptionist)->getJson('/api/v1/doctor/presence')->assertJsonPath('data.online', true);

    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/offline')->assertJsonPath('data.online', false);
    expect(PresenceSession::query()->withoutGlobalScopes()->whereNull('ended_at')->count())->toBe(0);
});

it('never opens two sessions for the same user', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');

    expect(PresenceSession::query()->withoutGlobalScopes()->where('user_id', $this->receptionist->id)->count())->toBe(1);
});

it('requires the presence.toggle permission to go online', function () {
    $clerk = $this->createUser($this->clinic);

    $this->actingAsUser($clerk)->postJson('/api/v1/doctor/presence/online')->assertForbidden();
});

it('follows the appointment state machine', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');

    $this->actingAsUser($this->receptionist)
        ->patchJson("/api/v1/doctor/appointments/{$this->appointment->id}/status", ['status' => 'completed'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_status_transition');

    $this->actingAsUser($this->receptionist)->postJson("/api/v1/doctor/appointments/{$this->appointment->id}/check-in")->assertOk();
    $this->actingAsUser($this->receptionist)->postJson("/api/v1/doctor/appointments/{$this->appointment->id}/check-in")
        ->assertJsonPath('code', 'invalid_status_transition');

    $this->actingAsUser($this->receptionist)
        ->patchJson("/api/v1/doctor/appointments/{$this->appointment->id}/status", ['status' => 'completed'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.allowed_transitions', []);
});

it('closes stale sessions from the scheduler command', function () {
    $this->actingAsUser($this->receptionist)->postJson('/api/v1/doctor/presence/online');
    $this->travel(config('tenancy.presence.timeout_minutes') + 1)->minutes();

    $this->artisan('presence:close-stale')->assertSuccessful();

    $session = PresenceSession::query()->withoutGlobalScopes()->first();
    expect($session->ended_at)->not->toBeNull()
        ->and($session->ended_at->equalTo($session->last_heartbeat_at))->toBeTrue();
});

it('shows today\'s queue on the dashboard in the viewer\'s timezone', function () {
    $this->receptionist->update(['timezone' => 'Europe/Athens']);

    $this->actingAsUser($this->receptionist)->getJson('/api/v1/doctor/dashboard')
        ->assertOk()
        ->assertJsonPath('data.stats.waiting', 1)
        ->assertJsonPath('data.queue.0.id', $this->appointment->id)
        ->assertJsonPath('data.queue.0.scheduled_at', $this->appointment->scheduled_at->copy()->setTimezone('Europe/Athens')->toIso8601String());
})->skip(fn () => now('Europe/Athens')->addHour()->isTomorrow(), 'The fixture appointment would fall on another local day');
