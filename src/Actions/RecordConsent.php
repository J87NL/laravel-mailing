<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;

class RecordConsent
{
    public function handle(
        Subscriber $subscriber,
        ?MailingList $list,
        ConsentAction $action,
        ?string $source = null,
        ?string $ip = null,
        ?string $note = null,
        ?int $actorId = null,
    ): ConsentRecord {
        return ConsentRecord::query()
            ->create([
                'subscriber_id' => $subscriber->id,
                'list_id' => $list?->id,
                'action' => $action,
                'source' => $source,
                'ip' => $ip,
                'note' => $note,
                'actor_id' => $actorId,
            ]);
    }
}
