<?php

namespace App\Http\Middleware;

use App\Enums\TenantType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to tenants of a given type: `tenant.type:doctor`.
 */
class EnsureTenantType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        abort_unless(tenant()?->isType(TenantType::from($type)), Response::HTTP_FORBIDDEN, __('messages.feature_unavailable'));

        return $next($request);
    }
}
