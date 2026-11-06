<?php

namespace App\Services\Admin;

use App\Authorization\PermissionCache;
use App\Exceptions\BusinessRuleException;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleService
{
    public function __construct(private readonly PermissionCache $cache) {}

    /**
     * @param  array{name: string, description?: ?string, is_admin?: bool, permissions?: list<string>}  $data
     */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::query()->create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'description' => $data['description'] ?? null,
                'is_admin' => $data['is_admin'] ?? false,
            ]);

            $this->syncPermissions($role, $data['permissions'] ?? []);

            return $role;
        });
    }

    /**
     * @param  array{name?: string, description?: ?string, is_admin?: bool, permissions?: list<string>}  $data
     */
    public function update(Role $role, array $data): Role
    {
        if ($role->is_system && array_key_exists('is_admin', $data) && $data['is_admin'] !== $role->is_admin) {
            throw new BusinessRuleException(__('messages.system_role_locked'), 'system_role_locked', 422);
        }

        return DB::transaction(function () use ($role, $data) {
            $role->fill(collect($data)->only(['name', 'description', 'is_admin'])->all())->save();

            if (array_key_exists('permissions', $data)) {
                $this->syncPermissions($role, $data['permissions']);
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw new BusinessRuleException(__('messages.system_role_locked'), 'system_role_locked', 422);
        }

        $role->delete();
    }

    /**
     * @param  list<string>  $names  already validated against the tenant's permission scopes
     */
    private function syncPermissions(Role $role, array $names): void
    {
        $role->permissions()->sync(Permission::query()->whereIn('name', $names)->pluck('id'));
        $this->cache->flushTenant($role->tenant_id);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;

        while (Role::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
