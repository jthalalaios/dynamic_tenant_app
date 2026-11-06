<?php

namespace App\Tenancy;

use App\Enums\TenantType;
use App\Support\ClientContext;

/**
 * Which themes (tenant types) this backend serves and which one the current
 * request's frontend is bound to.
 *
 * - Multi frontend: no X-App-Theme header, every enabled theme is accepted.
 * - Standalone frontend: sends X-App-Theme, so auth endpoints only act on
 *   accounts of that theme and tokens of another theme are refused.
 */
class Themes
{
    public function __construct(private readonly ClientContext $client) {}

    /**
     * @return list<TenantType>
     */
    public function enabled(): array
    {
        $configured = config('tenancy.themes.enabled', []);

        if ($configured === []) {
            return TenantType::cases();
        }

        return array_values(array_filter(
            TenantType::cases(),
            fn (TenantType $type) => in_array($type->value, $configured, true),
        ));
    }

    public function isEnabled(?TenantType $type): bool
    {
        return $type !== null && in_array($type, $this->enabled(), true);
    }

    /**
     * The theme the calling frontend is bound to, if it is a standalone one.
     */
    public function requested(): ?TenantType
    {
        return $this->client->theme();
    }

    /**
     * Themes the current request may act on: the requested one, or every enabled one.
     *
     * @return list<TenantType>
     */
    public function allowed(): array
    {
        $requested = $this->requested();

        return $requested !== null ? [$requested] : $this->enabled();
    }

    public function allows(?TenantType $type): bool
    {
        return $type !== null && in_array($type, $this->allowed(), true);
    }

    /**
     * @return list<string>
     */
    public function allowedValues(): array
    {
        return array_map(fn (TenantType $type) => $type->value, $this->allowed());
    }

    /**
     * Base URL of the frontend that serves accounts of the given theme.
     */
    public function frontendUrl(?TenantType $type): string
    {
        $url = $type !== null ? config("tenancy.themes.frontend_urls.{$type->value}") : null;

        return rtrim($url ?: config('app.frontend_url'), '/');
    }
}
