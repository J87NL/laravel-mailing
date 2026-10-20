<?php

declare(strict_types=1);

namespace J87NL\Mailing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use J87NL\Mailing\Drivers\DriverRegistry;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Mail\CampaignMail;
use J87NL\Mailing\Mail\ListHeaders;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Sending\Dispatcher;
use J87NL\Mailing\Sending\RetrySchedule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Throwable;

class SendCampaignMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const string SKIPPED_UNSUBSCRIBED = 'skipped_unsubscribed';

    public int $tries = 1;

    public function __construct(
        public readonly int $sendId,
        public readonly int $attempt,
        public readonly ?int $notAfter = null,
    ) {
        $this->onQueue((string) config('mailing.sending.queue', 'mailing'));
    }

    public function handle(DriverRegistry $drivers, RetrySchedule $retries, ListHeaders $listHeaders): void
    {
        $send = Send::query()
            ->with([
                'campaign.mailingList',
                'subscription.subscriber',
            ])
            ->find($this->sendId);

        if (is_null($send) || $send->campaign->status !== CampaignStatus::Sending) {
            return;
        }

        if (! is_null($this->notAfter) && now()->getTimestamp() >= $this->notAfter) {
            return;
        }

        $messageId = sprintf('%d.%s@%s', $send->id, Str::lower(Str::random(16)), $listHeaders->domain());

        $claimed = Send::query()
            ->whereKey($send->id)
            ->whereIn('status', [
                SendStatus::Queued,
                SendStatus::Deferred,
            ])
            ->where('attempts', $this->attempt)
            ->update([
                'status' => SendStatus::Sending,
                'attempts' => $this->attempt + 1,
                'message_id' => sprintf('<%s>', $messageId),
                'sending_started_at' => now(),
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return;
        }

        $send->refresh();

        $subscription = $send->subscription;

        if (is_null($subscription) || $subscription->status !== SubscriptionStatus::Subscribed || Suppression::covers($send->email)) {
            $this->finish($send, SendStatus::Skipped, self::SKIPPED_UNSUBSCRIBED);

            return;
        }

        $list = $send->campaign->mailingList;
        $driver = $drivers->for($list);
        $mail = (new CampaignMail($send->campaign, $subscription, $messageId))
            ->withSymfonyMessage(fn (Email $message) => $driver->prepare($message, $send));

        try {
            Mail::mailer($list->mailer)->to($send->email)->send($mail);
        } catch (TransportExceptionInterface $exception) {
            $this->failedTransport($send, $exception, $retries);

            return;
        } catch (Throwable $exception) {
            $this->finish($send, SendStatus::Failed, $exception->getMessage());

            return;
        }

        $this->leaveSending($send, [
            'status' => SendStatus::HandedOver,
            'handed_over_at' => now(),
            'last_response' => null,
        ]);
    }

    private function failedTransport(Send $send, TransportExceptionInterface $exception, RetrySchedule $retries): void
    {
        $code = (int) $exception->getCode();

        if ($code === 0) {
            $this->finish($send, SendStatus::Failed, Dispatcher::INTERRUPTED);

            return;
        }

        $temporary = $code >= 400 && $code < 500;
        $delay = $retries->delayAfter($send->attempts);

        if (! $temporary || is_null($delay)) {
            $this->finish($send, SendStatus::Failed, $exception->getMessage());

            return;
        }

        $this->leaveSending($send, [
            'status' => SendStatus::Deferred,
            'next_attempt_at' => now()->addMinutes($delay),
            'last_response' => Str::limit($exception->getMessage(), 1000),
        ]);
    }

    private function finish(Send $send, SendStatus $status, string $response): void
    {
        $this->leaveSending($send, [
            'status' => $status,
            'last_response' => Str::limit($response, 1000),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function leaveSending(Send $send, array $attributes): void
    {
        Send::query()
            ->whereKey($send->id)
            ->where('status', SendStatus::Sending)
            ->update([
                ...$attributes,
                'updated_at' => now(),
            ]);
    }
}
