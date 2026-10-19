<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Illuminate\Validation\Rule;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\File;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\FormConfig;

class ImportForm extends AbstractForm
{
    public function configure(FormConfig $form): void
    {
        $form->class('space-y-6');
    }

    /**
     * @return list<BaseField>
     */
    public function fields(): array
    {
        $lists = MailingList::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MailingList $list): array => [
                'value' => (string) $list->id,
                'label' => $list->name,
            ])
            ->values()
            ->all();

        return [
            Select::make('list_id')
                ->label(__('mailing::imports.fields.list'))
                ->options($lists)
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    Rule::in(array_column($lists, 'value')),
                ]),

            File::make('file')
                ->label(__('mailing::imports.fields.file'))
                ->help(__('mailing::imports.help.file'))
                ->accept([
                    'text/csv',
                    'text/plain',
                    '.csv',
                ])
                ->required()
                ->rules([
                    'file',
                    'mimes:csv,txt',
                    sprintf('max:%d', (int) config('mailing.import.max_kilobytes', 1024)),
                ]),

            Text::make('consent_source')
                ->label(__('mailing::imports.fields.consent_source'))
                ->help(__('mailing::imports.help.consent_source'))
                ->required()
                ->rules([
                    'string',
                    'max:200',
                ]),

            Textarea::make('consent_note')
                ->label(__('mailing::imports.fields.consent_note'))
                ->help(__('mailing::imports.help.consent_note'))
                ->rules([
                    'nullable',
                    'string',
                    'max:1000',
                ]),

            Submit::make()->label(__('mailing::imports.actions.import')),
        ];
    }
}
