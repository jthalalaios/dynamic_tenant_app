<?php

namespace App\Authorization;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Caches each user's effective roles/permissions.
 *
 * Invalidation is version based: every tenant has a counter that is bumped on
 * any role, permission or assignment change, which orphans all cached entries
 * for that tenant at once. Works with every cache store (no tags required).
 */
class PermissionCache
{
    public function __construct(
        private readonly Cache $cache,
        private readonly PermissionRegistry $registry,
    ) {}

    /**
     * @return array{is_admin: bool, roles: list<string>, permissions: list<string>}
     */
    public function snapshot(User $user): array
    {
        $key = sprintf('acl:t%s:v%d:u%d', $user->tenant_id ?? 'none', $this->version($user->tenant_id), $user->getKey());

        return $this->cache->remember($key, config('permissions.cache_ttl'), fn () => $this->build($user));
    }

    public function version(?int $tenantId): int
    {
        return (int) $this->cache->get($this->versionKey($tenantId), 1);
    }

    public function flushTenant(?int $tenantId): void
    {
        $key = $this->versionKey($tenantId);

        // add() is a no-op when the key exists, so increment always has a base to work from
        $this->cache->add($key, 1);
        $this->cache->increment($key);
    }

    /**
     * @return array{is_admin: bool, roles: list<string>, permissions: list<string>}
     */
    private function build(User $user): array
    {
        $roles = $user->roles()->with('permissions:id,name')->get();
        $isAdmin = $roles->contains('is_admin', true);
        $tenantType = $user->tenant?->type;

        if ($isAdmin && $tenantType !== null) {
            $permissions = $this->registry->namesFor($tenantType);
        } else {
            $allowed = $tenantType ? $this->registry->namesFor($tenantType) : [];

            // Intersect with the registry so stale or foreign-scope rows can never grant access
            $permissions = $roles->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique()
                ->intersect($allowed)
                ->values()
                ->all();
        }

        sort($permissions);

        return [
            'is_admin' => $isAdmin,
            'roles' => $roles->pluck('slug')->values()->all(),
            'permissions' => $permissions,
        ];
    }

    private function versionKey(?int $tenantId): string
    {
        return 'acl:t'.($tenantId ?? 'none').':version';
    }
}
