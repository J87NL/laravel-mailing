<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailing\Actions\EraseSubscriber;
use J87NL\Mailing\Actions\Unsubscribe;
use J87NL\Mailing\Forms\EraseSubscriberForm;
use J87NL\Mailing\Models\ConsentRecord;
use J87NL\Mailing\Models\Subscriber;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;
use J87NL\Mailing\Tables\SubscriberTable;
use J87NL\Mailing\Tables\SubscriptionTable;

class SubscriberController extends Controller
{
    public function index(): Response
    {
        return Inertia::render($this->page('subscribers_index'), [
            'subscribers' => SubscriberTable::build(),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function show(Subscriber $subscriber): Response
    {
        $suppression = Suppression::query()
            ->where('email', $subscriber->email)
            ->first();

        return Inertia::render($this->page('subscribers_show'), [
            'subscriber' => [
                'email' => $subscriber->email,
                'name' => $subscriber->name,
                'createdAt' => $subscriber->created_at?->isoFormat('D MMM YYYY HH:mm'),
                'suppression' => is_null($suppression) ? null : [
                    'reason' => $suppression->reason->label(),
                    'createdAt' => $suppression->created_at?->isoFormat('D MMM YYYY HH:mm'),
                ],
            ],
            'eraseUrl' => route('mailing.subscribers.erase', $subscriber),
            'subscriptions' => SubscriptionTable::build($subscriber->id),
            'history' => $subscriber->consentRecords()
                ->with('mailingList')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ConsentRecord $record): array => [
                    'id' => $record->id,
                    'action' => $record->action->label(),
                    'list' => $record->mailingList?->name,
                    'source' => ConsentSource::describe($record->source),
                    'ip' => $record->ip,
                    'note' => $record->note,
                    'at' => $record->created_at?->isoFormat('D MMM YYYY HH:mm'),
                ])
                ->values()
                ->all(),
            'breadcrumbs' => [
                ...$this->breadcrumbs(),
                [
                    'title' => $subscriber->email,
                    'href' => route('mailing.subscribers.show', $subscriber),
                ],
            ],
        ]);
    }

    public function confirmErase(Subscriber $subscriber): Response
    {
        return $this->form(
            __('mailing::subscribers.erase.title', [
                'email' => $subscriber->email,
            ]),
            __('mailing::subscribers.erase.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => $subscriber->email,
                    'href' => route('mailing.subscribers.show', $subscriber),
                ],
                [
                    'title' => __('mailing::subscribers.erase.action'),
                    'href' => route('mailing.subscribers.erase', $subscriber),
                ],
            ],
            route('mailing.subscribers.show', $subscriber),
            EraseSubscriberForm::make()
                ->action(route('mailing.subscribers.destroy', $subscriber))
                ->method('DELETE')
                ->fill([
                    'keep_suppressed' => true,
                ]),
        );
    }

    public function destroy(Request $request, Subscriber $subscriber, EraseSubscriber $eraseSubscriber): RedirectResponse
    {
        $validated = EraseSubscriberForm::make()->validate($request);

        $eraseSubscriber->handle($subscriber, (bool) ($validated['keep_suppressed'] ?? false), $this->userId($request));

        $this->toast(__('mailing::subscribers.flash.erased'));

        return to_route('mailing.subscribers.index');
    }

    public function unsubscribe(Request $request, Subscription $subscription, Unsubscribe $unsubscribe): RedirectResponse
    {
        $unsubscribe->handle($subscription, ConsentSource::ADMIN, null, $this->userId($request));

        $this->toast(__('mailing::subscribers.flash.unsubscribed'));

        return to_route('mailing.subscribers.show', $subscription->subscriber_id);
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(): array
    {
        return [
            [
                'title' => __('mailing::subscribers.index.title'),
                'href' => route('mailing.subscribers.index'),
            ],
        ];
    }
}
