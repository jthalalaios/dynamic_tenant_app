<?php

use App\Authorization\PermissionCache;
use App\Authorization\PermissionSynchronizer;
use App\Enums\TenantType;
use App\Models\Role;
use App\Models\Tenant;
use App\Services\Chat\ChatService;
use App\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One workspace per theme (config tenancy.workspaces). Organizations created by the
 * old "sign up = new organization" flow are folded into their theme's workspace:
 *
 * 1. their users move to the workspace and become members (tenancy.registration.role),
 *    keeping their login, email verification and preferences;
 * 2. the extra organizations are deleted with everything still scoped to them
 *    (roles, chat rooms and messages...; cascading foreign keys).
 *
 * Also provisions the workspaces' new system roles (member) and default chat rooms.
 * Nothing happens on a fresh database (no workspace yet). Irreversible by nature.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionSynchronizer::class)->syncPermissions();

        foreach (TenantType::cases() as $type) {
            $slug = config("tenancy.workspaces.{$type->value}.slug");
            $workspace = $slug ? Tenant::query()->withTrashed()->where('slug', $slug)->where('type', $type)->first() : null;

            if ($workspace === null) {
                continue;
            }

            app(PermissionSynchronizer::class)->provisionMissingRoles($workspace);

            $memberRoleId = Role::query()->withoutGlobalScopes()
                ->where('tenant_id', $workspace->id)
                ->where('slug', config('tenancy.registration.role'))
                ->value('id');

            Tenant::query()->withTrashed()
                ->where('type', $type)
                ->whereKeyNot($workspace->id)
                ->each(function (Tenant $extra) use ($workspace, $memberRoleId) {
                    DB::transaction(function () use ($extra, $workspace, $memberRoleId) {
                        $userIds = DB::table('users')->where('tenant_id', $extra->id)->pluck('id');

                        if ($userIds->isNotEmpty()) {
                            // Their old roles belong to the organization being deleted
                            DB::table('role_user')->whereIn('user_id', $userIds)->delete();
                            DB::table('users')->whereIn('id', $userIds)->update(['tenant_id' => $workspace->id, 'updated_at' => now()]);
                            DB::table('role_user')->insert($userIds->map(fn ($id) => ['role_id' => $memberRoleId, 'user_id' => $id])->all());
                        }

                        $extra->forceDelete();
                    });
                });

            app(PermissionCache::class)->flushTenant($workspace->id);

            // The workspace's default rooms (Global, Announcements, Admins, role rooms)
            app(TenantContext::class)->runAs($workspace, fn () => app(ChatService::class)->provisionDefaultRooms());
        }
    }

    public function down(): void
    {
        // Merged organizations cannot be split back apart.
    }
};
