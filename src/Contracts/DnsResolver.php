<?php

declare(strict_types=1);

namespace J87NL\Mailing\Contracts;

interface DnsResolver
{
    /**
     * @return list<string>
     */
    public function txt(string $host): array;

    /**
     * @return list<string>
     */
    public function a(string $host): array;
}
