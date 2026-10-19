<?php

declare(strict_types=1);

use J87NL\Mailing\Mailbox\NullMailboxReader;
use J87NL\Mailing\Senders\ConfigSenderIdentityProvider;

return [

    'permissions_config_key' => 'j87nl.permissions',

    'navigation_config_key' => 'j87nl.navigation',

    'toast_flash_key' => 'toast',

    'layout' => 'mailing::layouts.default',

    'routes' => [
        'public_prefix' => 'mailing',
        'public_middleware' => [],
        'admin_prefix' => 'admin/mailing',
        'admin_middleware' => [
            'web',
            'auth',
        ],
    ],

    'pages' => [
        'form' => 'mailing/Form',
        'lists_index' => 'mailing/lists/Index',
        'subscribers_index' => 'mailing/subscribers/Index',
        'subscribers_show' => 'mailing/subscribers/Show',
        'suppressions_index' => 'mailing/suppressions/Index',
        'imports_index' => 'mailing/imports/Index',
    ],

    'senders' => [
        'default' => [
            'email' => env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'name' => env('MAILING_FROM_NAME', env('MAIL_FROM_NAME')),
        ],
    ],

    'sender_identity_provider' => ConfigSenderIdentityProvider::class,

    'mailbox_reader' => NullMailboxReader::class,

    'transactional_mailer' => env('MAILING_TRANSACTIONAL_MAILER'),

    'list_defaults' => [
        'mailer' => env('MAILING_MAILER', 'smtp'),
        'throttle_per_minute' => 20,
        'daily_cap' => 500,
    ],

    'list_id_domain' => env('MAILING_LIST_ID_DOMAIN'),

    'signup' => [
        'per_ip_per_hour' => 10,
        'per_address_per_hour' => 3,
        'global_per_hour' => 60,
        'min_seconds' => 3,
        'max_seconds' => 172800,
    ],

    'confirmation' => [
        'valid_days' => 7,
    ],

    'unsubscribe' => [
        'mailto' => env('MAILING_UNSUBSCRIBE_MAILTO'),
    ],

    'import' => [
        'max_kilobytes' => 1024,
        'max_rows' => 5000,
    ],

    'retention' => [
        'unconfirmed_days' => 30,
        'unsubscribed_years' => 2,
        'send_records_months' => 13,
    ],

    'scheduling' => [
        'enabled' => true,
    ],

];
