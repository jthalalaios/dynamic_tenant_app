<?php

namespace App\Services\Auth;

use App\Enums\TenantType;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\Workspaces;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

/**
 * Self-service sign up: joins the workspace of the chosen theme (the clinic or the
 * shop) with the least privileged role, tenancy.registration.role. Administrators
 * promote members from the Team page.
 */
class MemberRegistrar
{
    public function __construct(private readonly Workspaces $workspaces) {}

    /**
     * @param  array{type: string, name: string, email: string, password: string, timezone?: ?string, locale?: ?string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $tenant = $this->workspaces->for(TenantType::from($data['type']));

            if (! $tenant->is_active) {
                throw new BusinessRuleException(__('messages.registration_closed'), 'registration_closed', 422);
            }

            $user = User::query()->create([
                'tenant_id' => $tenant->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'timezone' => $data['timezone'] ?? null,
                'locale' => $data['locale'] ?? null,
            ]);

            $role = Role::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('slug', config('tenancy.registration.role'))
                ->firstOrFail();

            $user->syncRoles([$role->id]);

            return $user;
        });

        // Sends the verification email; a mail outage must not fail the sign up (the user can resend)
        rescue(fn () => event(new Registered($user)));

        return $user;
    }
}
