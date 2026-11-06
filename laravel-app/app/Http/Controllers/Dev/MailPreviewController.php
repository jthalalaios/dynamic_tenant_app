<?php

namespace App\Http\Controllers\Dev;

use App\Enums\TenantType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * Local-only preview of the branded emails, rendered for a real account of the theme:
 *
 *   /dev/mail/verify-email/doctor
 *   /dev/mail/reset-password/marketplace?lang=el
 *   /dev/mail/verify-email/doctor?format=text
 *
 * Registered in routes/web.php only when APP_ENV=local; nothing is sent.
 */
class MailPreviewController extends Controller
{
    public function __invoke(Request $request, string $template, string $theme): Response
    {
        abort_unless(App::environment('local'), 404);

        $type = TenantType::tryFrom($theme) ?? abort(404, 'Unknown theme.');
        $user = User::query()->withoutGlobalScopes()
            ->whereHas('tenant', fn ($q) => $q->where('type', $type))
            ->orderBy('id')
            ->first() ?? abort(404, "No {$type->value} account to preview with. Run the demo seeder first.");

        $notification = match ($template) {
            'verify-email' => new VerifyEmailNotification,
            'reset-password' => new ResetPasswordNotification('preview-token'),
            default => abort(404, 'Unknown template.'),
        };

        $locale = in_array($request->query('lang'), config('tenancy.locales'), true)
            ? $request->query('lang')
            : ($user->preferredLocale() ?? config('app.locale'));

        // Same as a real send: the notification is built and rendered in the recipient's language
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            $message = $notification->toMail($user);

            if ($request->query('format') === 'text') {
                return response(view($message->view['text'], $message->data())->render(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            return response($message->render());
        } finally {
            App::setLocale($previous);
        }
    }
}
