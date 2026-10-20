<?php

declare(strict_types=1);

namespace J87NL\Mailing\Drivers;

use Illuminate\Http\Request;
use J87NL\Mailing\Contracts\MailingDriver;
use J87NL\Mailing\Models\Campaign;
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
        $this->returnPath($message, $send->id);
    }

    public function prepareTest(Email $message, Campaign $campaign): void
    {
        $this->returnPath($message, Verp::TEST_SEND_ID);
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

    private function returnPath(Email $message, int $sendId): void
    {
        $returnPath = Verp::address($sendId);

        if (is_null($returnPath)) {
            return;
        }

        $message->returnPath($returnPath);
    }
}
