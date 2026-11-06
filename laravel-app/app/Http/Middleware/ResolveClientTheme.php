<?php

namespace App\Http\Middleware;

use App\Enums\TenantType;
use App\Support\ClientContext;
use App\Tenancy\Themes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the X-App-Theme header a standalone frontend sends (doctor | marketplace).
 *
 * An unknown theme, or one this backend does not serve (TENANCY_THEMES), is
 * refused up front so a misconfigured frontend fails loudly.
 */
class ResolveClientTheme
{
    public function __construct(
        private readonly ClientContext $context,
        private readonly Themes $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $value = $request->header(config('tenancy.themes.header'));

        if (is_string($value) && $value !== '') {
            $theme = TenantType::tryFrom(strtolower(trim($value)));

            abort_unless($this->themes->isEnabled($theme), Response::HTTP_NOT_FOUND, __('messages.theme_unavailable'));

            $this->context->setTheme($theme);
        }

        return $next($request);
    }
}
