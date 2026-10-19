<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class ListTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::Lists->value);
    }

    /**
     * @return Builder<MailingList>
     */
    public function for(): Builder
    {
        return MailingList::query()
            ->withCount([
                'subscriptions',
                'subscriptions as subscribed_count' => fn (Builder $query): Builder => $query->where('status', SubscriptionStatus::Subscribed),
                'subscriptions as pending_count' => fn (Builder $query): Builder => $query->where('status', SubscriptionStatus::Pending),
            ])
            ->orderBy('name');
    }

    public function configure(TableBuilder $table): void
    {
        $canSettings = (bool) request()->user()?->can(MailingPermission::Settings->value);

        $table
            ->column('name', __('mailing::lists.fields.name'))
            ->column('key', __('mailing::lists.fields.key'))
            ->column('is_public', __('mailing::lists.fields.is_public'), boolean: true)
            ->column('subscribed_count', __('mailing::lists.fields.subscribed'))
            ->column('pending_count', __('mailing::lists.fields.pending'))
            ->column('mailer', __('mailing::lists.fields.mailer'))
            ->paginate(50);

        $table
            ->rowAction(
                __('mailing::common.edit'),
                url: fn (MailingList $list): string => route('mailing.lists.edit', $list),
                key: 'edit',
            )
            ->rowAction(
                __('mailing::lists.actions.transport'),
                url: fn (MailingList $list): string => route('mailing.lists.transport.edit', $list),
                disabled: $canSettings ? false : (string) __('mailing::lists.errors.needs_settings'),
                key: 'transport',
            )
            ->rowAction(
                __('mailing::lists.actions.signup_page'),
                url: fn (MailingList $list): string => route('mailing.subscribe.show', $list),
                visible: fn (MailingList $list): bool => $list->is_public,
                key: 'signup-page',
            )
            ->rowAction(
                __('mailing::common.delete'),
                url: fn (MailingList $list): string => route('mailing.lists.destroy', $list),
                method: 'delete',
                confirm: __('mailing::lists.actions.delete_confirm'),
                variant: 'destructive',
                disabled: fn (MailingList $list): string|false => (int) $list->getAttribute('subscriptions_count') > 0 ? (string) __('mailing::lists.errors.has_subscriptions') : false,
                key: 'delete',
            );
    }
}
