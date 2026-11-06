<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationController extends Controller
{
    /**
     * Confirm an email from the emailed link. The frontend forwards the link's
     * id/hash/expires/signature here; the `signed:relative` middleware has
     * already checked the signature and expiry. No session needed, so the link
     * also works when opened on another device.
     */
    public function verify(Request $request, string $id, string $hash): JsonResponse
    {
        $user = User::query()->withoutGlobalScope(TenantScope::class)->find($id);

        abort_if(
            $user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash),
            Response::HTTP_FORBIDDEN,
            __('messages.verification_link_invalid'),
        );

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('messages.email_already_verified'), 'verified' => true]);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->json(['message' => __('messages.email_verified'), 'verified' => true]);
    }

    /**
     * Send a fresh verification link to the signed-in user.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->mustVerifyEmail()) {
            return response()->json(['message' => __('messages.email_already_verified'), 'verified' => true]);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportExceptionInterface $e) {
            // SMTP down or rejecting our credentials: say so, without leaking server details
            report($e);

            return response()->json(['message' => __('messages.verification_mail_failed'), 'code' => 'mail_unavailable'], 503);
        }

        return response()->json(['message' => __('messages.verification_link_sent'), 'verified' => false]);
    }
}
