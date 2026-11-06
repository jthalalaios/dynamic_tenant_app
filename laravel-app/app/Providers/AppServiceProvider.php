<?php

namespace App\Providers;

use App\Authorization\PermissionRegistry;
use App\Models\User;
use App\Services\Chat\ChatDirectory;
use App\Support\ClientContext;
use App\Tenancy\TenantContext;
use App\Tenancy\Themes;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: fresh per request / queued job, never leaking a tenant across them
        $this->app->scoped(TenantContext::class);
        $this->app->scoped(ClientContext::class);
        $this->app->scoped(Themes::class);
        $this->app->scoped(ChatDirectory::class);
        $this->app->singleton(PermissionRegistry::class);

        // Token-only API: the OAuth authorization server routes are not exposed
        Passport::ignoreRoutes();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::personalAccessTokensExpireIn(now()->addMinutes(config('tenancy.auth.token_ttl_minutes')));

        // Lets $user->can('patients.view'), Gate::allows() and `can:` middleware use the dynamic ACL
        Gate::before(function (User $user, string $ability) {
            return app(PermissionRegistry::class)->exists($ability) ? $user->hasPermission($ability) : null;
        });

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('auth-ip:'.$request->ip()),
            Limit::perMinute(5)->by('auth-email:'.strtolower((string) $request->input('email')).'|'.$request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Chat messages per member; also caps how fast a client can flood a room
        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(30)->by('chat:'.($request->user()?->id ?: $request->ip())));

        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(3)->by('verify:'.($request->user()?->id ?: $request->ip())));

        // Emailed links open the frontend of the account's own theme (FRONTEND_URL_DOCTOR / _MARKETPLACE)
        ResetPassword::createUrlUsing(fn (User $user, string $token) => app(Themes::class)->frontendUrl($user->tenant?->type)
            .'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->getEmailForPasswordReset()]));

        // Sign the API route (relative, so it survives proxies / differing hosts), then hand its
        // parameters to the frontend page, which posts them back to that route
        VerifyEmail::createUrlUsing(function (User $user) {
            $hash = sha1($user->getEmailForVerification());
            $signed = URL::temporarySignedRoute(
                'api.v1.auth.verification.verify',
                now()->addMinutes(config('tenancy.auth.verification_ttl_minutes')),
                ['id' => $user->getKey(), 'hash' => $hash],
                absolute: false,
            );
            parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

            return app(Themes::class)->frontendUrl($user->tenant?->type)
                ."/verify-email/{$user->getKey()}/{$hash}?".http_build_query($query);
        });
    }
}
