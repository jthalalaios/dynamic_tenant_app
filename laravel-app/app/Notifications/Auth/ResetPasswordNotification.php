<?php

namespace App\Notifications\Auth;

use App\Models\User;
use App\Notifications\Concerns\BuildsBrandedMail;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset in the recipient's theme. The link (pointing at the theme's
 * frontend) is still built by ResetPassword::createUrlUsing in AppServiceProvider.
 */
class ResetPasswordNotification extends ResetPassword
{
    use BuildsBrandedMail;

    /**
     * @param  User  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');

        return $this->brandedMail(
            $notifiable,
            'reset-password',
            $this->resetUrl($notifiable),
            (int) config("auth.passwords.{$broker}.expire"),
        );
    }
}
