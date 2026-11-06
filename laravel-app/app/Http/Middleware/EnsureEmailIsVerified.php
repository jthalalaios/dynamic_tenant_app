<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unverified users from the app's features (JSON 403 with code `email_unverified`).
 *
 * A no-op when AUTH_VERIFY_EMAIL is false.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->mustVerifyEmail()) {
            return response()->json([
                'message' => __('messages.email_unverified'),
                'code' => 'email_unverified',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
