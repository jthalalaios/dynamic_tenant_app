<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Overrides the request locale with the authenticated user's saved preference.
 */
class SetUserLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale;

        if ($locale !== null && in_array($locale, config('tenancy.locales'), true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
