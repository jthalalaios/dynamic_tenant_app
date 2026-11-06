<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        // Outside a tenant context (console, seeders, platform jobs) queries are unscoped
        if ($context->has()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->id());
        }
    }
}
