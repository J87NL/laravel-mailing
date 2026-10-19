<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Headers;
use J87NL\Mailing\Models\Subscription;

abstract class ListMailable extends Mailable
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {}

    public function headers(): Headers
    {
        return new Headers(
            text: app(ListHeaders::class)->for($this->subscription),
        );
    }

    public function unsubscribeUrl(): string
    {
        return app(ListHeaders::class)->unsubscribeUrl($this->subscription);
    }
}
