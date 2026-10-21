<?php

declare(strict_types=1);

use J87NL\Mailing\Drivers\LettermintDriver;
use J87NL\Mailing\Drivers\PostfixDriver;
use J87NL\Mailing\Html\BasicHtmlSanitizer;
use J87NL\Mailing\Mailbox\NullMailboxReader;
use J87NL\Mailing\Preflight\NativeDnsResolver;
use J87NL\Mailing\Preflight\RspamdSpamScorer;
use J87NL\Mailing\Senders\ConfigSenderIdentityProvider;

return [

    'permissions_config_key' => 'j87nl.permissions',

    'navigation_config_key' => 'j87nl.navigation',

    'toast_flash_key' => 'toast',

    'timezone' => env('MAILING_TIMEZONE', 'Europe/Amsterdam'),

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
        'campaigns_index' => 'mailing/campaigns/Index',
        'campaigns_show' => 'mailing/campaigns/Show',
    ],

    'senders' => [
        'default' => [
            'email' => env('MAILING_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')),
            'name' => env('MAILING_FROM_NAME', env('MAIL_FROM_NAME')),
        ],
    ],

    'sender_identity_provider' => ConfigSenderIdentityProvider::class,

    'mailbox_reader' => NullMailboxReader::class,

    'html_sanitizer' => BasicHtmlSanitizer::class,

    'rich_text' => [
        'editor' => 'hugerte',
        'options_config_key' => 'cms.rich_text.editor_options',
    ],

    'transactional_mailer' => env('MAILING_TRANSACTIONAL_MAILER'),

    'list_defaults' => [
        'mailer' => env('MAILING_MAILER', 'smtp'),
        'throttle_per_minute' => 20,
        'daily_cap' => 500,
    ],

    'list_id_domain' => env('MAILING_LIST_ID_DOMAIN'),

    'mailer_drivers' => [
        'postfix' => 'postfix',
        'smtp' => 'postfix',
        'lettermint' => 'lettermint',
    ],

    'drivers' => [
        'postfix' => [
            'class' => PostfixDriver::class,
        ],
        'lettermint' => [
            'class' => LettermintDriver::class,
            'webhook_secret' => env('LETTERMINT_WEBHOOK_SECRET'),
            'tolerance' => 300,
        ],
    ],

    'sending' => [
        'queue' => env('MAILING_QUEUE', 'mailing'),
        'retry_minutes' => [
            15,
            60,
            240,
        ],
        'stale_minutes' => 30,
        'scan_factor' => 5,
        'domain_limits' => [
            'gmail.com' => 5,
            'googlemail.com' => 5,
            'outlook.com' => 5,
            'hotmail.com' => 5,
            'live.nl' => 5,
            'ziggo.nl' => 5,
            'kpnmail.nl' => 5,
        ],
    ],

    'bounces' => [
        'domain' => env('MAILING_BOUNCE_DOMAIN'),
        'local_part' => 'bounces',
        'soft_limit' => 3,
        'soft_window_days' => 30,
    ],

    'preflight' => [
        'dns_resolver' => NativeDnsResolver::class,
        'spam_scorer' => RspamdSpamScorer::class,
        'rspamd' => [
            'url' => env('MAILING_RSPAMD_URL'),
            'password' => env('MAILING_RSPAMD_PASSWORD'),
        ],
        'spam_threshold' => 5.0,
        'link_budget_seconds' => 20,
        'sending_ips' => array_values(array_filter(explode(',', (string) env('MAILING_SENDING_IPS', '')))),
        'blocklists' => [
            'zen.spamhaus.org',
            'b.barracudacentral.org',
            'bl.spamcop.net',
        ],
        'spf' => [
            'postfix' => array_values(array_filter(explode(',', (string) env('MAILING_SPF_POSTFIX', '')))),
            'lettermint' => [],
        ],
        'dkim_selectors' => [
            'postfix' => array_values(array_filter(explode(',', (string) env('MAILING_DKIM_SELECTORS_POSTFIX', '')))),
            'lettermint' => [],
        ],
    ],

    'signup' => [
        'per_ip_per_hour' => 10,
        'per_address_per_hour' => 3,
        'per_address_per_day' => 6,
        'global_per_hour' => 60,
        'min_seconds' => 3,
        'max_seconds' => 172800,
        'success_query' => 'aangemeld',
        'privacy_url' => env('MAILING_PRIVACY_URL'),
    ],

    'confirmation' => [
        'valid_days' => 7,
        'resend_after_hours' => 24,
    ],

    'import' => [
        'max_kilobytes' => 1024,
        'max_rows' => 5000,
        'confirmations_per_minute' => 20,
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
