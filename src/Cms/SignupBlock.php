<?php

declare(strict_types=1);

namespace J87NL\Mailing\Cms;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use J87NL\Cms\Blocks\BlockContext;
use J87NL\Cms\Blocks\BlockType;
use J87NL\Mailing\Models\MailingList;
use J87NL\Mailing\Signup\SignupForm;
use TranquilTools\FormBuilder\Fields\Select;
use TranquilTools\FormBuilder\Fields\Text;
use TranquilTools\FormBuilder\Fields\Textarea;
use TranquilTools\FormBuilder\FormConfig;

class SignupBlock implements BlockType
{
    public function __construct(
        private readonly SignupForm $form,
    ) {}

    public function key(): string
    {
        return 'mailing_signup';
    }

    public function label(): string
    {
        return 'mailing::blocks.signup.label';
    }

    public function fields(): array
    {
        return [
            Select::make('list')
                ->label(__('mailing::blocks.signup.fields.list'))
                ->help(__('mailing::blocks.signup.help.list'))
                ->options($this->listOptions())
                ->optionLabel('label')
                ->optionValue('value')
                ->required()
                ->rules([
                    'string',
                    Rule::exists('mailing_lists', 'key')->where('is_public', true),
                ]),

            Text::make('title')
                ->label(__('mailing::blocks.signup.fields.title'))
                ->rules([
                    'nullable',
                    'string',
                    'max:160',
                ]),

            Textarea::make('intro')
                ->label(__('mailing::blocks.signup.fields.intro'))
                ->rules([
                    'nullable',
                    'string',
                    'max:1000',
                ]),

            Text::make('button_label')
                ->label(__('mailing::blocks.signup.fields.button_label'))
                ->help(__('mailing::blocks.signup.help.button_label'))
                ->rules([
                    'nullable',
                    'string',
                    'max:80',
                ]),
        ];
    }

    public function rules(): array
    {
        return FormConfig::make($this->fields())->getRules();
    }

    public function prepare(array $data): array
    {
        return [
            'list' => trim($this->string($data, 'list')),
            'title' => trim($this->string($data, 'title')),
            'intro' => trim($this->string($data, 'intro')),
            'button_label' => trim($this->string($data, 'button_label')),
        ];
    }

    public function view(): string
    {
        return 'mailing::blocks.signup';
    }

    public function viewData(array $data, BlockContext $context): array
    {
        $list = MailingList::query()
            ->where('key', $this->string($data, 'list'))
            ->where('is_public', true)
            ->first();

        if (is_null($list)) {
            return [
                'form' => null,
            ];
        }

        return [
            'form' => $this->form->data(
                $list,
                app(Request::class),
                $context->page->url(),
                null,
                [],
                $this->string($data, 'title'),
                $this->string($data, 'intro'),
                $this->string($data, 'button_label'),
            ),
        ];
    }

    public function toPlainText(array $data): string
    {
        return trim(sprintf('%s %s', $this->string($data, 'title'), $this->string($data, 'intro')));
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function listOptions(): array
    {
        return array_values(MailingList::query()
            ->where('is_public', true)
            ->orderBy('name')
            ->get()
            ->map(fn (MailingList $list): array => [
                'value' => $list->key,
                'label' => $list->name,
            ])
            ->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
