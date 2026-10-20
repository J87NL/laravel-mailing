<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use Illuminate\Contracts\View\Factory;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Transport\ArrayTransport;

class RawMessageRenderer
{
    public function __construct(
        private readonly Factory $views,
    ) {}

    public function render(Mailable $mailable, string $recipient): string
    {
        $mailer = new Mailer('preflight', $this->views, new ArrayTransport);

        return $mailer->to($recipient)->send($mailable)?->toString() ?? '';
    }
}
