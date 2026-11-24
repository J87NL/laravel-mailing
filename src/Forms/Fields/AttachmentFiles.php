<?php

declare(strict_types=1);

namespace J87NL\Mailing\Forms\Fields;

use TranquilTools\FormBuilder\Fields\Files;

class AttachmentFiles extends Files
{
    /**
     * @var array<int, mixed>
     */
    protected array $fileRules = [];

    public function fileRules(array|string|object ...$rules): static
    {
        $this->fileRules = collect($rules)
            ->map(fn (mixed $rule): mixed => is_string($rule) ? explode('|', $rule) : $rule)
            ->flatten()
            ->all();

        return $this;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function nestedRules(): array
    {
        return [
            ...parent::nestedRules(),
            sprintf('%s.*', (string) $this->getName()) => $this->fileRules,
        ];
    }
}
