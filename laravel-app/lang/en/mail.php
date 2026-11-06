<?php

/*
| Transactional email wording. Per template: `default` lines, overridden per
| theme (tenant type) where the clinic and the shop speak differently.
| See App\Mail\Branding\MailCopy for the lookup order.
*/

return [

    'common' => [
        'greeting' => 'Hi :name,',
        'expires' => 'For your security, this link expires in :time.',
        'fallback' => 'Button not working? Copy and paste this link into your browser:',
        'footer' => 'You are receiving this email because of your account at :organization.',
        'signature' => 'The :organization team',
    ],

    'verify-email' => [
        'default' => [
            'subject' => 'Confirm your email address',
            'eyebrow' => 'One more step',
            'title' => 'Confirm your email address',
            'intro' => 'Thanks for joining :organization. Please confirm your email address to finish setting up your account.',
            'cta' => 'Confirm my email',
            'ignore' => 'If you did not create this account, you can safely ignore this email.',
        ],
        'doctor' => [
            'subject' => 'Confirm your email to open :organization',
            'eyebrow' => 'Clinic workspace',
            'title' => 'Welcome to :organization',
            'intro' => 'You are one step away from your clinic workspace. Confirm your email address so we know it is really you.',
            'highlights_title' => 'Once you are in, you can:',
            'highlights' => [
                'Book and manage appointments for the whole team',
                'Keep patient records safe in one place',
                'Check patients in and follow today’s queue live',
            ],
        ],
        'marketplace' => [
            'subject' => 'Activate your :organization store account',
            'eyebrow' => 'Store workspace',
            'title' => 'Your store is almost ready',
            'intro' => 'Confirm your email address to start selling with :organization.',
            'cta' => 'Activate my account',
            'highlights_title' => 'What is waiting for you:',
            'highlights' => [
                'Add products and keep stock under control',
                'Take orders and follow them to delivery',
                'See what sells best with live insights',
            ],
        ],
    ],

    'reset-password' => [
        'default' => [
            'subject' => 'Reset your password',
            'eyebrow' => 'Account security',
            'title' => 'Reset your password',
            'intro' => 'We received a request to reset the password of your :organization account.',
            'cta' => 'Choose a new password',
            'ignore' => 'If you did not ask for this, no action is needed: your password stays the same.',
        ],
        'doctor' => [
            'subject' => 'Reset your :organization password',
            'eyebrow' => 'Clinic workspace',
            'intro' => 'We received a request to reset the password of your account at :organization. Choose a new one to get back to your patients.',
        ],
        'marketplace' => [
            'subject' => 'Reset your :organization store password',
            'eyebrow' => 'Store workspace',
            'title' => 'Let’s get you back to your store',
            'intro' => 'We received a request to reset the password of your :organization store account.',
        ],
    ],

];
