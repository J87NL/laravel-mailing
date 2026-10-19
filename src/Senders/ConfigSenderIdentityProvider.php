<?php

declare(strict_types=1);

namespace J87NL\Mailing\Senders;

use J87NL\Mailing\Contracts\SenderIdentityProvider;

final class ConfigSenderIdentityProvider implements SenderIdentityProvider
{
    public function identities(): array
    {
        $configured = config('mailing.senders', []);

        if (! is_array($configured)) {
            return [];
        }

        $identities = [];

        foreach ($configured as $key => $sender) {
            if (! is_string($key) || ! is_array($sender)) {
                continue;
            }

            $email = $sender['email'] ?? null;

            if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }

            $name = $sender['name'] ?? null;

            $identities[$key] = new SenderIdentity($key, $email, is_string($name) ? $name : null);
        }

        return $identities;
    }

    public function find(string $key): ?SenderIdentity
    {
        return $this->identities()[$key] ?? null;
    }
}
