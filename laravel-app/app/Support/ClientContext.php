<?php

namespace App\Support;

use App\Enums\TenantType;

/**
 * Request-scoped client preferences (locale, timezone and the frontend's theme) resolved by middleware.
 */
class ClientContext
{
    private ?string $timezone = null;

    private ?TenantType $theme = null;

    public function setTheme(?TenantType $theme): void
    {
        $this->theme = $theme;
    }

    public function theme(): ?TenantType
    {
        return $this->theme;
    }

    public function setTimezone(?string $timezone): void
    {
        $this->timezone = $timezone;
    }

    public function timezone(): ?string
    {
        return $this->timezone;
    }

    public static function isValidTimezone(?string $timezone): bool
    {
        return is_string($timezone)
            && $timezone !== ''
            && in_array($timezone, \DateTimeZone::listIdentifiers(), true);
    }
}
