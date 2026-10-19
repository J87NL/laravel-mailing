<?php

declare(strict_types=1);

namespace J87NL\Mailing\Delivery;

use J87NL\Mailing\Enums\DeliveryEventType;

final readonly class DeliveryEvent
{
    public function __construct(
        public DeliveryEventType $type,
        public string $eventId,
        public ?int $sendId = null,
        public ?string $messageId = null,
        public ?string $recipient = null,
        public ?string $diagnostic = null,
    ) {}
}
