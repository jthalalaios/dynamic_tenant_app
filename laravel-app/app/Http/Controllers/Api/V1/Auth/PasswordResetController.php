<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Tenancy\TenantScope;
use App\Tenancy\Themes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request, Themes $themes): JsonResponse
    {
        $user = User::query()->withoutGlobalScope(TenantScope::class)->with('tenant')->where('email', $request->validated('email'))->first();

        // Only accounts of a theme this frontend serves get a link (a market frontend never mails clinic staff)
        if ($user !== null && $themes->allows($user->tenant?->type)) {
            Password::sendResetLink($request->only('email'));
        }

        // Identical response whether or not the email exists (no account enumeration)
        return response()->json(['message' => __('messages.reset_link_sent')]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                // Following the emailed link also proves ownership of the address
                $user->forceFill([
                    'password' => $password,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                // Sign out every device after a password reset
                $user->tokens()->update(['revoked' => true]);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => __($status)]);
    }
}
