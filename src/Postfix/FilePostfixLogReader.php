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

        clearstatcache();

        $inode = $this->inode($path);

        if (is_null($inode)) {
            return;
        }

        $cursor = PostfixLogCursor::query()
            ->firstOrNew([
                'path' => $path,
            ]);

        $start = 0;

        if ($cursor->exists && $cursor->inode === $inode && $cursor->position <= (int) filesize($path)) {
            $start = $cursor->position;
        }

        if ($cursor->exists && $cursor->inode !== $inode) {
            yield from $this->rotated($path, $cursor);
        }

        $end = yield from $this->read($path, $start);

        $cursor->fill([
            'inode' => $inode,
            'position' => $end,
        ])->save();
    }

    /**
     * @param  resource  $handle
     * @return Generator<int, string, mixed, int>
     */
    public static function stream($handle): Generator
    {
        $position = (int) ftell($handle);

        while (($line = fgets($handle)) !== false && str_ends_with($line, "\n")) {
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
        $rotated = sprintf('%s%s', $path, self::ROTATED_SUFFIX);

        if ($this->inode($rotated) !== $cursor->inode || $cursor->position > (int) filesize($rotated)) {
            return;
        }

        yield from $this->read($rotated, $cursor->position);
    }

    /**
     * @return Generator<int, string, mixed, int>
     */
    private function read(string $path, int $start): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return $start;
        }

        try {
            fseek($handle, $start);

            return yield from self::stream($handle);
        } finally {
            fclose($handle);
        }
    }

    private function inode(string $path): ?int
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $inode = fileinode($path);

        return $inode === false ? null : $inode;
    }
}
