<?php

namespace App\Authorization;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class PermissionSynchronizer
{
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly PermissionCache $cache,
    ) {}

    /**
     * Upsert the registry into the permissions table and prune removed entries.
     *
     * @return array{synced: int, pruned: int}
     */
    public function syncPermissions(): array
    {
        $definitions = $this->registry->all();
        $now = now();

        return DB::transaction(function () use ($definitions, $now) {
            Permission::query()->upsert(
                $definitions->map(fn (array $p) => [...$p, 'created_at' => $now, 'updated_at' => $now])->all(),
                ['name'],
                ['group', 'scope', 'description', 'updated_at'],
            );

            $pruned = Permission::query()->whereNotIn('name', $definitions->pluck('name'))->delete();

            // Every tenant's cached grants may reference changed or removed permissions
            Tenant::query()->pluck('id')->each(fn (int $id) => $this->cache->flushTenant($id));

            return ['synced' => $definitions->count(), 'pruned' => $pruned];
        });
    }

    /**
     * Create the system roles a tenant is missing (e.g. one added to config later),
     * leaving existing roles and their customised permissions untouched.
     */
    public function provisionMissingRoles(Tenant $tenant): void
    {
        $existing = Role::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('slug')->all();
        $permissionIds = Permission::query()->pluck('id', 'name');
        $created = false;

        foreach ($this->registry->defaultRoles($tenant->type) as $slug => $definition) {
            if (in_array($slug, $existing, true)) {
                continue;
            }

            $role = Role::query()->withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'slug' => $slug,
                'name' => $definition['name'],
                'is_admin' => $definition['is_admin'],
                'is_system' => true,
            ]);
            $role->permissions()->sync(
                collect($definition['permissions'])->map(fn (string $name) => $permissionIds[$name] ?? null)->filter()->values()->all()
            );
            $created = true;
        }

        if ($created) {
            $this->cache->flushTenant($tenant->id);
        }
    }

    /**
     * Create (or refresh) the system roles a tenant of this type starts with.
     */
    public function provisionTenantRoles(Tenant $tenant): void
    {
        $permissionIds = Permission::query()->pluck('id', 'name');

        DB::transaction(function () use ($tenant, $permissionIds) {
            foreach ($this->registry->defaultRoles($tenant->type) as $slug => $definition) {
                $role = Role::query()->withoutGlobalScopes()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'slug' => $slug],
                    ['name' => $definition['name'], 'is_admin' => $definition['is_admin'], 'is_system' => true],
                );

                $role->permissions()->sync(
                    collect($definition['permissions'])->map(fn (string $name) => $permissionIds[$name] ?? null)->filter()->values()->all()
                );
            }
        });

        $this->cache->flushTenant($tenant->id);
    }
}
