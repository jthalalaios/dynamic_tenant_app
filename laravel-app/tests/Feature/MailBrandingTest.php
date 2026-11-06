<?php

use App\Enums\TenantType;
use App\Mail\Branding\MailBrandResolver;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

/*
| Transactional emails follow the recipient's theme (tenant type) and language.
*/

beforeEach(function () {
    $this->clinic = $this->createTenant(TenantType::Doctor, ['name' => 'Angel Clinic', 'locale' => 'en']);
    $this->shop = $this->createTenant(TenantType::Marketplace, ['name' => 'Test Shop', 'locale' => 'en']);
});

function renderMail(MailMessage $message): string
{
    return (string) $message->render();
}

it('sends clinic accounts the clinic template, as the clinic', function () {
    $user = $this->createUser($this->clinic);
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($message->view['html'])->toBe('mail.doctor.verify-email')
        ->and($message->view['text'])->toBe('mail.text.action')
        ->and($message->subject)->toBe('Confirm your email to open Angel Clinic')
        ->and($message->from)->toBe([config('mail.from.address'), 'Angel Clinic']);

    $html = renderMail($message);
    expect($html)->toContain('Welcome to Angel Clinic', 'Book and manage appointments', '#0f66f0', e($message->actionUrl))
        ->not->toContain('Laravel');
});

it('sends shop accounts the store template, as the shop', function () {
    $user = $this->createUser($this->shop);
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($message->view['html'])->toBe('mail.marketplace.verify-email')
        ->and($message->subject)->toBe('Activate your Test Shop store account')
        ->and($message->actionText)->toBe('Activate my account')
        ->and($message->from[1])->toBe('Test Shop');

    expect(renderMail($message))->toContain('Your store is almost ready', 'Add products and keep stock', '#ef4444')
        ->not->toContain('#0f66f0');
});

it('brands the password reset email per theme too', function () {
    $doctor = (new ResetPasswordNotification('token'))->toMail($this->createUser($this->clinic));
    $seller = (new ResetPasswordNotification('token'))->toMail($this->createUser($this->shop));

    expect($doctor->view['html'])->toBe('mail.doctor.reset-password')
        ->and($doctor->subject)->toBe('Reset your Angel Clinic password')
        ->and($seller->view['html'])->toBe('mail.marketplace.reset-password')
        ->and(renderMail($seller))->toContain('Let’s get you back to your store', 'expires in 1 hour');
});

it('writes in the recipient language: their own, else their organisation’s', function () {
    $greek = $this->createUser($this->clinic);
    $greek->update(['locale' => 'el']);
    expect($greek->preferredLocale())->toBe('el');

    $byTenant = $this->createUser($this->createTenant(TenantType::Marketplace, ['name' => 'Κατάστημα', 'locale' => 'el']));
    expect($byTenant->preferredLocale())->toBe('el');

    // A real send runs in that locale (HasLocalePreference)
    Notification::fake();
    $greek->notify(new VerifyEmailNotification);
    Notification::assertSentTo($greek, VerifyEmailNotification::class, function (VerifyEmailNotification $n, array $channels, $notifiable, ?string $locale) {
        return $locale === 'el';
    });

    app()->setLocale('el');
    expect((new VerifyEmailNotification)->toMail($greek)->subject)->toBe('Επιβεβαιώστε το email σας για το Angel Clinic');
});

it('falls back to the generic template and wording for themes without their own', function () {
    $user = $this->createUser($this->clinic);
    config(['branding.mail.themes' => ['default' => config('branding.mail.themes.default')]]);

    $brand = app(MailBrandResolver::class)->for($user);
    $message = (new VerifyEmailNotification)->toMail($user);

    expect($brand->theme)->toBe('default')
        ->and($message->view['html'])->toBe('mail.default.verify-email')
        ->and($message->subject)->toBe('Confirm your email address')
        ->and(renderMail($message))->toContain('Angel Clinic');
});

it('delivers html and plain text parts through the mailer', function () {
    config(['mail.default' => 'array']);
    $user = $this->createUser($this->shop);

    $user->notify(new VerifyEmailNotification);

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->first()?->getOriginalMessage();
    expect($sent)->not->toBeNull()
        ->and($sent->getSubject())->toBe('Activate your Test Shop store account')
        ->and($sent->getHtmlBody())->toContain('Your store is almost ready')
        ->and($sent->getTextBody())->toContain('Activate my account:', '/verify-email/')
        ->and($sent->getTextBody())->not->toContain('<');
});
