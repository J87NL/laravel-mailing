<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use J87NL\Mailing\Contracts\SpamScorer;

class NullSpamScorer implements SpamScorer
{
    public function score(string $rawMessage): ?float
    {
        return null;
    }
}
