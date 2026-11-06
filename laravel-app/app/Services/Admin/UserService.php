<?php

namespace App\Services\Admin;

use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * @param  array<string, mixed>  $data  validated input; `role_ids` already restricted to the tenant
     */
    public function create(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create(Arr::except($data, ['role_ids']));
            $user->syncRoles($data['role_ids'] ?? []);

            return $user;
        });

        // Team members confirm their address too; a mail outage must not fail the creation
        rescue(fn () => $user->sendEmailVerificationNotification());

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, User $actor, array $data): User
    {
        return DB::transaction(function () use ($user, $actor, $data) {
            if ($user->is($actor) && array_key_exists('is_active', $data) && ! $data['is_active']) {
                throw new BusinessRuleException(__('messages.cannot_deactivate_self'), 'self_deactivation', 422);
            }

            if (array_key_exists('role_ids', $data)) {
                $this->guardLastAdmin($user, $data['role_ids']);
            }

            if (array_key_exists('is_active', $data) && ! $data['is_active']) {
                $this->guardLastAdmin($user, []);
            }

            $attributes = Arr::except($data, ['role_ids']);

            // An empty password field means "keep the current one"
            if (blank($attributes['password'] ?? null)) {
                unset($attributes['password']);
            }

            $user->update($attributes);

            if (array_key_exists('role_ids', $data)) {
                $user->syncRoles($data['role_ids']);
            }

            return $user;
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new BusinessRuleException(__('messages.cannot_delete_self'), 'self_deletion', 422);
        }

        DB::transaction(function () use ($user) {
            $this->guardLastAdmin($user, []);
            $user->tokens()->update(['revoked' => true]);
            $user->delete();
        });
    }

    /**
     * Refuse changes that would leave the tenant without an active administrator.
     *
     * @param  list<int>  $nextRoleIds
     */
    private function guardLastAdmin(User $user, array $nextRoleIds): void
    {
        $adminRoleIds = Role::query()->where('is_admin', true)->pluck('id');

        $isAdminNow = $user->roles()->whereIn('roles.id', $adminRoleIds)->exists();
        $staysAdmin = $adminRoleIds->intersect($nextRoleIds)->isNotEmpty();

        if (! $isAdminNow || $staysAdmin) {
            return;
        }

        $otherAdmins = User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('roles.id', $adminRoleIds))
            ->lockForUpdate()
            ->count();

        if ($otherAdmins === 0) {
            throw new BusinessRuleException(__('messages.last_admin'), 'last_admin', 422);
        }
    }
}
