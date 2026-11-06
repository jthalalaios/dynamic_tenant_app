<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Tenancy\TenantScope;
use App\Tenancy\Themes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private readonly Themes $themes) {}

    /**
     * Verify credentials and issue a Passport personal access token (a signed JWT).
     *
     * @throws ValidationException
     */
    public function login(string $email, string $password): IssuedToken
    {
        // Emails are globally unique; never let a leftover tenant context hide an account
        $user = User::query()->withoutGlobalScope(TenantScope::class)->with('tenant')->where('email', $email)->first();

        // Same error for unknown email, wrong password and accounts of another theme
        // (e.g. a clinic login on the market frontend), so accounts cannot be enumerated
        $valid = $user !== null && Hash::check($password, $user->password);

        if (! $valid || ! $this->themes->allows($user->tenant?->type)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => __('messages.account_disabled')]);
        }

        if ($user->tenant === null || ! $user->tenant->is_active) {
            throw ValidationException::withMessages(['email' => __('messages.tenant_unavailable')]);
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return new IssuedToken($user, $user->createToken(config('tenancy.auth.token_name')));
    }

    public function logout(User $user): void
    {
        $user->token()?->revoke();
    }
}
