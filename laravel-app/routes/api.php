<?php

use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\AppConfigController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Auth\ProfileController;
use App\Http\Controllers\Api\V1\Chat;
use App\Http\Controllers\Api\V1\Doctor;
use App\Http\Controllers\Api\V1\Marketplace;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
|
| Every authenticated route runs: auth:api → tenant → user.locale, so the
| tenant scope is active before route model binding (see bootstrap/app.php).
| Per-action permissions are declared on each controller via HasMiddleware.
|
| The same endpoints serve every theme. A standalone frontend sends
| X-App-Theme, which scopes login / register / forgot password to its theme
| and makes `tenant` refuse tokens of another theme. Feature routes also
| require a verified email (`verified`, toggled by AUTH_VERIFY_EMAIL).
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    Route::get('app', AppConfigController::class)->name('app');

    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('forgot-password', [PasswordResetController::class, 'forgot'])->name('password.email');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

        // Public: the emailed link can be opened on any device, the signature is the proof
        Route::post('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed:relative')
            ->name('verification.verify');
    });

    Route::middleware(['auth:api', 'tenant', 'user.locale', 'throttle:api'])->group(function () {

        // Session endpoints stay reachable before the email is verified
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])
                ->middleware('throttle:verification')
                ->name('verification.send');
        });
    });

    Route::middleware(['auth:api', 'tenant', 'verified', 'user.locale', 'throttle:api'])->group(function () {

        // Administration: available to every tenant type, gated by users.* / roles.* permissions
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::apiResource('users', UserController::class);
            Route::apiResource('roles', RoleController::class);
            Route::get('permissions', [PermissionController::class, 'index'])
                ->middleware('permission:roles.view|roles.create|roles.update')
                ->name('permissions.index');
        });

        // Live chat: available to every tenant type. Realtime events go through Reverb
        // (routes/channels.php); moderation rules live in App\Services\Chat\ChatModeration.
        Route::prefix('chat')->name('chat.')->middleware('permission:chat.view')->group(function () {
            Route::get('rooms', [Chat\RoomController::class, 'index'])->name('rooms.index');
            Route::post('rooms', [Chat\RoomController::class, 'store'])->name('rooms.store');
            Route::patch('rooms/{room}', [Chat\RoomController::class, 'update'])->name('rooms.update');
            Route::delete('rooms/{room}', [Chat\RoomController::class, 'destroy'])->name('rooms.destroy');
            Route::post('rooms/{room}/read', [Chat\RoomController::class, 'read'])->name('rooms.read');

            Route::get('rooms/{room}/messages', [Chat\MessageController::class, 'index'])->name('messages.index');
            Route::post('rooms/{room}/messages', [Chat\MessageController::class, 'store'])
                ->middleware('throttle:chat')
                ->name('messages.store');
            Route::delete('messages/{message}', [Chat\MessageController::class, 'destroy'])->name('messages.destroy');

            Route::get('members', Chat\MemberController::class)->name('members.index');

            Route::get('mutes', [Chat\MuteController::class, 'index'])->name('mutes.index');
            Route::post('mutes', [Chat\MuteController::class, 'store'])->name('mutes.store');
            Route::delete('mutes/{mute}', [Chat\MuteController::class, 'destroy'])->name('mutes.destroy');
        });

        Route::prefix('doctor')->name('doctor.')->middleware('tenant.type:doctor')->group(function () {
            Route::get('dashboard', Doctor\DashboardController::class)
                ->middleware('permission:appointments.view')
                ->name('dashboard');

            Route::get('reports/summary', [Doctor\ReportController::class, 'summary'])
                ->middleware('permission:reports.view')
                ->name('reports.summary');

            Route::prefix('presence')->name('presence.')->middleware('permission:presence.toggle')->group(function () {
                Route::get('/', [Doctor\PresenceController::class, 'show'])->name('show');
                Route::post('online', [Doctor\PresenceController::class, 'online'])->name('online');
                Route::post('heartbeat', [Doctor\PresenceController::class, 'heartbeat'])->name('heartbeat');
                Route::post('offline', [Doctor\PresenceController::class, 'offline'])->name('offline');
            });
            Route::get('presence/team', [Doctor\PresenceController::class, 'team'])
                ->middleware('permission:appointments.view')
                ->name('presence.team');

            Route::apiResource('patients', Doctor\PatientController::class);

            Route::post('appointments/{appointment}/check-in', [Doctor\AppointmentController::class, 'checkIn'])->name('appointments.check-in');
            Route::patch('appointments/{appointment}/status', [Doctor\AppointmentController::class, 'status'])->name('appointments.status');
            Route::apiResource('appointments', Doctor\AppointmentController::class);
        });

        Route::prefix('marketplace')->name('marketplace.')->middleware('tenant.type:marketplace')->group(function () {
            Route::get('dashboard', Marketplace\DashboardController::class)
                ->middleware('permission:products.view|orders.view')
                ->name('dashboard');

            Route::get('insights/top-products', [Marketplace\InsightController::class, 'topProducts'])
                ->middleware('permission:marketing.view')
                ->name('insights.top-products');

            Route::apiResource('products', Marketplace\ProductController::class);

            Route::patch('orders/{order}/status', [Marketplace\OrderController::class, 'status'])->name('orders.status');
            Route::apiResource('orders', Marketplace\OrderController::class)->except(['update', 'destroy']);
        });
    });
});
