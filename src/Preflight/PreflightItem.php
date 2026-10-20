<?php

declare(strict_types=1);

namespace J87NL\Mailing\Preflight;

final readonly class PreflightItem
{
    public const string OK = 'ok';

    public const string WARNING = 'warning';

    public const string ERROR = 'error';

    public function __construct(
        public string $key,
        public string $status,
        public string $message,
    ) {}

    public function blocks(): bool
    {
        return $this->status === self::ERROR;
    }

    /**
     * @return array{key: string, status: string, label: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status,
            'label' => (string) __(sprintf('mailing::preflight.items.%s', $this->key)),
            'message' => $this->message,
        ];
    }
}
