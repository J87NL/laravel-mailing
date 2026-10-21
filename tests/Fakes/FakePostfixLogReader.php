<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use J87NL\Mailing\Contracts\PostfixLogReader;

class FakePostfixLogReader implements PostfixLogReader
{
    /**
     * @var list<string>
     */
    public array $lines = [];

    public function lines(): iterable
    {
        $lines = $this->lines;
        $this->lines = [];

        return $lines;
    }
}
