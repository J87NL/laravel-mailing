<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\MailingImport;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class ImportTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::Subscribers->value);
    }

    /**
     * @return Builder<MailingImport>
     */
    public function for(): Builder
    {
        return MailingImport::query()
            ->with('mailingList')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function configure(TableBuilder $table): void
    {
        $table
            ->column('created_at', __('mailing::imports.fields.created_at'), as: fn (mixed $value, MailingImport $import): string => $import->created_at?->isoFormat('D MMM YYYY HH:mm') ?? '')
            ->column('list', __('mailing::imports.fields.list'), as: fn (mixed $value, MailingImport $import): string => $import->mailingList->name)
            ->column('filename', __('mailing::imports.fields.file'))
            ->column('consent_source', __('mailing::imports.fields.consent_source'))
            ->column('consent_note', __('mailing::imports.fields.consent_note'))
            ->column('imported', __('mailing::imports.fields.imported'))
            ->column('skipped', __('mailing::imports.fields.skipped'))
            ->column('confirmations_sent', __('mailing::imports.fields.confirmations_sent'))
            ->paginate(25);
    }
}
