<?php

declare(strict_types=1);

namespace J87NL\Mailing\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\MailingPermission;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Support\ConsentSource;
use TranquilTools\TableBuilder\AbstractTable;
use TranquilTools\TableBuilder\TableBuilder;

final class SuppressionTable extends AbstractTable
{
    public function authorize(Request $request): bool
    {
        return (bool) $request->user()?->can(MailingPermission::Subscribers->value);
    }

    /**
     * @return Builder<Suppression>
     */
    public function for(): Builder
    {
        return Suppression::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function configure(TableBuilder $table): void
    {
        $canLift = (bool) request()->user()?->can(MailingPermission::Settings->value);
        $reasons = [];

        foreach (SuppressionReason::cases() as $reason) {
            $reasons[$reason->value] = $reason->label();
        }

        $table
            ->withGlobalSearch(columns: [
                'email',
            ])
            ->selectFilter('reason', $reasons, __('mailing::suppressions.fields.reason'))
            ->column('email', __('mailing::suppressions.fields.email'), sortable: true)
            ->column('reason', __('mailing::suppressions.fields.reason'), as: fn (mixed $value, Suppression $suppression): string => $suppression->reason->label())
            ->column('source', __('mailing::suppressions.fields.source'), as: fn (mixed $value, Suppression $suppression): string => ConsentSource::describe($suppression->source))
            ->column('note', __('mailing::suppressions.fields.note'))
            ->column('created_at', __('mailing::suppressions.fields.created_at'), sortable: true, as: fn (mixed $value, Suppression $suppression): string => $suppression->created_at?->isoFormat('D MMM YYYY HH:mm') ?? '')
            ->paginate(50);

        $table->rowAction(
            __('mailing::common.delete'),
            url: fn (Suppression $suppression): string => route('mailing.suppressions.destroy', $suppression),
            method: 'delete',
            confirm: __('mailing::suppressions.actions.delete_confirm'),
            variant: 'destructive',
            disabled: $canLift ? false : (string) __('mailing::suppressions.errors.needs_settings'),
            key: 'delete',
        );
    }
}
