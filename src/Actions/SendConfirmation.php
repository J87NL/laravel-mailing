<?php

declare(strict_types=1);

namespace J87NL\Mailing\Actions;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use J87NL\Mailing\Mail\ConfirmSubscriptionMail;
use J87NL\Mailing\Models\Subscription;

class SendConfirmation
{
    public function handle(Subscription $subscription, bool $imported = false, int $delayMinutes = 0): void
    {
        $subscription->loadMissing([
            'subscriber',
            'mailingList',
        ]);

        $url = URL::temporarySignedRoute('mailing.confirm.show', now()->addDays((int) config('mailing.confirmation.valid_days', 7)), [
            'subscription' => $subscription->id,
        ]);

        $mailer = config('mailing.transactional_mailer');

        $mail = new ConfirmSubscriptionMail($subscription, $url, $imported);

        if ($delayMinutes > 0) {
            $mail->delay(now()->addMinutes($delayMinutes));
        }

        Mail::mailer(is_string($mailer) && $mailer !== '' ? $mailer : null)
            ->to($subscription->subscriber)
            ->queue($mail);

        $subscription->forceFill([
            'confirmation_sent_at' => now(),
        ])->save();
    }
}
