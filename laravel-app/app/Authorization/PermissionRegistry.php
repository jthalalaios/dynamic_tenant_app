<?php

namespace App\Authorization;

use App\Enums\TenantType;
use Illuminate\Support\Collection;

/**
 * Read-only view over config/permissions.php.
 */
class PermissionRegistry
{
    /**
     * @return Collection<int, array{name: string, group: string, scope: string, description: string}>
     */
    public function all(): Collection
    {
        $definitions = [];

        foreach (config('permissions.permissions', []) as $scope => $groups) {
            foreach ($groups as $group => $permissions) {
                foreach ($permissions as $name => $description) {
                    $definitions[] = compact('name', 'group', 'scope', 'description');
                }
            }
        }

        return collect($definitions);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return $this->all()->pluck('name')->all();
    }

    public function exists(string $name): bool
    {
        return in_array($name, $this->names(), true);
    }

    /**
     * @return list<string>
     */
    public function namesFor(TenantType $type): array
    {
        return $this->all()
            ->whereIn('scope', $type->permissionScopes())
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * System roles seeded for each new tenant of the given type.
     *
     * @return array<string, array{name: string, is_admin: bool, permissions: list<string>}>
     */
    public function defaultRoles(TenantType $type): array
    {
        return config("permissions.roles.{$type->value}", []);
    }
}
