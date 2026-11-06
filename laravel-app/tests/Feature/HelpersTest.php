<?php

use App\Enums\TenantType;
use App\Support\ClientContext;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

it('converts between UTC storage and the viewer timezone', function () {
    $utc = Carbon::parse('2026-07-01 09:00:00', 'UTC');

    expect(to_user_timezone($utc, 'Europe/Athens'))->toBe('2026-07-01T12:00:00+03:00')
        ->and(to_user_timezone(null))->toBeNull()
        // Wall time without an offset is read in the viewer's timezone
        ->and(from_user_timezone('2026-07-01 12:00', 'Europe/Athens')->toIso8601String())->toBe('2026-07-01T09:00:00+00:00')
        // An explicit offset always wins
        ->and(from_user_timezone('2026-07-01T12:00:00+00:00', 'Europe/Athens')->toIso8601String())->toBe('2026-07-01T12:00:00+00:00');
});

it('resolves the timezone by precedence: user, header, tenant, app', function () {
    $tenant = $this->createTenant(TenantType::Doctor, ['timezone' => 'Asia/Tokyo']);
    $user = $this->createUser($tenant);

    expect(user_timezone($user))->toBe('UTC');

    app(TenantContext::class)->set($tenant);
    expect(user_timezone($user))->toBe('Asia/Tokyo');

    app(ClientContext::class)->setTimezone('America/New_York');
    expect(user_timezone($user))->toBe('America/New_York');

    $user->timezone = 'Europe/Athens';
    expect(user_timezone($user))->toBe('Europe/Athens');
});

it('reads locale and timezone from request headers', function () {
    $user = $this->createUser($this->createTenant());

    $this->actingAsUser($user)
        ->withHeaders(['Accept-Language' => 'el-GR,el;q=0.9', 'X-Timezone' => 'Europe/Athens'])
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertHeader('Content-Language', 'el')
        ->assertJsonPath('data.preferences.timezone', 'Europe/Athens');

    // Invalid header values are ignored rather than trusted
    $this->actingAsUser($user)
        ->withHeaders(['X-Timezone' => 'Not/AZone'])
        ->getJson('/api/v1/auth/me')
        ->assertJsonPath('data.preferences.timezone', $user->tenant->timezone);
});
