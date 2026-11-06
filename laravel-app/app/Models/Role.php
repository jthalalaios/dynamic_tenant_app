<?php

namespace App\Models;

use App\Authorization\PermissionCache;
use App\Tenancy\BelongsToTenant;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id', 'name', 'slug', 'description', 'is_admin', 'is_system'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use BelongsToTenant, HasFactory;

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Any change to a role (e.g. toggling is_admin) changes effective permissions of its users
        $flush = fn (self $role) => app(PermissionCache::class)->flushTenant($role->tenant_id);

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
