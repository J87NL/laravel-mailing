<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Support\MailingTime;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class CampaignTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::CampaignsEdit->value);
    }

    /**
     * @return Builder<Campaign>
     */
    public function for(): Builder
    {
        return Campaign::query()
            ->with('mailingList')
            ->withCount('sends')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->withGlobalSearch(columns: [
                'subject',
            ])
            ->column('subject', __('mailing::campaigns.fields.subject'))
            ->column('list', __('mailing::campaigns.fields.list'), as: fn (mixed $value, Campaign $campaign): string => $campaign->mailingList->name)
            ->column('status', __('mailing::campaigns.fields.status'), as: fn (mixed $value, Campaign $campaign): string => $campaign->status->label())
            ->column('sends_count', __('mailing::campaigns.fields.recipients'))
            ->column('scheduled_at', __('mailing::campaigns.fields.scheduled_at'), as: fn (mixed $value, Campaign $campaign): string => MailingTime::format($campaign->scheduled_at, 'D MMM YYYY HH:mm') ?? '')
            ->rowLink(fn (Campaign $campaign): string => route('mailing.campaigns.show', $campaign))
            ->paginate(25);
    }
}
