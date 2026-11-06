<?php

namespace App\Tenancy;

use App\Authorization\PermissionSynchronizer;
use App\Enums\TenantType;
use App\Models\Tenant;
use LogicException;

/**
 * The single organization (tenant) of each theme: everyone who signs up for the
 * clinic joins the clinic workspace, everyone who signs up for the shop joins the
 * shop workspace. Configured in config('tenancy.workspaces').
 */
class Workspaces
{
    public function __construct(private readonly PermissionSynchronizer $synchronizer) {}

    public function slugFor(TenantType $type): string
    {
        $slug = config("tenancy.workspaces.{$type->value}.slug");

        if (! is_string($slug) || $slug === '') {
            throw new LogicException("No workspace configured for the {$type->value} theme (tenancy.workspaces).");
        }

        return $slug;
    }

    /**
     * The theme's workspace, created (with its system roles) when it does not exist yet.
     * Safe under concurrent sign-ups: the slug is unique.
     */
    public function for(TenantType $type): Tenant
    {
        $slug = $this->slugFor($type);
        $tenant = Tenant::query()->withTrashed()->where('slug', $slug)->first();

        if ($tenant?->trashed()) {
            $tenant->restore();
        }

        $tenant ??= Tenant::query()->createOrFirst(['slug' => $slug], [
            'name' => config("tenancy.workspaces.{$type->value}.name") ?: ucfirst($type->value),
            'type' => $type,
            'timezone' => config('tenancy.workspaces.timezone') ?: config('app.timezone'),
            'locale' => config('app.locale'),
            // Set explicitly: a freshly created model does not read back column defaults
            'is_active' => true,
        ]);

        if ($tenant->type !== $type) {
            throw new LogicException("Workspace '{$slug}' is a {$tenant->type->value} tenant, not {$type->value}.");
        }

        // New workspace, or a role added to config since: create only what is missing
        $this->synchronizer->provisionMissingRoles($tenant);

        return $tenant;
    }

    public function isWorkspace(Tenant $tenant): bool
    {
        return $tenant->slug === config("tenancy.workspaces.{$tenant->type->value}.slug");
    }
}
