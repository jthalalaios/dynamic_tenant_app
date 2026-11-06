<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\Themes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant to the request, provided its theme is
 * served by this backend and matches the calling frontend (X-App-Theme).
 *
 * Must run after authentication and before route model binding (see the
 * middleware priority in bootstrap/app.php), otherwise bound models would be
 * resolved without the tenant scope.
 */
class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly Themes $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        abort_if($user === null, Response::HTTP_UNAUTHORIZED);
        abort_if($tenant === null || ! $tenant->is_active, Response::HTTP_FORBIDDEN, __('messages.tenant_unavailable'));
        abort_unless($user->is_active, Response::HTTP_FORBIDDEN, __('messages.account_disabled'));
        // Theme disabled on this backend (TENANCY_THEMES) or a token used from another theme's frontend
        abort_unless($this->themes->allows($tenant->type), Response::HTTP_FORBIDDEN, __('messages.theme_mismatch'));

        $this->context->set($tenant);

        return $next($request);
    }
}
