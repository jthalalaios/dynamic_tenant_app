<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Public cards of chat participants (name, admin badge).
 *
 * Admin flags are resolved with one query per request: message lists show an
 * admin badge per author, and asking each author's ACL snapshot would cost a
 * cache round trip per message.
 *
 * Registered as a scoped singleton (see AppServiceProvider).
 */
class ChatDirectory
{
    /** @var array<int, true>|null */
    private ?array $adminIds = null;

    public function isAdmin(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        // Administrator = holds a role flagged is_admin in the current workspace
        $this->adminIds ??= DB::table('role_user')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.is_admin', true)
            ->when(tenant_id(), fn ($q, int $tenantId) => $q->where('roles.tenant_id', $tenantId))
            ->pluck('role_user.user_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();

        return isset($this->adminIds[$userId]);
    }

    /**
     * @return array{id: int, name: string, is_admin: bool}|null
     */
    public function card(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'is_admin' => $this->isAdmin($user->id),
        ];
    }
}
