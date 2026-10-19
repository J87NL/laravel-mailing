<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use J87NL\Mailing\Contracts\SpamScorer;

class FakeSpamScorer implements SpamScorer
{
    public ?float $score = 1.2;

    public function score(string $rawMessage): ?float
    {
        return $this->score;
    }
}
