<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Senders\ListSender;

class ConfirmSubscriptionMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $confirmUrl,
        public readonly bool $imported = false,
    ) {}

    public function envelope(): Envelope
    {
        $list = $this->subscription->mailingList;
        $replyTo = is_null($list->reply_to) || $list->reply_to === '' ? [] : [
            new Address($list->reply_to),
        ];

        return new Envelope(
            from: app(ListSender::class)->for($list)->address(),
            replyTo: $replyTo,
            subject: __(sprintf('mailing::mail.%s.subject', $this->copy()), [
                'list' => $list->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mailing::mail.confirm',
            text: 'mailing::mail.confirm-text',
            with: [
                'listName' => $this->subscription->mailingList->name,
                'confirmUrl' => $this->confirmUrl,
                'validDays' => (int) config('mailing.confirmation.valid_days', 7),
                'copy' => $this->copy(),
            ],
        );
    }

    private function copy(): string
    {
        return $this->imported ? 'confirm_import' : 'confirm';
    }
}
