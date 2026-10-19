<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class SubscriberTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::Subscribers->value);
    }

    /**
     * @return Builder<Subscriber>
     */
    public function for(): Builder
    {
        return Subscriber::query()
            ->with('subscriptions.mailingList')
            ->orderBy('email');
    }

    public function configure(TableBuilder $table): void
    {
        $lists = MailingList::query()
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id): array => [
                (string) $id => $name,
            ])
            ->all();

        $table
            ->withGlobalSearch(columns: [
                'email',
                'name',
            ])
            ->callbackFilter(
                'list',
                $lists,
                fn (Builder $query, mixed $value): Builder => $query->whereHas('subscriptions', fn (Builder $subscriptions): Builder => $subscriptions->where('list_id', (int) $value)),
                __('mailing::subscribers.fields.list'),
            )
            ->column('email', __('mailing::subscribers.fields.email'), sortable: true)
            ->column('name', __('mailing::subscribers.fields.name'), sortable: true)
            ->column('subscriptions', __('mailing::subscribers.fields.subscriptions'), as: fn (mixed $value, Subscriber $subscriber): string => $this->summary($subscriber))
            ->column('created_at', __('mailing::subscribers.fields.created_at'), sortable: true, as: fn (mixed $value, Subscriber $subscriber): string => $subscriber->created_at?->isoFormat('D MMM YYYY') ?? '')
            ->exportable(
                label: __('mailing::subscribers.actions.export'),
                filename: fn (): string => sprintf('subscribers-%s.csv', now()->format('Y-m-d')),
            )
            ->paginate(50);

        $table->rowAction(
            __('mailing::subscribers.actions.show'),
            url: fn (Subscriber $subscriber): string => route('mailing.subscribers.show', $subscriber),
            key: 'show',
        );
    }

    private function summary(Subscriber $subscriber): string
    {
        return $subscriber->subscriptions
            ->map(fn (Subscription $subscription): string => sprintf('%s: %s', $subscription->mailingList->name, $subscription->status->label()))
            ->implode(', ');
    }
}
