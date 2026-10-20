<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

use Illuminate\Http\Request;
use J87NL\Mailing\Delivery\DeliveryEvent;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Send;
use Symfony\Component\Mime\Email;

interface MailingDriver
{
    public function key(): string;

    public function prepare(Email $message, Send $send): void;

    public function prepareTest(Email $message, Campaign $campaign): void;

    public function hasWebhooks(): bool;

    public function verifyWebhook(Request $request): bool;

    /**
     * @return list<DeliveryEvent>
     */
    public function parseWebhook(Request $request): array;

    public function reportsDelivery(): bool;
}
