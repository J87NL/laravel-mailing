<?php

declare(strict_types=1);

namespace J87NL\Mailing\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use J87NL\Mailing\Html\EmailStyler;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Senders\ListSender;

class CampaignMail extends Mailable
{
    public function __construct(
        public readonly Campaign $campaign,
        public readonly ?Subscription $subscription = null,
        public readonly ?string $messageId = null,
    ) {}

    public function envelope(): Envelope
    {
        $list = $this->campaign->mailingList;
        $replyTo = is_null($list->reply_to) || $list->reply_to === '' ? [] : [
            new Address($list->reply_to),
        ];

        return new Envelope(
            from: app(ListSender::class)->for($list)->address(),
            replyTo: $replyTo,
            subject: $this->campaign->subject,
        );
    }

    public function headers(): Headers
    {
        $headers = app(ListHeaders::class);

        return new Headers(
            messageId: is_null($this->messageId) ? null : trim($this->messageId, '<>'),
            text: is_null($this->subscription) ? [
                'List-Id' => $headers->listId($this->campaign->mailingList),
            ] : $headers->for($this->subscription),
        );
    }

    public function content(): Content
    {
        $list = $this->campaign->mailingList;
        $unsubscribeUrl = is_null($this->subscription) ? null : app(ListHeaders::class)->unsubscribeUrl($this->subscription);

        return new Content(
            view: 'mailing::mail.campaign',
            text: 'mailing::mail.campaign-text',
            with: [
                'subject' => $this->campaign->subject,
                'preheader' => (string) $this->campaign->preheader,
                'bodyHtml' => app(EmailStyler::class)->inline($this->campaign->body_html),
                'bodyText' => $this->campaign->body_text,
                'listName' => $list->name,
                'senderName' => app(ListSender::class)->for($list)->label(),
                'unsubscribeUrl' => $unsubscribeUrl,
            ],
        );
    }
}
