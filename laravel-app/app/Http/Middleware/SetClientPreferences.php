<?php

namespace App\Http\Middleware;

use App\Support\ClientContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the client's locale (Accept-Language) and timezone (X-Timezone).
 *
 * Stored user preferences are applied later, once the user is authenticated,
 * by the helpers that read them (see user_timezone()) and by SetUserLocale.
 */
class SetClientPreferences
{
    public function __construct(private readonly ClientContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('tenancy.locales');
        $locale = $request->getPreferredLanguage($supported) ?? $supported[0];

        app()->setLocale($locale);

        $timezone = $request->header('X-Timezone');
        $this->context->setTimezone(ClientContext::isValidTimezone($timezone) ? $timezone : null);

        $response = $next($request);
        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }
}
