<?php

namespace App\Authorization;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role/permission API for the User model, backed by PermissionCache.
 *
 * @mixin User
 */
trait HasRoles
{
    /**
     * Snapshot memoised per cache version, so a long-lived model instance never serves stale grants.
     *
     * @var array{version: int, data: array{is_admin: bool, roles: list<string>, permissions: list<string>}}|null
     */
    private ?array $aclMemo = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * @return array{is_admin: bool, roles: list<string>, permissions: list<string>}
     */
    public function acl(): array
    {
        $cache = app(PermissionCache::class);
        $version = $cache->version($this->tenant_id);

        if ($this->aclMemo === null || $this->aclMemo['version'] !== $version) {
            $this->aclMemo = ['version' => $version, 'data' => $cache->snapshot($this)];
        }

        return $this->aclMemo['data'];
    }

    public function isAdmin(): bool
    {
        return $this->acl()['is_admin'];
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->acl()['permissions'];
    }

    /**
     * @return list<string>
     */
    public function roleSlugs(): array
    {
        return $this->acl()['roles'];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissionNames(), true);
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function hasAnyPermission(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  iterable<string>  $permissions
     */
    public function hasAllPermissions(iterable $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  string|iterable<string>  $roles
     */
    public function hasRole(string|iterable $roles): bool
    {
        $roles = is_string($roles) ? [$roles] : $roles;

        return array_intersect($this->roleSlugs(), [...$roles]) !== [];
    }

    /**
     * Replace the user's roles. Callers must pass role ids that belong to the user's tenant.
     *
     * @param  list<int>  $roleIds
     */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        app(PermissionCache::class)->flushTenant($this->tenant_id);
    }
}
