<?php

declare(strict_types=1);

namespace J87NL\Mailing\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;
use J87NL\Mailing\Actions\ImportSubscribers;
use J87NL\Mailing\Forms\ImportForm;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Tables\ImportTable;

class ImportController extends Controller
{
    public function index(): Response
    {
        $hasLists = MailingList::query()
            ->exists();

        return Inertia::render($this->page('imports_index'), [
            'imports' => ImportTable::build(),
            'createUrl' => route('mailing.imports.create'),
            'createDisabledReason' => $hasLists ? null : __('mailing::imports.errors.no_lists'),
            'breadcrumbs' => $this->breadcrumbs(),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        if (! MailingList::query()
            ->exists()) {
            return to_route('mailing.imports.index')->withErrors([
                'import' => __('mailing::imports.errors.no_lists'),
            ]);
        }

        return $this->form(
            __('mailing::imports.create.title'),
            __('mailing::imports.create.description'),
            [
                ...$this->breadcrumbs(),
                [
                    'title' => __('mailing::imports.create.title'),
                    'href' => route('mailing.imports.create'),
                ],
            ],
            route('mailing.imports.index'),
            ImportForm::make()
                ->action(route('mailing.imports.store'))
                ->method('POST'),
        );
    }

    public function store(Request $request, ImportSubscribers $importSubscribers): RedirectResponse
    {
        $validated = ImportForm::make()->validate($request);
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $list = MailingList::query()
            ->findOrFail((int) $validated['list_id']);

        $import = $importSubscribers->handle(
            $list,
            $file->getRealPath(),
            $file->getClientOriginalName(),
            (string) $validated['consent_source'],
            is_string($validated['consent_note'] ?? null) ? $validated['consent_note'] : null,
            $this->userId($request),
        );

        $this->toast(__('mailing::imports.flash.imported', [
            'imported' => $import->imported,
            'skipped' => $import->skipped,
            'confirmations' => $import->confirmations_sent,
        ]));

        if (is_null($import->errors)) {
            return to_route('mailing.imports.index');
        }

        return to_route('mailing.imports.index')->withErrors([
            'import' => implode(' ', $import->errors),
        ]);
    }

    /**
     * @return list<array{title: string, href: string}>
     */
    private function breadcrumbs(): array
    {
        return [
            [
                'title' => __('mailing::imports.index.title'),
                'href' => route('mailing.imports.index'),
            ],
        ];
    }
}
