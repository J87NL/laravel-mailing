<?php

declare(strict_types=1);

return [
    'index' => [
        'title' => 'Mailing lists',
        'description' => 'Lists that visitors join with double opt-in.',
    ],
    'create' => [
        'title' => 'New list',
    ],
    'edit' => [
        'title' => 'Edit list :name',
    ],
    'transport' => [
        'title' => 'Sending for :name',
        'description' => 'Which mailer and sender this list uses, and how fast it sends. Sender and transport are independent.',
    ],
    'fields' => [
        'name' => 'Name',
        'key' => 'Key',
        'description' => 'Description',
        'reply_to' => 'Reply-to address',
        'is_public' => 'Public',
        'subscribed' => 'Subscribed',
        'pending' => 'Awaiting confirmation',
        'mailer' => 'Mailer',
        'sender_identity' => 'Sender',
        'throttle_per_minute' => 'Mails per minute',
        'daily_cap' => 'Daily maximum',
    ],
    'help' => [
        'key' => 'Lowercase letters, digits and dashes. Used in the sign-up URL and the List-Id.',
        'description' => 'Shown above the sign-up form.',
        'reply_to' => 'Replies to mails of this list go to this address.',
        'is_public' => 'Only public lists have a sign-up page and can be used in a sign-up block.',
        'mailer' => 'The Laravel mailer from config/mail.php that sends this list\'s mailings.',
        'sender_identity' => 'The From address of this list.',
        'throttle_per_minute' => 'Postfix: 20 is a safe default.',
        'daily_cap' => 'Leave empty for no maximum.',
    ],
    'actions' => [
        'transport' => 'Sending',
        'signup_page' => 'Sign-up page',
        'delete_confirm' => 'Are you sure you want to delete this list?',
    ],
    'errors' => [
        'needs_settings' => 'This needs the "manage sending settings" permission.',
        'has_subscriptions' => 'This list has subscribers and cannot be deleted.',
    ],
    'flash' => [
        'saved' => 'The list has been saved.',
        'deleted' => 'The list has been deleted.',
    ],
];
