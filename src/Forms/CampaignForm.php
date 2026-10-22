<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use J87NL\Mailing\Attachments\CampaignAttachments;
use J87NL\Mailing\Forms\Fields\AttachmentFiles;
use J87NL\Mailing\Models\Campaign;
use J87NL\Mailing\Models\CampaignAttachment;
use J87NL\Mailing\Models\MailingList;
use TranquilTools\FormBuilder\AbstractForm;
use TranquilTools\FormBuilder\Fields\BaseField;
use TranquilTools\FormBuilder\Fields\Checkboxes;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Submit;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\Fields\Toggle;
use TranquilTools\FormBuilder\Fields\Wysiwyg;
use TranquilTools\FormBuilder\FormConfig;

class CampaignForm extends AbstractForm
{
    public function __construct(
        private readonly ?Campaign $campaign = null,
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

            ...$this->attachmentFields(),

            Submit::make()->label(__('mailing::common.save')),
        ];
    }

    /**
     * @return list<BaseField>
     */
    private function attachmentFields(): array
    {
        $attachments = app(CampaignAttachments::class);
        $current = is_null($this->campaign) || ! $this->campaign->exists ? [] : $this->campaign->attachments()
            ->get()
            ->all();
        $extensions = $attachments->extensions();

        $fields = [
            AttachmentFiles::make('attachments')
                ->label(__('mailing::campaigns.fields.attachments'))
                ->help(__('mailing::campaigns.help.attachments', [
                    'types' => implode(', ', array_map('strtoupper', array_values(array_diff($extensions, in_array('jpg', $extensions, true) ? ['jpeg'] : [])))),
                    'max' => CampaignAttachments::size($attachments->maxTotalBytes()),
                    'warn' => CampaignAttachments::size($attachments->warnTotalBytes()),
                ]))
                ->attributes([
                    'accept' => array_map(fn (string $extension): string => sprintf('.%s', $extension), $extensions),
                ])
                ->current(array_map(fn (CampaignAttachment $attachment): array => [
                    'key' => (string) $attachment->id,
                    'name' => $attachment->name,
                    'size' => $attachment->size,
                ], $current))
                ->removable()
                ->rules([
                    'nullable',
                    'array',
                    $this->totalSizeRule($current, $attachments->maxTotalBytes()),
                ])
                ->fileRules([
                    'file',
                    sprintf('mimes:%s', implode(',', $extensions)),
                    sprintf('extensions:%s', implode(',', $extensions)),
                    sprintf('max:%d', intdiv($attachments->maxTotalBytes(), 1024)),
                ]),

            Toggle::make('new_attachments_as_link')
                ->label(__('mailing::campaigns.fields.new_attachments_as_link'))
                ->help(__('mailing::campaigns.help.new_attachments_as_link', [
                    'days' => $attachments->linkValidDays(),
                ]))
                ->on($attachments->defaultAsLink())
                ->rules([
                    'nullable',
                    'boolean',
                ]),
        ];

        if ($current === []) {
            return $fields;
        }

        $options = array_map(fn (CampaignAttachment $attachment): array => [
            'value' => (string) $attachment->id,
            'label' => $attachment->name,
        ], $current);

        $fields[] = Checkboxes::make('attachment_links')
            ->label(__('mailing::campaigns.fields.attachment_links'))
            ->help(__('mailing::campaigns.help.attachment_links'))
            ->options($options)
            ->optionLabel('label')
            ->optionValue('value')
            ->rules([
                'nullable',
                'array',
                $this->knownAttachmentsRule(array_column($options, 'value')),
            ]);

        return $fields;
    }

    /**
     * @param  list<string>  $keys
     */
    private function knownAttachmentsRule(array $keys): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($keys): void {
            foreach (is_array($value) ? $value : [] as $key) {
                if (is_scalar($key) && in_array((string) $key, $keys, true)) {
                    continue;
                }

                $fail((string) __('validation.in', [
                    'attribute' => __('mailing::campaigns.fields.attachment_links'),
                ]));

                return;
            }
        };
    }

    /**
     * @param  list<CampaignAttachment>  $current
     */
    private function totalSizeRule(array $current, int $max): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($current, $max): void {
            $removed = request()->input('remove_attachments');
            $removed = is_array($removed) ? array_map('strval', $removed) : [];
            $kept = array_sum(array_map(
                fn (CampaignAttachment $attachment): int => in_array((string) $attachment->id, $removed, true) ? 0 : $attachment->size,
                $current,
            ));
            $added = array_sum(array_map(
                fn (mixed $file): int => $file instanceof UploadedFile ? (int) $file->getSize() : 0,
                is_array($value) ? $value : [],
            ));

            if ($kept + $added <= $max) {
                return;
            }

            $fail((string) __('mailing::campaigns.errors.attachments_too_large', [
                'max' => CampaignAttachments::size($max),
            ]));
        };
    }
}
