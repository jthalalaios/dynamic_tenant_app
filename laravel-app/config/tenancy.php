<?php

return [

    // Locales the API can answer in; the first one is the fallback
    'locales' => ['en', 'el'],

    'presence' => [
        // Minutes without a heartbeat after which an open session no longer counts as online
        'timeout_minutes' => (int) env('PRESENCE_TIMEOUT_MINUTES', 5),
    ],

    /*
    | Themes (tenant types) this backend serves. One backend can power a single
    | "multi" frontend (theme picker), several standalone frontends (one per
    | theme, e.g. clinic.example.com + market.example.com), or both at once.
    |
    | - enabled:       comma separated list, empty = every TenantType. A standalone
    |                  deployment sets e.g. TENANCY_THEMES=doctor.
    | - header:        standalone frontends send it; auth endpoints (login, register,
    |                  forgot password) then only act on accounts of that theme and
    |                  tokens of another theme are refused.
    | - frontend_urls: where emailed links (reset password, verify email) point for
    |                  accounts of each theme; falls back to app.frontend_url.
    */
    'themes' => [
        'enabled' => array_values(array_filter(array_map('trim', explode(',', (string) env('TENANCY_THEMES', ''))))),
        'header' => 'X-App-Theme',
        'frontend_urls' => [
            'doctor' => env('FRONTEND_URL_DOCTOR'),
            'marketplace' => env('FRONTEND_URL_MARKETPLACE'),
        ],
    ],

    /*
    | One workspace (organization) per theme. Everyone who signs up joins the
    | workspace of the theme they pick, as a `registration.role` member; an
    | administrator then promotes them on the Team page. Missing workspaces
    | are created on demand (App\Tenancy\Workspaces).
    */
    'workspaces' => [
        'doctor' => [
            'slug' => env('TENANCY_DOCTOR_WORKSPACE', 'acme-clinic'),
            'name' => env('TENANCY_DOCTOR_WORKSPACE_NAME', 'Acme Clinic'),
        ],
        'marketplace' => [
            'slug' => env('TENANCY_MARKETPLACE_WORKSPACE', 'acme-market'),
            'name' => env('TENANCY_MARKETPLACE_WORKSPACE_NAME', 'Acme Market'),
        ],
        // Used when a workspace has to be created
        'timezone' => env('TENANCY_WORKSPACE_TIMEZONE', 'Europe/Athens'),
    ],

    'registration' => [
        // System role (config/permissions.php) given to self-registered users
        'role' => 'member',
    ],

    'auth' => [
        // Lifetime of issued Passport personal access tokens
        'token_ttl_minutes' => (int) env('AUTH_TOKEN_TTL_MINUTES', 60 * 12),
        'token_name' => 'spa',

        // When true, unverified users can sign in but only reach their own session
        // endpoints (me, profile, logout, resend verification) until they verify.
        'verify_email' => (bool) env('AUTH_VERIFY_EMAIL', true),
        'verification_ttl_minutes' => (int) env('AUTH_VERIFICATION_TTL_MINUTES', 60 * 24),
    ],

    'pagination' => [
        'default' => 15,
        'max' => 100,
    ],

];
