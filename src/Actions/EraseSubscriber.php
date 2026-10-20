<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
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

            $listIds = $subscriber->subscriptions()
                ->pluck('list_id')
                ->all();

            Send::query()
                ->whereIn('subscription_id', $subscriber->subscriptions()->pluck('id'))
                ->delete();

            BounceEvent::query()
                ->where('email', $subscriber->email)
                ->delete();

            $subscriber->delete();

            if ($keepSuppressed) {
                return;
            }

            Log::info('Mailing subscriber erased without a suppression', [
                'actor_id' => $actorId,
                'action' => 'subscriber_erased',
                'subscriber_id' => $subscriber->id,
                'list_ids' => $listIds,
                'at' => now()->toIso8601String(),
            ]);
        });
    }
}
