<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Jobs\SendCampaignMessage;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Sending\Dispatcher;
use J87NL\Mailing\Support\MailingTime;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class SendTable extends AbstractTable
{
    private const int RESPONSE_LENGTH = 160;

    private const array RESPONSE_KEYS = [
        Dispatcher::INTERRUPTED,
        SendCampaignMessage::SKIPPED_UNSUBSCRIBED,
    ];

    public function __construct(
        private readonly int $campaignId,
    ) {}

    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::CampaignsEdit->value);
    }

    public function signedParameters(): array
    {
        return [
            'campaignId' => $this->campaignId,
        ];
    }

    /**
     * @return Builder<Send>
     */
    public function for(): Builder
    {
        return Send::query()
            ->where('campaign_id', $this->campaignId)
            ->orderBy('id');
    }

    public function configure(TableBuilder $table): void
    {
        $statuses = [];

        foreach (SendStatus::cases() as $status) {
            $statuses[$status->value] = $status->label();
        }

        $table
            ->withGlobalSearch(columns: [
                'email',
            ])
            ->selectFilter('status', $statuses, __('mailing::campaigns.fields.status'))
            ->column('email', __('mailing::subscribers.fields.email'))
            ->column('status', __('mailing::campaigns.fields.status'), as: fn (mixed $value, Send $send): string => $send->status->label())
            ->column('attempts', __('mailing::campaigns.fields.attempts'))
            ->column('handed_over_at', __('mailing::campaigns.fields.handed_over_at'), as: fn (mixed $value, Send $send): string => MailingTime::format($send->handed_over_at, 'D MMM HH:mm') ?? '')
            ->column('last_response', __('mailing::campaigns.fields.last_response'), as: fn (mixed $value, Send $send): string => $this->response($send))
            ->paginate(50);
    }

    private function response(Send $send): string
    {
        $response = (string) ($send->bounce_diagnostic ?? $send->last_response);

        if (! in_array($response, self::RESPONSE_KEYS, true)) {
            return Str::limit($response, self::RESPONSE_LENGTH);
        }

        return (string) __(sprintf('mailing::campaigns.responses.%s', $response));
    }
}
