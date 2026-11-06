<?php

namespace App\Mail\Branding;

use App\Models\User;
use App\Tenancy\Themes;
use Illuminate\Support\Facades\View;

/**
 * Picks the brand and template of an email from the recipient's tenant type,
 * so a clinic account gets clinic emails and a shop account gets shop emails.
 */
class MailBrandResolver
{
    public function __construct(private readonly Themes $themes) {}

    public function for(User $user): MailBrand
    {
        $type = $user->tenant?->type;
        $themes = config('branding.mail.themes', []);
        $theme = $type !== null && isset($themes[$type->value]) ? $type->value : 'default';
        $definition = $themes[$theme] ?? [];

        return new MailBrand(
            theme: $theme,
            organization: $user->tenant?->name ?: (string) config('app.name'),
            glyph: $definition['glyph'] ?? '✦',
            colors: $definition['colors'] ?? [],
            frontendUrl: $this->themes->frontendUrl($type),
        );
    }

    /**
     * `mail.{theme}.{template}`, or the generic `mail.default.{template}` when the theme has none.
     */
    public function view(string $template, MailBrand $brand): string
    {
        $themed = "mail.{$brand->theme}.{$template}";

        return View::exists($themed) ? $themed : "mail.default.{$template}";
    }
}
