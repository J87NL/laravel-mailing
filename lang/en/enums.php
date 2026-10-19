<?php

declare(strict_types=1);

return [
    'subscription_status' => [
        'pending' => 'Awaiting confirmation',
        'subscribed' => 'Subscribed',
        'unsubscribed' => 'Unsubscribed',
        'bounced' => 'Bounced',
        'complained' => 'Complained',
    ],
    'suppression_reason' => [
        'hard_bounce' => 'Hard bounce',
        'complaint' => 'Spam complaint',
        'manual' => 'Manual',
        'unsubscribe_all' => 'Unsubscribed from all lists',
        'soft_bounces' => 'Repeated soft bounces',
    ],
    'consent_action' => [
        'requested' => 'Sign-up requested',
        'confirmed' => 'Sign-up confirmed',
        'imported' => 'Imported',
        'unsubscribed' => 'Unsubscribed',
        'suppressed' => 'Added to the suppression list',
        'unsuppressed' => 'Removed from the suppression list',
    ],
    'campaign_status' => [
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'sending' => 'Sending',
        'paused' => 'Paused',
        'sent' => 'Sent',
        'cancelled' => 'Cancelled',
    ],
    'send_status' => [
        'queued' => 'Queued',
        'sending' => 'Sending',
        'handed_over' => 'Handed over',
        'deferred' => 'Deferred',
        'delivered' => 'Delivered',
        'bounced' => 'Bounced',
        'complained' => 'Complained',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        'cancelled' => 'Cancelled',
    ],
    'bounce_type' => [
        'hard' => 'Hard',
        'soft' => 'Soft',
    ],
];
