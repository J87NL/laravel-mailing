<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

interface PostfixLogReader
{
    /**
     * @return iterable<string>
     */
    public function lines(): iterable;
}
