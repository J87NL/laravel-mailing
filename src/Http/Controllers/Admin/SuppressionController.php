<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailing\Actions\SuppressAddress;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Forms\SuppressionForm;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Tables\SuppressionTable;

class SuppressionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render($this->page('suppressions_index'), [
            'suppressions' => SuppressionTable::build(),
            'createUrl' => route('mailing.suppressions.create'),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(
            __('mailing::suppressions.create.title'),
            __('mailing::suppressions.create.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => __('mailing::suppressions.create.title'),
                    'href' => route('mailing.suppressions.create'),
                ],
            ],
            route('mailing.suppressions.index'),
            SuppressionForm::make()
                ->action(route('mailing.suppressions.store'))
                ->method('POST'),
        );
    }

    public function store(Request $request, SuppressAddress $suppressAddress): RedirectResponse
    {
        $validated = SuppressionForm::make()->validate($request);
        $note = trim((string) ($validated['note'] ?? ''));

        $suppressAddress->handle(
            (string) $validated['email'],
            SuppressionReason::Manual,
            'admin',
            $note === '' ? null : $note,
            $this->userId($request),
        );

        $this->toast(__('mailing::suppressions.flash.saved'));

        return to_route('mailing.suppressions.index');
    }

    public function destroy(Suppression $suppression): RedirectResponse
    {
        $suppression->delete();

        $this->toast(__('mailing::suppressions.flash.deleted'));

        return to_route('mailing.suppressions.index');
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(): array
    {
        return [
            [
                'title' => __('mailing::suppressions.index.title'),
                'href' => route('mailing.suppressions.index'),
            ],
        ];
    }
}
