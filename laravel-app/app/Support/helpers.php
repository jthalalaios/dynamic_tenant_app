<?php

use App\Models\Tenant;
use App\Models\User;
use App\Support\ClientContext;
use App\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Global helpers
|--------------------------------------------------------------------------
|
| Thin, side-effect free accessors over the request-scoped tenant/client
| context and the authenticated user's ACL. Autoloaded via composer.json.
|
*/

if (! function_exists('tenant')) {
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->get();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('current_user')) {
    function current_user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}

if (! function_exists('is_admin')) {
    function is_admin(?User $user = null): bool
    {
        return (bool) ($user ?? current_user())?->isAdmin();
    }
}

if (! function_exists('has_permission')) {
    /**
     * True when the user holds at least one of the given permissions.
     *
     * @param  string|list<string>  $permissions
     */
    function has_permission(string|array $permissions, ?User $user = null): bool
    {
        $user ??= current_user();

        return $user !== null && $user->hasAnyPermission((array) $permissions);
    }
}

if (! function_exists('has_all_permissions')) {
    /**
     * @param  list<string>  $permissions
     */
    function has_all_permissions(array $permissions, ?User $user = null): bool
    {
        $user ??= current_user();

        return $user !== null && $user->hasAllPermissions($permissions);
    }
}

if (! function_exists('has_role')) {
    /**
     * @param  string|list<string>  $roles
     */
    function has_role(string|array $roles, ?User $user = null): bool
    {
        $user ??= current_user();

        return $user !== null && $user->hasRole($roles);
    }
}

if (! function_exists('user_timezone')) {
    /**
     * Timezone to present dates in: user preference > request header > tenant default > app default.
     */
    function user_timezone(?User $user = null): string
    {
        $candidates = [
            ($user ?? current_user())?->timezone,
            app(ClientContext::class)->timezone(),
            tenant()?->timezone,
        ];

        foreach ($candidates as $timezone) {
            if (ClientContext::isValidTimezone($timezone)) {
                return $timezone;
            }
        }

        return config('app.timezone', 'UTC');
    }
}

if (! function_exists('to_user_timezone')) {
    /**
     * Format a (UTC) date as ISO-8601 in the viewer's timezone, offset included.
     */
    function to_user_timezone(?CarbonInterface $date, ?string $timezone = null): ?string
    {
        return $date?->copy()->setTimezone($timezone ?? user_timezone())->toIso8601String();
    }
}

if (! function_exists('from_user_timezone')) {
    /**
     * Parse a client supplied date. Values without an explicit offset are read as the
     * viewer's local wall time; the result is always converted to UTC for storage.
     */
    function from_user_timezone(string $value, ?string $timezone = null): Carbon
    {
        return Carbon::parse($value, $timezone ?? user_timezone())->utc();
    }
}
