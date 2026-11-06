<?php

namespace App\Console\Commands;

use App\Authorization\PermissionSynchronizer;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('permissions:sync {--roles : Also refresh every tenant\'s system roles from config/permissions.php}')]
#[Description('Upsert config/permissions.php into the database')]
class SyncPermissions extends Command
{
    public function handle(PermissionSynchronizer $synchronizer): int
    {
        ['synced' => $synced, 'pruned' => $pruned] = $synchronizer->syncPermissions();
        $this->components->info("Synced {$synced} permissions, pruned {$pruned}.");

        if ($this->option('roles')) {
            Tenant::query()->each(fn (Tenant $tenant) => $synchronizer->provisionTenantRoles($tenant));
            $this->components->info('System roles refreshed for every tenant.');
        }

        return self::SUCCESS;
    }
}
