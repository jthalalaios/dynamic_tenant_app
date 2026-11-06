<?php

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current tenant and stamps tenant_id on create.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null && $context->has()) {
                $model->setAttribute('tenant_id', $context->id());
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function belongsToTenant(?int $tenantId): bool
    {
        return $tenantId !== null && (int) $this->getAttribute('tenant_id') === $tenantId;
    }
}
