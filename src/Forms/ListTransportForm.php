<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Illuminate\Validation\Rule;
use J87NL\Mailing\Senders\ListSender;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Number;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\FormConfig;

class ListTransportForm extends AbstractForm
{
    private const array INTERNAL_MAILERS = [
        'array',
        'log',
        'failover',
        'roundrobin',
    ];

    public function configure(FormConfig $form): void
    {
        $form->class('space-y-6');
    }

    /**
     * @return list<BaseField>
     */
    public function fields(): array
    {
        $senders = app(ListSender::class)->options();
        $mailers = self::mailerOptions();

        return [
            Select::make('mailer')
                ->label(__('mailing::lists.fields.mailer'))
                ->help(__('mailing::lists.help.mailer'))
                ->options($mailers)
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    'string',
                    Rule::in(array_column($mailers, 'value')),
                ]),

            Select::make('sender_identity')
                ->label(__('mailing::lists.fields.sender_identity'))
                ->help(__('mailing::lists.help.sender_identity'))
                ->options($senders)
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    'string',
                    Rule::in(array_column($senders, 'value')),
                ]),

            Number::make('throttle_per_minute')
                ->label(__('mailing::lists.fields.throttle_per_minute'))
                ->help(__('mailing::lists.help.throttle_per_minute'))
                ->required()
                ->rules([
                    'integer',
                    'min:1',
                    'max:600',
                ]),

            Number::make('daily_cap')
                ->label(__('mailing::lists.fields.daily_cap'))
                ->help(__('mailing::lists.help.daily_cap'))
                ->rules([
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100000',
                ]),

            Submit::make()->label(__('mailing::common.save')),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function mailerOptions(): array
    {
        $mailers = config('mail.mailers', []);

        if (! is_array($mailers)) {
            return [];
        }

        $options = [];

        foreach (array_keys($mailers) as $name) {
            if (! is_string($name) || in_array($name, self::INTERNAL_MAILERS, true)) {
                continue;
            }

            $options[] = [
                'value' => $name,
                'label' => $name,
            ];
        }

        return $options;
    }
}
