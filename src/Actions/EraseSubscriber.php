<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;

class EraseSubscriber
{
    public function handle(Subscriber $subscriber, bool $keepSuppressed, ?int $actorId = null): void
    {
        DB::transaction(function () use ($subscriber, $keepSuppressed, $actorId): void {
            if ($keepSuppressed) {
                Suppression::query()
                    ->firstOrCreate([
                        'email' => $subscriber->email,
                    ], [
                        'reason' => SuppressionReason::Manual,
                        'source' => ConsentSource::ADMIN,
                        'note' => __('mailing::subscribers.erase.suppression_note'),
                        'created_by' => $actorId,
                    ]);
            }

            $subscriber->delete();
        });
    }
}
