<?php

namespace Database\Seeders;

use App\Authorization\PermissionSynchronizer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    // Model events stay enabled: tenant stamping and ACL cache invalidation depend on them

    /**
     * Seed the application's database.
     */
    public function run(PermissionSynchronizer $synchronizer): void
    {
        // Required in every environment: the permission catalogue
        $synchronizer->syncPermissions();

        if (! app()->isProduction()) {
            $this->call(DemoTenantSeeder::class);
        }
    }
}
