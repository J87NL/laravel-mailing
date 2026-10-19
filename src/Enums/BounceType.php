<?php

declare(strict_types=1);

namespace J87NL\Mailing\Enums;

enum BounceType: string
{
    case Hard = 'hard';
    case Soft = 'soft';

    public function label(): string
    {
        return __(sprintf('mailing::enums.bounce_type.%s', $this->value));
    }
}
