<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\DB;
use J87NL\Mailing\Enums\ConsentAction;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;

class LiftSuppression
{
    public function __construct(
        private readonly RecordConsent $recordConsent,
    ) {}

    public function handle(Suppression $suppression, ?int $actorId = null): void
    {
        DB::transaction(function () use ($suppression, $actorId): void {
            $subscriber = Subscriber::query()
                ->where('email', $suppression->email)
                ->first();

            $suppression->delete();

            if (is_null($subscriber)) {
                return;
            }

            $this->recordConsent->handle(
                $subscriber,
                null,
                ConsentAction::Unsuppressed,
                ConsentSource::ADMIN,
                null,
                __('mailing::suppressions.lifted_note', [
                    'reason' => $suppression->reason->label(),
                ]),
                $actorId,
            );
        });
    }
}
