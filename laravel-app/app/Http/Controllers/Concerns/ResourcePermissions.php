<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Routing\Controllers\Middleware;

/**
 * Maps resource controller actions onto `<prefix>.<ability>` permissions.
 *
 * A controller implementing HasMiddleware returns
 *   self::permissionMiddleware('patients', ['status' => 'update'])
 * to guard index/show with patients.view, store with patients.create, etc.
 */
trait ResourcePermissions
{
    private const DEFAULT_ABILITIES = [
        'index' => 'view',
        'show' => 'view',
        'store' => 'create',
        'update' => 'update',
        'destroy' => 'delete',
    ];

    /**
     * @param  array<string, string>  $extra  additional action => ability pairs
     * @return list<Middleware>
     */
    protected static function permissionMiddleware(string $prefix, array $extra = []): array
    {
        $byAbility = [];

        foreach ([...self::DEFAULT_ABILITIES, ...$extra] as $action => $ability) {
            $byAbility[$ability][] = $action;
        }

        return array_map(
            fn (string $ability, array $actions) => new Middleware("permission:{$prefix}.{$ability}", only: $actions),
            array_keys($byAbility),
            $byAbility,
        );
    }
}
