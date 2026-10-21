<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Illuminate\Console\Command;
use J87NL\Mailing\Contracts\PostfixLogReader;
use J87NL\Mailing\Postfix\FilePostfixLogReader;
use J87NL\Mailing\Postfix\PostfixLogProcessor;

class ReadPostfixLog extends Command
{
    private const string STANDARD_INPUT = '-';

    protected $signature = 'mailing:read-postfix-log
        {source? : Use - to read Postfix log lines from standard input}
        {--file= : Read this file once from the start instead of the configured log}';

    protected $description = 'Read the Postfix log and apply delivered, deferred and bounced statuses to the sends';

    public function handle(PostfixLogReader $reader, PostfixLogProcessor $processor): int
    {
        $file = $this->option('file');

        if ($this->argument('source') === self::STANDARD_INPUT) {
            return $this->once('php://stdin', $processor);
        }

        if (is_string($file) && $file !== '') {
            return $this->once($file, $processor);
        }

        $path = config('mailing.postfix_log.path');

        if (! is_string($path) || $path === '') {
            $this->info('The Postfix log is not configured (mailing.postfix_log.path).');

            return self::SUCCESS;
        }

        return $this->report($processor->process($reader->lines()));
    }

    private function once(string $path, PostfixLogProcessor $processor): int
    {
        $handle = is_readable($path) || $path === 'php://stdin' ? fopen($path, 'rb') : false;

        if ($handle === false) {
            $this->error(sprintf('Cannot read %s.', $path));

            return self::FAILURE;
        }

        try {
            return $this->report($processor->process(FilePostfixLogReader::stream($handle)));
        } finally {
            fclose($handle);
        }
    }

    private function report(int $applied): int
    {
        $this->info(sprintf('Applied %d Postfix delivery statuses.', $applied));

        return self::SUCCESS;
    }
}
