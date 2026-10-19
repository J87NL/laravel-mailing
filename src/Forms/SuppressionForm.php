<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Email;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\FormConfig;

class SuppressionForm extends AbstractForm
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
        return [
            Email::make('email')
                ->label(__('mailing::suppressions.fields.email'))
                ->required()
                ->rules([
                    'email:rfc',
                    'max:254',
                    'unique:mailing_suppressions,email',
                ]),

            Textarea::make('note')
                ->label(__('mailing::suppressions.fields.note'))
                ->help(__('mailing::suppressions.help.note'))
                ->rules([
                    'nullable',
                    'string',
                    'max:1000',
                ]),

            Submit::make()->label(__('mailing::suppressions.actions.add')),
        ];
    }
}
