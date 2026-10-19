<?php

declare(strict_types=1);

namespace J87NL\Mailing\Senders;

use J87NL\Mailing\Contracts\SenderIdentityProvider;
use J87NL\Mailing\Models\MailingList;
use RuntimeException;

final readonly class ListSender
{
    public function __construct(
        private SenderIdentityProvider $provider,
    ) {}

    public function for(MailingList $list): SenderIdentity
    {
        $identity = is_null($list->sender_identity) ? null : $this->provider->find($list->sender_identity);

        if (! is_null($identity)) {
            return $identity;
        }

        $fallback = array_values($this->provider->identities())[0] ?? null;

        if (is_null($fallback)) {
            throw new RuntimeException('No mailing sender identity is configured.');
        }

        return $fallback;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        return array_values(array_map(
            fn (SenderIdentity $identity): array => [
                'value' => $identity->key,
                'label' => $identity->label(),
            ],
            $this->provider->identities(),
        ));
    }
}
