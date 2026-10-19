<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscription;

class ListHeaders
{
    /**
     * @return array<string, string>
     */
    public function for(Subscription $subscription): array
    {
        $subscription->loadMissing('mailingList');

        return [
            'List-Id' => $this->listId($subscription->mailingList),
            'List-Unsubscribe' => sprintf('<%s>', $this->unsubscribeUrl($subscription)),
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }

    public function unsubscribeUrl(Subscription $subscription): string
    {
        return route('mailing.unsubscribe.show', [
            'token' => $subscription->unsubscribe_token,
        ]);
    }

    public function listId(MailingList $list): string
    {
        $name = str_replace([
            '"',
            '\\',
        ], '', $list->name);

        return sprintf('"%s" <%s.%s>', $name, $list->key, $this->domain());
    }

    private function domain(): string
    {
        $configured = config('mailing.list_id_domain');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }
}
