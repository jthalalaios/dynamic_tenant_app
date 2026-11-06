<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `permission:a.view|a.update` passes when the user holds any of the listed permissions.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        abort_unless(
            has_permission(explode('|', $permissions), $request->user()),
            Response::HTTP_FORBIDDEN,
            __('messages.forbidden'),
        );

        return $next($request);
    }
}
