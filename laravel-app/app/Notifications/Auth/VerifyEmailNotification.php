<?php

namespace App\Notifications\Auth;

use App\Models\User;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Email verification in the recipient's theme. The link itself (signed, pointing
 * at the theme's frontend) is still built by VerifyEmail::createUrlUsing in
 * AppServiceProvider.
 */
class VerifyEmailNotification extends VerifyEmail
{
    use BuildsBrandedMail;

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return $this->brandedMail(
            $notifiable,
            'verify-email',
            $this->verificationUrl($notifiable),
            (int) config('tenancy.auth.verification_ttl_minutes'),
        );
    }
}
