<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Support\ConsentSource;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class SubscriptionTable extends AbstractTable
{
    public function __construct(
        private readonly int $subscriberId,
    ) {}

    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::Subscribers->value);
    }

    public function signedParameters(): array
    {
        return [
            'subscriberId' => $this->subscriberId,
        ];
    }

    /**
     * @return Builder<Subscription>
     */
    public function for(): Builder
    {
        return Subscription::query()
            ->with('mailingList')
            ->where('subscriber_id', $this->subscriberId)
            ->orderBy('id');
    }

    public function configure(TableBuilder $table): void
    {
        $date = fn (?DateTimeInterface $value): string => is_null($value) ? '' : Carbon::instance($value)->isoFormat('D MMM YYYY HH:mm');

        $table
            ->column('list', __('mailing::subscribers.fields.list'), as: fn (mixed $value, Subscription $subscription): string => $subscription->mailingList->name)
            ->column('status', __('mailing::subscribers.fields.status'), as: fn (mixed $value, Subscription $subscription): string => $subscription->status->label())
            ->column('consent_source', __('mailing::subscribers.fields.consent_source'), as: fn (mixed $value, Subscription $subscription): string => ConsentSource::describe($subscription->consent_source))
            ->column('confirmed_at', __('mailing::subscribers.fields.confirmed_at'), as: fn (mixed $value, Subscription $subscription): string => $date($subscription->confirmed_at))
            ->column('unsubscribed_at', __('mailing::subscribers.fields.unsubscribed_at'), as: fn (mixed $value, Subscription $subscription): string => $date($subscription->unsubscribed_at));

        $table->rowAction(
            __('mailing::subscribers.actions.unsubscribe'),
            url: fn (Subscription $subscription): string => route('mailing.subscriptions.unsubscribe', $subscription),
            method: 'post',
            confirm: __('mailing::subscribers.actions.unsubscribe_confirm'),
            variant: 'destructive',
            visible: fn (Subscription $subscription): bool => $subscription->isActive(),
            key: 'unsubscribe',
        );
    }
}
