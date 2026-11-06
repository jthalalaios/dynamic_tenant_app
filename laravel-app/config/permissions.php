<?php

/*
|--------------------------------------------------------------------------
| Permission registry
|--------------------------------------------------------------------------
|
| Single source of truth for every permission the API understands. Run
| `php artisan permissions:sync` after editing to upsert them into the
| database. Permissions are grouped by "scope": `common` ones are usable
| by every tenant, the others only by tenants of that type.
|
| `roles` lists the system roles seeded for each new tenant of a type.
| Admin roles implicitly hold every permission available to the tenant.
|
*/

return [

    'cache_ttl' => (int) env('PERMISSIONS_CACHE_TTL', 3600),

    'permissions' => [
        'common' => [
            'users' => [
                'users.view' => 'View team members',
                'users.create' => 'Invite team members',
                'users.update' => 'Edit team members and their roles',
                'users.delete' => 'Remove team members',
            ],
            'roles' => [
                'roles.view' => 'View roles',
                'roles.create' => 'Create roles',
                'roles.update' => 'Edit roles and their permissions',
                'roles.delete' => 'Delete roles',
            ],
            // Live chat. Moderation never reaches administrators unless the actor is one too.
            'chat' => [
                'chat.view' => 'Use the live chat (read and send messages)',
                'chat.mute' => 'Mute and unmute members (never administrators)',
                'chat.manage' => 'Create, edit and delete chat rooms',
            ],
        ],
        'doctor' => [
            'patients' => [
                'patients.view' => 'View patients',
                'patients.create' => 'Register patients',
                'patients.update' => 'Edit patients',
                'patients.delete' => 'Delete patients',
            ],
            'appointments' => [
                'appointments.view' => 'View appointments',
                'appointments.create' => 'Book appointments',
                'appointments.update' => 'Reschedule, complete or cancel appointments',
                'appointments.delete' => 'Delete appointments',
                'appointments.check-in' => 'Check patients in (requires being online)',
            ],
            'presence' => [
                'presence.toggle' => 'Go online / offline',
            ],
            'reports' => [
                'reports.view' => 'View clinic reports',
            ],
        ],
        'marketplace' => [
            'products' => [
                'products.view' => 'View products',
                'products.create' => 'Create products',
                'products.update' => 'Edit products and stock',
                'products.delete' => 'Delete products',
            ],
            'orders' => [
                'orders.view' => 'View orders',
                'orders.create' => 'Place orders',
                'orders.update' => 'Change order status',
            ],
            'marketing' => [
                'marketing.view' => 'View marketing insights',
            ],
        ],
    ],

    'roles' => [
        'doctor' => [
            'admin' => ['name' => 'Administrator', 'is_admin' => true, 'permissions' => []],
            // Self-registered users start here (tenancy.registration.role) until an admin promotes them
            'member' => ['name' => 'Member', 'is_admin' => false, 'permissions' => ['chat.view']],
            'doctor' => [
                'name' => 'Doctor',
                'is_admin' => false,
                'permissions' => [
                    'patients.view', 'patients.create', 'patients.update',
                    'appointments.view', 'appointments.create', 'appointments.update', 'appointments.check-in',
                    'presence.toggle', 'reports.view',
                    'chat.view', 'chat.mute',
                ],
            ],
            'receptionist' => [
                'name' => 'Receptionist',
                'is_admin' => false,
                'permissions' => [
                    'patients.view', 'patients.create', 'patients.update',
                    'appointments.view', 'appointments.create', 'appointments.update', 'appointments.check-in',
                    'presence.toggle',
                    'chat.view',
                ],
            ],
        ],
        'marketplace' => [
            'admin' => ['name' => 'Administrator', 'is_admin' => true, 'permissions' => []],
            'member' => ['name' => 'Member', 'is_admin' => false, 'permissions' => ['chat.view']],
            'manager' => [
                'name' => 'Store manager',
                'is_admin' => false,
                'permissions' => [
                    'products.view', 'products.create', 'products.update', 'products.delete',
                    'orders.view', 'orders.create', 'orders.update', 'marketing.view',
                    'chat.view', 'chat.mute',
                ],
            ],
            'sales' => [
                'name' => 'Sales associate',
                'is_admin' => false,
                'permissions' => ['products.view', 'orders.view', 'orders.create', 'chat.view'],
            ],
        ],
    ],

];
