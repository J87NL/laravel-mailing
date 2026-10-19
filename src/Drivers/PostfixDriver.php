<?php

declare(strict_types=1);

namespace J87NL\Mailing\Drivers;

use Illuminate\Http\Request;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Sending\Verp;
use Symfony\Component\Mime\Email;

class PostfixDriver implements MailingDriver
{
    public function key(): string
    {
        return 'postfix';
    }

    public function prepare(Email $message, Send $send): void
    {
        $returnPath = Verp::address($send->id);

        if (is_null($returnPath)) {
            return;
        }

        $message->returnPath($returnPath);
    }

    public function hasWebhooks(): bool
    {
        return false;
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function parseWebhook(Request $request): array
    {
        return [];
    }

    public function reportsDelivery(): bool
    {
        return false;
    }
}
