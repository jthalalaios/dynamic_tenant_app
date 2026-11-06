<?php

namespace App\Mail\Branding;

/**
 * Visual identity of one email: which theme it is drawn in and for which organisation.
 */
final class MailBrand
{
    /**
     * @param  string  $theme  key in config('branding.mail.themes'): a TenantType value or "default"
     * @param  array<string, string>  $colors
     */
    public function __construct(
        public readonly string $theme,
        public readonly string $organization,
        public readonly string $glyph,
        public readonly array $colors,
        public readonly string $frontendUrl,
    ) {}

    public function color(string $name): string
    {
        return $this->colors[$name] ?? '#000000';
    }
}
