<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\DateTime;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\FormConfig;

class ScheduleForm extends AbstractForm
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
            DateTime::make('scheduled_at')
                ->label(__('mailing::campaigns.fields.scheduled_at'))
                ->help(__('mailing::campaigns.help.scheduled_at'))
                ->rules([
                    'nullable',
                    'date',
                ]),

            Submit::make()->label(__('mailing::campaigns.actions.schedule')),
        ];
    }
}
