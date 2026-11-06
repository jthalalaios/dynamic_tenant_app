<?php

/*
|--------------------------------------------------------------------------
| Branding per theme (tenant type)
|--------------------------------------------------------------------------
|
| Visual identity of transactional emails. Keys are TenantType values;
| `default` is used for any type without an entry, so a new tenant type
| works out of the box and can be styled later.
|
| Colours mirror the frontend themes (vue3-frontend/src/services/theme.ts),
| so an email looks like the app it links to. Templates live in
| resources/views/mail/{theme}/{template}.blade.php (falling back to
| mail/default/...) and their wording in lang/{locale}/mail.php.
|
*/

return [

    'mail' => [
        'themes' => [
            'default' => [
                'glyph' => '✦',
                'colors' => [
                    'primary' => '#4f46e5',
                    'accent' => '#06b6d4',
                    'background' => '#f5f7fb',
                    'surface' => '#ffffff',
                    'soft' => '#eef2ff',
                    'text' => '#0f172a',
                    'muted' => '#64748b',
                    'border' => '#e2e8f0',
                ],
            ],

            // Calm, clinical blue
            'doctor' => [
                'glyph' => '✚',
                'colors' => [
                    'primary' => '#0f66f0',
                    'accent' => '#06b6d4',
                    'background' => '#f6fbff',
                    'surface' => '#ffffff',
                    'soft' => '#eaf3ff',
                    'text' => '#0f1724',
                    'muted' => '#64748b',
                    'border' => '#e6eefb',
                ],
            ],

            // Warm, energetic commerce
            'marketplace' => [
                'glyph' => '🛍',
                'colors' => [
                    'primary' => '#ef4444',
                    'accent' => '#f59e0b',
                    'background' => '#fffaf4',
                    'surface' => '#ffffff',
                    'soft' => '#fff1e6',
                    'text' => '#1f2937',
                    'muted' => '#7c2d12',
                    'border' => '#fde7d8',
                ],
            ],
        ],
    ],

];
