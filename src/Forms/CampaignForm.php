<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Illuminate\Validation\Rule;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\Fields\Wysiwyg;
use TranquilTools\FormBuilder\FormConfig;

class CampaignForm extends AbstractForm
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
        $lists = array_values(MailingList::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MailingList $list): array => [
                'value' => (string) $list->id,
                'label' => $list->name,
            ])
            ->all());

        $options = config((string) config('mailing.rich_text.options_config_key', 'cms.rich_text.editor_options'), []);

        return [
            Select::make('list_id')
                ->label(__('mailing::campaigns.fields.list'))
                ->options($lists)
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    Rule::in(array_column($lists, 'value')),
                ]),

            Text::make('subject')
                ->label(__('mailing::campaigns.fields.subject'))
                ->required()
                ->rules([
                    'string',
                    'max:150',
                ]),

            Text::make('preheader')
                ->label(__('mailing::campaigns.fields.preheader'))
                ->help(__('mailing::campaigns.help.preheader'))
                ->rules([
                    'nullable',
                    'string',
                    'max:150',
                ]),

            Wysiwyg::make('body_html')
                ->label(__('mailing::campaigns.fields.body_html'))
                ->help(__('mailing::campaigns.help.body_html'))
                ->editor((string) config('mailing.rich_text.editor', 'hugerte'))
                ->options(is_array($options) ? $options : [])
                ->required()
                ->rules([
                    'string',
                    'max:200000',
                ]),

            Textarea::make('body_text')
                ->label(__('mailing::campaigns.fields.body_text'))
                ->help(__('mailing::campaigns.help.body_text'))
                ->rules([
                    'nullable',
                    'string',
                    'max:100000',
                ]),

            Submit::make()->label(__('mailing::common.save')),
        ];
    }
}
