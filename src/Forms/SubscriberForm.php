<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Illuminate\Validation\Rule;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Email;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\FormConfig;

class SubscriberForm extends AbstractForm
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
                ->label(__('mailing::subscribers.fields.list'))
                ->options($lists)
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    Rule::in(array_column($lists, 'value')),
                ]),

            Email::make('email')
                ->label(__('mailing::subscribers.fields.email'))
                ->required()
                ->rules([
                    'email:rfc',
                    'max:254',
                ]),

            Text::make('name')
                ->label(__('mailing::subscribers.fields.name'))
                ->rules([
                    'nullable',
                    'string',
                    'max:120',
                ]),

            Text::make('consent_source')
                ->label(__('mailing::subscribers.fields.consent_source'))
                ->help(__('mailing::subscribers.help.consent_source'))
                ->required()
                ->rules([
                    'string',
                    'max:200',
                ]),

            Textarea::make('consent_note')
                ->label(__('mailing::subscribers.fields.consent_note'))
                ->help(__('mailing::subscribers.help.consent_note'))
                ->required()
                ->rules([
                    'string',
                    'max:1000',
                ]),

            Submit::make()->label(__('mailing::subscribers.actions.add')),
        ];
    }
}
