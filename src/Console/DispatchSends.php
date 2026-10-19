<?php

declare(strict_types=1);

namespace J87NL\Mailing\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use J87NL\Mailing\Sending\Dispatcher;

class DispatchSends extends Command
{
    protected $signature = 'mailing:dispatch';

    protected $description = 'Start due campaigns and release their sends within the throttles';

    public function handle(Dispatcher $dispatcher): int
    {
        $result = $dispatcher->run(CarbonImmutable::now());

        $this->info(sprintf(
            'Started %d campaigns, dispatched %d sends, marked %d interrupted sends as failed, finished %d campaigns.',
            $result['started'],
            $result['dispatched'],
            $result['interrupted'],
            $result['finished'],
        ));

        return self::SUCCESS;
    }
}
