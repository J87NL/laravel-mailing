<?php

declare(strict_types=1);

namespace J87NL\Mailing\Postfix;

use Generator;
use J87NL\Mailing\Contracts\PostfixLogReader;
use J87NL\Mailing\Models\PostfixLogCursor;

class FilePostfixLogReader implements PostfixLogReader
{
    private const string ROTATED_SUFFIX = '.1';

    public function lines(): iterable
    {
        $path = config('mailing.postfix_log.path');

        if (! is_string($path) || $path === '') {
            return;
        }

        $handle = $this->open($path);

        if (is_null($handle)) {
            return;
        }

        try {
            [$inode, $size] = $this->stat($handle);

            $cursor = PostfixLogCursor::query()
                ->firstOrNew([
                    'path' => $path,
                ]);

            $start = 0;

            if ($cursor->exists && $cursor->inode === $inode && $cursor->position <= $size) {
                $start = $cursor->position;
            }

            if ($cursor->exists && $cursor->inode !== $inode) {
                yield from $this->rotated($path, $cursor);
            }

            fseek($handle, $start);

            $end = yield from self::stream($handle);

            $cursor->fill([
                'inode' => $inode,
                'position' => $end,
            ])->save();
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, string, mixed, int>
     */
    public static function stream($handle, bool $completeLinesOnly = true): Generator
    {
        $position = (int) ftell($handle);

        while (($line = fgets($handle)) !== false) {
            if ($completeLinesOnly && ! str_ends_with($line, "\n")) {
                break;
            }

            $position = (int) ftell($handle);

            yield rtrim($line, "\r\n");
        }

        return $position;
    }

    /**
     * @return Generator<int, string, mixed, void>
     */
    private function rotated(string $path, PostfixLogCursor $cursor): Generator
    {
        $handle = $this->open(sprintf('%s%s', $path, self::ROTATED_SUFFIX));

        if (is_null($handle)) {
            return;
        }

        try {
            [$inode, $size] = $this->stat($handle);

            if ($inode !== $cursor->inode || $cursor->position > $size) {
                return;
            }

            fseek($handle, $cursor->position);

            yield from self::stream($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return resource|null
     */
    private function open(string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        return $handle === false ? null : $handle;
    }

    /**
     * @param  resource  $handle
     * @return array{0: int, 1: int}
     */
    private function stat($handle): array
    {
        $stat = fstat($handle);

        return [
            (int) ($stat['ino'] ?? 0),
            (int) ($stat['size'] ?? 0),
        ];
    }
}
