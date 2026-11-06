<?php

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantType;
use App\Http\Middleware\EnsureUserIsOnline;
use App\Http\Middleware\ResolveClientTheme;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SetClientPreferences;
use App\Http\Middleware\SetUserLocale;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Channel auth for Echo at POST /api/v1/broadcasting/auth, with the same guard,
    // tenant and theme checks as the rest of the token API
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'prefix' => 'api/v1',
        'middleware' => ['api', 'auth:api', 'tenant', 'verified'],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // Locale first, so a refused X-App-Theme is reported in the client's language
        $middleware->api(prepend: [SetClientPreferences::class, ResolveClientTheme::class]);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'tenant.type' => EnsureTenantType::class,
            'permission' => EnsurePermission::class,
            'role' => EnsureRole::class,
            'online' => EnsureUserIsOnline::class,
            'user.locale' => SetUserLocale::class,
            // Replaces Laravel's redirecting `verified` with a JSON one for the token API
            'verified' => EnsureEmailIsVerified::class,
        ]);

        // The tenant scope must be active before implicit route model binding resolves models,
        // otherwise /patients/{id} could load another tenant's record.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);

        // Pure token API: unauthenticated requests get a 401, never a redirect to a login page
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Uniform, non-leaky messages for common HTTP errors on the API
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $message = $e->getPrevious() instanceof ModelNotFoundException ? __('messages.not_found') : __('messages.route_not_found');

                return response()->json(['message' => $message], 404);
            }
        });

        // Expired or tampered email verification links
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('messages.verification_link_invalid'), 'code' => 'invalid_signature'], 403);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => __('messages.unauthenticated')], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage() ?: __('messages.forbidden')], 403);
            }
        });
    })->create();
