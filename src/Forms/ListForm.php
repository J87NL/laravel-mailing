<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Illuminate\Validation\Rule;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Email;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\Fields\Toggle;
use TranquilTools\FormBuilder\FormConfig;

class ListForm extends AbstractForm
{
    public function __construct(
        private readonly ?MailingList $list = null,
    ) {}

    public function configure(FormConfig $form): void
    {
        $form->class('space-y-6');
    }

    /**
     * @return list<BaseField>
     */
    public function fields(): array
    {
        return [
            Text::make('name')
                ->label(__('mailing::lists.fields.name'))
                ->required()
                ->rules([
                    'string',
                    'max:120',
                ]),

            Text::make('key')
                ->label(__('mailing::lists.fields.key'))
                ->help(__('mailing::lists.help.key'))
                ->required()
                ->rules([
                    'string',
                    'max:64',
                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                    Rule::unique('mailing_lists', 'key')->ignore($this->list?->id),
                ]),

            Textarea::make('description')
                ->label(__('mailing::lists.fields.description'))
                ->help(__('mailing::lists.help.description'))
                ->rules([
                    'nullable',
                    'string',
                    'max:1000',
                ]),

            Email::make('reply_to')
                ->label(__('mailing::lists.fields.reply_to'))
                ->help(__('mailing::lists.help.reply_to'))
                ->rules([
                    'nullable',
                    'email:rfc',
                    'max:254',
                ]),

            Toggle::make('is_public')
                ->label(__('mailing::lists.fields.is_public'))
                ->help(__('mailing::lists.help.is_public'))
                ->rules([
                    'boolean',
                ]),

            Submit::make()->label(__('mailing::common.save')),
        ];
    }
}
