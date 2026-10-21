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

    /**
     * @param  array<int, mixed>  $rules
     */
    public function fileRules(array $rules): static
    {
        $this->fileRules = $rules;

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
