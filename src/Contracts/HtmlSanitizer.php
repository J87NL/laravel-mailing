<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

interface HtmlSanitizer
{
    public function sanitize(?string $html): string;
}
