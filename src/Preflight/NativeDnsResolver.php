<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

use J87NL\Mailing\Contracts\DnsResolver;

class NativeDnsResolver implements DnsResolver
{
    public function txt(string $host): array
    {
        $records = @dns_get_record($host, DNS_TXT);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): string => is_array($record['entries'] ?? null) ? implode('', $record['entries']) : (string) ($record['txt'] ?? ''),
            $records,
        )));
    }

    public function a(string $host): array
    {
        $records = @dns_get_record($host, DNS_A);

        if (! is_array($records)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (array $record): string => (string) ($record['ip'] ?? ''),
            $records,
        )));
    }
}
