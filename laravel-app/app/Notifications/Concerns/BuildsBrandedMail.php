<?php

namespace App\Notifications\Concerns;

use App\Mail\Branding\MailBrandResolver;
use App\Mail\Branding\MailCopy;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Builds a call-to-action email in the recipient's theme (clinic, shop...).
 *
 * Rendered while the notification runs in the recipient's locale (User implements
 * HasLocalePreference), so wording, dates and durations follow their language.
 * The MailMessage keeps greeting/intro/action set as well, so `actionUrl` and
 * `toArray()` stay meaningful for tests and other channels.
 */
trait BuildsBrandedMail
{
    protected function brandedMail(User $user, string $template, string $url, ?int $expiresInMinutes = null): MailMessage
    {
        $resolver = app(MailBrandResolver::class);
        $brand = $resolver->for($user);
        $copy = new MailCopy($template, $brand->theme, ['organization' => $brand->organization, 'name' => $user->name]);

        $expiresIn = $expiresInMinutes !== null
            ? CarbonInterval::minutes($expiresInMinutes)->cascade()->locale(app()->getLocale())->forHumans(['parts' => 2])
            : null;

        $message = (new MailMessage)
            ->subject($copy->get('subject'))
            ->greeting($copy->common('greeting'))
            ->line($copy->get('intro'))
            ->action($copy->get('cta'), $url)
            ->view(
                ['html' => $resolver->view($template, $brand), 'text' => 'mail.text.action'],
                ['brand' => $brand, 'copy' => $copy, 'url' => $url, 'expiresIn' => $expiresIn, 'recipient' => $user],
            );

        // Sent "as" the organisation, from the configured (authenticated) mailbox
        if (filled(config('mail.from.address'))) {
            $message->from(config('mail.from.address'), $brand->organization);
        }

        return $message;
    }
}
