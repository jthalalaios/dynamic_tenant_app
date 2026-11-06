<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `role:admin|doctor` passes when the user has any of the listed role slugs.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        abort_unless(has_role(explode('|', $roles), $request->user()), Response::HTTP_FORBIDDEN, __('messages.forbidden'));

        return $next($request);
    }
}
