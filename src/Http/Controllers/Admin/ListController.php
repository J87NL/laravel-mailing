<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailing\Forms\ListForm;
use J87NL\Mailing\Forms\ListTransportForm;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Senders\ListSender;
use J87NL\Mailing\Tables\ListTable;
use RuntimeException;

class ListController extends Controller
{
    public function index(): Response
    {
        return Inertia::render($this->page('lists_index'), [
            'lists' => ListTable::build(),
            'createUrl' => route('mailing.lists.create'),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(
            __('mailing::lists.create.title'),
            __('mailing::lists.index.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => __('mailing::lists.create.title'),
                    'href' => route('mailing.lists.create'),
                ],
            ],
            route('mailing.lists.index'),
            ListForm::make()
                ->action(route('mailing.lists.store'))
                ->method('POST')
                ->fill([
                    'is_public' => true,
                ]),
        );
    }

    public function store(Request $request, ListSender $sender): RedirectResponse
    {
        $validated = ListForm::make()->validate($request);

        $list = new MailingList;
        $list->fill([
            ...$this->listAttributes($validated),
            'key' => (string) $validated['key'],
            'mailer' => (string) config('mailing.list_defaults.mailer'),
            'throttle_per_minute' => (int) config('mailing.list_defaults.throttle_per_minute', 20),
            'daily_cap' => config('mailing.list_defaults.daily_cap'),
        ]);
        try {
            $list->sender_identity = $sender->for($list)->key;
        } catch (RuntimeException) {
            return to_route('mailing.lists.index')->withErrors([
                'list' => __('mailing::lists.errors.no_sender'),
            ]);
        }

        $list->save();

        $this->toast(__('mailing::lists.flash.saved'));

        return to_route('mailing.lists.index');
    }

    public function edit(MailingList $list): Response
    {
        return $this->form(
            __('mailing::lists.edit.title', [
                'name' => $list->name,
            ]),
            __('mailing::lists.index.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => $list->name,
                    'href' => route('mailing.lists.edit', $list),
                ],
            ],
            route('mailing.lists.index'),
            ListForm::make($list)
                ->action(route('mailing.lists.update', $list))
                ->method('PUT')
                ->fill([
                    'name' => $list->name,
                    'key' => $list->key,
                    'description' => $list->description,
                    'reply_to' => $list->reply_to,
                    'is_public' => $list->is_public,
                ]),
        );
    }

    public function update(Request $request, MailingList $list): RedirectResponse
    {
        $list->fill($this->listAttributes(ListForm::make($list)->validate($request)))->save();

        $this->toast(__('mailing::lists.flash.saved'));

        return to_route('mailing.lists.index');
    }

    public function destroy(MailingList $list): RedirectResponse
    {
        if ($list->subscriptions()->exists()) {
            return to_route('mailing.lists.index')->withErrors([
                'list' => __('mailing::lists.errors.has_subscriptions'),
            ]);
        }

        $list->delete();

        $this->toast(__('mailing::lists.flash.deleted'));

        return to_route('mailing.lists.index');
    }

    public function editTransport(MailingList $list, ListSender $sender): Response
    {
        return $this->form(
            __('mailing::lists.transport.title', [
                'name' => $list->name,
            ]),
            __('mailing::lists.transport.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => __('mailing::lists.transport.title', [
                        'name' => $list->name,
                    ]),
                    'href' => route('mailing.lists.transport.edit', $list),
                ],
            ],
            route('mailing.lists.index'),
            ListTransportForm::make()
                ->action(route('mailing.lists.transport.update', $list))
                ->method('PUT')
                ->fill([
                    'mailer' => $list->mailer,
                    'sender_identity' => $sender->for($list)->key,
                    'throttle_per_minute' => $list->throttle_per_minute,
                    'daily_cap' => $list->daily_cap,
                ]),
        );
    }

    public function updateTransport(Request $request, MailingList $list): RedirectResponse
    {
        $validated = ListTransportForm::make()->validate($request);

        $list->fill([
            'mailer' => (string) $validated['mailer'],
            'sender_identity' => (string) $validated['sender_identity'],
            'throttle_per_minute' => (int) $validated['throttle_per_minute'],
            'daily_cap' => is_numeric($validated['daily_cap'] ?? null) ? (int) $validated['daily_cap'] : null,
        ])->save();

        $this->toast(__('mailing::lists.flash.saved'));

        return to_route('mailing.lists.index');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function listAttributes(array $validated): array
    {
        $description = trim((string) ($validated['description'] ?? ''));
        $replyTo = trim((string) ($validated['reply_to'] ?? ''));

        return [
            'name' => trim((string) $validated['name']),
            'description' => $description === '' ? null : $description,
            'reply_to' => $replyTo === '' ? null : $replyTo,
            'is_public' => (bool) ($validated['is_public'] ?? false),
        ];
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(): array
    {
        return [
            [
                'title' => __('mailing::lists.index.title'),
                'href' => route('mailing.lists.index'),
            ],
        ];
    }
}
