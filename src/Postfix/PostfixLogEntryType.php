<?php

declare(strict_types=1);

namespace J87NL\Mailing\Postfix;

enum PostfixLogEntryType: string
{
    case MessageId = 'message_id';
    case Delivery = 'delivery';
    case Expired = 'expired';
    case Removed = 'removed';
}
