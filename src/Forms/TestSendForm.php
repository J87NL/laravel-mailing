<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\FormConfig;

class TestSendForm extends AbstractForm
{
    public function configure(FormConfig $form): void
    {
        $form->class('space-y-4');
    }

    /**
     * @return list<BaseField>
     */
    public function fields(): array
    {
        return [
            Text::make('recipients')
                ->label(__('mailing::campaigns.fields.test_recipients'))
                ->help(__('mailing::campaigns.help.test_recipients'))
                ->required()
                ->rules([
                    'string',
                    'max:1000',
                ]),

            Submit::make()->label(__('mailing::campaigns.actions.test_send')),
        ];
    }
}
