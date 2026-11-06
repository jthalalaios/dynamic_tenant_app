<?php

namespace App\Mail\Branding;

use Illuminate\Support\Facades\Lang;

/**
 * Wording of one email template for one theme, from lang/{locale}/mail.php:
 *
 *   mail.{template}.{theme}.{key}  →  falls back to  mail.{template}.default.{key}
 *   mail.common.{key}              →  shared lines (greeting, footer...)
 *
 * so a theme only overrides the lines it words differently.
 */
final class MailCopy
{
    /**
     * @param  array<string, string>  $replace  placeholders available to every line (:organization, :name...)
     */
    public function __construct(
        private readonly string $template,
        private readonly string $theme,
        private readonly array $replace = [],
    ) {}

    /**
     * @param  array<string, string>  $replace
     */
    public function get(string $key, array $replace = []): string
    {
        return (string) __($this->resolve($key), [...$this->replace, ...$replace]);
    }

    /**
     * A list of lines (e.g. feature highlights); empty when the theme defines none.
     *
     * @return list<string>
     */
    public function items(string $key): array
    {
        $key = $this->resolve($key);
        $items = Lang::has($key) ? __($key) : [];

        return is_array($items) ? array_values(array_map('strval', $items)) : [];
    }

    /**
     * @param  array<string, string>  $replace
     */
    public function common(string $key, array $replace = []): string
    {
        return (string) __("mail.common.{$key}", [...$this->replace, ...$replace]);
    }

    private function resolve(string $key): string
    {
        $themed = "mail.{$this->template}.{$this->theme}.{$key}";

        return Lang::has($themed) ? $themed : "mail.{$this->template}.default.{$key}";
    }
}
