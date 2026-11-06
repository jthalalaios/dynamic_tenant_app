<?php

namespace App\Models;

use App\Authorization\HasRoles;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use App\Tenancy\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['tenant_id', 'name', 'email', 'password', 'timezone', 'locale', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether this user still has to confirm their email before using the app.
     */
    public function mustVerifyEmail(): bool
    {
        return config('tenancy.auth.verify_email') && ! $this->hasVerifiedEmail();
    }

    /**
     * Sends the verification link only while verification is enabled (AUTH_VERIFY_EMAIL).
     * The link points at the frontend of the user's theme, see AppServiceProvider.
     */
    public function sendEmailVerificationNotification(): void
    {
        if ($this->mustVerifyEmail()) {
            $this->notify(new VerifyEmailNotification);
        }
    }

    /**
     * Reset link email in the theme of the user's tenant.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Language of the emails and notifications this user receives: their own
     * preference, else their organisation's, else the app default.
     */
    public function preferredLocale(): ?string
    {
        $supported = config('tenancy.locales', []);

        foreach ([$this->locale, $this->tenant?->locale] as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                return $locale;
            }
        }

        return null;
    }

    /**
     * @return HasMany<PresenceSession, $this>
     */
    public function presenceSessions(): HasMany
    {
        return $this->hasMany(PresenceSession::class);
    }
}
