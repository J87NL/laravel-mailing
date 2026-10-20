<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tests\Fakes;

use J87NL\Mailing\Contracts\DnsResolver;

class FakeDnsResolver implements DnsResolver
{
    /**
     * @var array<string, list<string>>
     */
    public array $txt = [];

    /**
     * @var array<string, list<string>>
     */
    public array $a = [];

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }

    public function a(string $host): array
    {
        return $this->a[$host] ?? [];
    }
}
