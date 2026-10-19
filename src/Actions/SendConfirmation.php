<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\Models\Subscription;

class SendConfirmation
{
    public function handle(Subscription $subscription): void
    {
        $subscription->loadMissing([
            'subscriber',
            'mailingList',
        ]);

        $url = URL::temporarySignedRoute('mailing.confirm.show', now()->addDays((int) config('mailing.confirmation.valid_days', 7)), [
            'subscription' => $subscription->id,
        ]);

        $mailer = config('mailing.transactional_mailer');

        Mail::mailer(is_string($mailer) && $mailer !== '' ? $mailer : null)
            ->to($subscription->subscriber)
            ->queue(new ConfirmSubscriptionMail($subscription, $url));

        $subscription->forceFill([
            'confirmation_sent_at' => now(),
        ])->save();
    }
}
