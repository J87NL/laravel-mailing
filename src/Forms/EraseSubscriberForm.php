<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Toggle;
use TranquilTools\FormBuilder\FormConfig;

class EraseSubscriberForm extends AbstractForm
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
            Toggle::make('keep_suppressed')
                ->label(__('mailing::subscribers.erase.keep_suppressed'))
                ->help(__('mailing::subscribers.erase.keep_suppressed_help'))
                ->rules([
                    'boolean',
                ]),

            Submit::make()->label(__('mailing::subscribers.erase.submit')),
        ];
    }
}
