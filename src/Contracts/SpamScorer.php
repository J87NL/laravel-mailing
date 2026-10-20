<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

interface SpamScorer
{
    public function score(string $rawMessage): ?float;
}
