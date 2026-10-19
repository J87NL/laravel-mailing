<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

use J87NL\Mailing\Senders\SenderIdentity;

interface SenderIdentityProvider
{
    /**
     * @return array<string, SenderIdentity>
     */
    public function identities(): array;

    public function find(string $key): ?SenderIdentity;
}
