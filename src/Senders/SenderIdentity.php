<?php

declare(strict_types=1);

namespace J87NL\Mailing\Senders;

use Illuminate\Mail\Mailables\Address;

final readonly class SenderIdentity
{
    public function __construct(
        public string $key,
        public string $email,
        public ?string $name = null,
    ) {}

    public function label(): string
    {
        if (is_null($this->name) || $this->name === '') {
            return $this->email;
        }

        return sprintf('%s <%s>', $this->name, $this->email);
    }

    public function address(): Address
    {
        return new Address($this->email, $this->name);
    }
}
