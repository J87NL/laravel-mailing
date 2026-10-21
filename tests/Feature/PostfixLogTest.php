<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use J87NL\Mailing\Actions\ChangeCampaignStatus;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Contracts\PostfixLogReader;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\CampaignStatus;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\PostfixQueueId;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Models\WebhookEvent;
use J87NL\Mailing\Tests\Fakes\FakePostfixLogReader;

function handedOverSend(string $email = 'lid@example.org'): Send
{
    $list = makeList([
        'key' => sprintf('lijst-%s', substr(md5($email.microtime()), 0, 6)),
    ]);
    makeSubscription($list, $email);
    app(StartCampaign::class)->handle(makeCampaign($list));
    runDispatcher();

    return Send::query()
        ->where('email', $email)
        ->latest('id')
        ->firstOrFail();
}

function postfixLine(string $program, string $message, string $format = 'syslog'): string
{
    return match ($format) {
        'journald' => sprintf('2026-10-21T10:15:01+0200 vps %s[2203]: %s', $program, $message),
        'rfc5424' => sprintf('<22>1 2026-10-21T10:15:01.123456+02:00 vps %s 2203 - - %s', $program, $message),
        default => sprintf('Oct 21 10:15:01 vps %s[2203]: %s', $program, $message),
    };
}

/**
 * @return list<string>
 */
function postfixDelivery(Send $send, string $queueId, string $result, string $format = 'syslog'): array
{
    return [
        postfixLine('postfix/cleanup', sprintf('%s: message-id=%s', $queueId, $send->message_id), $format),
        postfixLine('postfix/smtp', sprintf('%s: to=<%s>, relay=mx.example.org[203.0.113.5]:25, delay=1.2, delays=0.1/0/0.5/0.6, %s', $queueId, $send->email, $result), $format),
    ];
}

function fakePostfixLog(): FakePostfixLogReader
{
    config()->set('mailing.postfix_log.path', '/var/log/mailing-postfix.log');

    $reader = new FakePostfixLogReader;
    app()->instance(PostfixLogReader::class, $reader);

    return $reader;
}

function readPostfixLog(): void
{
    test()->artisan('mailing:read-postfix-log')->assertSuccessful();
}

test('a sent line marks the send delivered with the reply of the remote server', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok: queued as 4F1A2B3C4D)');
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered)
        ->and($send->fresh()->delivered_at)->not->toBeNull()
        ->and($send->fresh()->last_response)->toBe('250 2.0.0 Ok: queued as 4F1A2B3C4D');
});

test('a deferred line in journald format marks the send deferred without a retry by the package', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.2.0, status=deferred (host mx.example.org[203.0.113.5] said: 450 4.2.0 Greylisted, try again later (in reply to RCPT TO command))', 'journald');
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Deferred)
        ->and($send->fresh()->next_attempt_at)->toBeNull()
        ->and($send->fresh()->last_response)->toBe('4.2.0 host mx.example.org[203.0.113.5] said: 450 4.2.0 Greylisted, try again later (in reply to RCPT TO command)');
});

test('a permanent bounce with a long queue id in RFC 5424 format suppresses the address', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = postfixDelivery($send, '4XbYpQ1ZkTz9vsL', 'dsn=5.1.1, status=bounced (host mx.example.org[203.0.113.5] said: 550 5.1.1 User unknown (in reply to RCPT TO command))', 'rfc5424');
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Bounced)
        ->and($send->fresh()->bounce_type)->toBe(BounceType::Hard)
        ->and($send->fresh()->bounce_diagnostic)->toContain('User unknown')
        ->and(Suppression::query()
            ->sole()->reason)->toBe(SuppressionReason::HardBounce)
        ->and(Suppression::query()
            ->sole()->source)->toBe('postfix-log')
        ->and(Subscription::query()
            ->sole()->status)->toBe(SubscriptionStatus::Bounced);
});

test('a bounce with a temporary status code counts as a soft bounce', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.4.7, status=bounced (host mx.example.org[203.0.113.5] said: 452 4.4.7 Mailbox full)');
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Deferred)
        ->and(BounceEvent::query()
            ->sole()->type)->toBe(BounceType::Soft)
        ->and(Suppression::query()
            ->count())->toBe(0);
});

test('an expired message counts as a soft bounce and fails the send', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = [
        postfixLine('postfix/cleanup', sprintf('7D3E21F4A2: message-id=%s', $send->message_id)),
        postfixLine('postfix/qmgr', '7D3E21F4A2: from=<bounces@rommelmarkt.test>, status=expired, returned to sender'),
    ];
    readPostfixLog();

    expect(BounceEvent::query()
        ->sole()->type)->toBe(BounceType::Soft)
        ->and(BounceEvent::query()
            ->sole()->diagnostic)->toBe('expired, returned to sender')
        ->and($send->fresh()->status)->toBe(SendStatus::Failed);
});

test('a delivery line for another recipient of a known message is ignored', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = [
        postfixLine('postfix/cleanup', sprintf('7D3E21F4A2: message-id=%s', $send->message_id)),
        postfixLine('postfix/smtp', '7D3E21F4A2: to=<someone-else@example.net>, relay=mx.example.net[203.0.113.8]:25, dsn=5.1.1, status=bounced (550 5.1.1 User unknown)'),
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(Suppression::query()
            ->count())->toBe(0);
});

test('a delivery line for the original recipient of a rewritten address is applied', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = [
        postfixLine('postfix/cleanup', sprintf('7D3E21F4A2: message-id=%s', $send->message_id)),
        postfixLine('postfix/smtp', sprintf('7D3E21F4A2: to=<rewritten@example.net>, orig_to=<%s>, relay=mx.example.net[203.0.113.8]:25, dsn=2.0.0, status=sent (250 2.0.0 Ok)', strtoupper($send->email))),
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);
});

test('an RFC 5424 line whose app name has no slash is read', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = [
        sprintf('<22>1 2026-10-21T10:15:00+02:00 vps postfix 2202 - - 7D3E21F4A2: message-id=%s', $send->message_id),
        sprintf('<22>1 2026-10-21T10:15:01+02:00 vps postfix 2203 - - 7D3E21F4A2: to=<%s>, relay=mx.example.org[203.0.113.5]:25, dsn=2.0.0, status=sent (250 2.0.0 Ok)', $send->email),
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);
});

test('Postfix text inside the line of another program is ignored', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = [
        sprintf('Oct 21 10:15:00 vps dovecot[812]: imap(x): said postfix/cleanup[9]: AAAAAA: message-id=%s', $send->message_id),
        sprintf('Oct 21 10:15:01 vps dovecot[812]: imap(x): said postfix/smtp[9]: AAAAAA: to=<%s>, dsn=5.1.1, status=bounced (550 5.1.1 User unknown)', $send->email),
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(PostfixQueueId::query()
            ->count())->toBe(0);
});

test('every deferred line keeps the queue id of a long retried message', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();
    [$cleanup, $deferred] = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.4.1, status=deferred (connect to mx.example.org[203.0.113.5]:25: Connection timed out)');
    [, $sent] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');

    $reader->lines = [
        $cleanup,
    ];
    readPostfixLog();

    $this->travel(40)->hours();
    $reader->lines = [
        $deferred,
    ];
    readPostfixLog();

    $this->travel(40)->hours();
    $reader->lines = [
        $sent,
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);
});

test('lines of other mail on the server are never stored or applied', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = explode("\n", trim(str_replace(
        [
            '__MESSAGE_ID__',
            '__RECIPIENT__',
        ],
        [
            trim((string) $send->message_id, '<>'),
            $send->email,
        ],
        (string) file_get_contents(sprintf('%s/../fixtures/postfix/mail.log', __DIR__)),
    )));
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered)
        ->and(WebhookEvent::query()
            ->count())->toBe(1)
        ->and(BounceEvent::query()
            ->count())->toBe(0)
        ->and(Suppression::query()
            ->count())->toBe(0)
        ->and(PostfixQueueId::query()
            ->count())->toBe(0);
});

test('a message id and its delivery line in two different runs are still matched', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();
    [$cleanup, $delivery] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');

    $reader->lines = [
        $cleanup,
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver);

    $reader->lines = [
        $delivery,
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);
});

test('a forgotten queue id expires after the configured time', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();
    [$cleanup, $delivery] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');

    $reader->lines = [
        $cleanup,
    ];
    readPostfixLog();

    $this->travel(49)->hours();

    $reader->lines = [
        $delivery,
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(PostfixQueueId::query()
            ->count())->toBe(0);
});

test('a late deferred line never moves a delivered send backwards', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();
    [$cleanup, $sent] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');
    [, $deferred] = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.4.1, status=deferred (connect to mx.example.org[203.0.113.5]:25: Connection timed out)');

    $reader->lines = [
        $cleanup,
        $sent,
        $deferred,
    ];
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered)
        ->and($send->fresh()->last_response)->toBe('250 2.0.0 Ok');
});

test('the log file is read incrementally and continues in the rotated file without duplicates', function (): void {
    $send = handedOverSend();
    $directory = sprintf('%s/mailing-postfix-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    mkdir($directory);
    $path = sprintf('%s/mailing-postfix.log', $directory);
    config()->set('mailing.postfix_log.path', $path);

    [$cleanup, $deferred] = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.2.0, status=deferred (450 4.2.0 Greylisted)');
    [, $sent] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');

    file_put_contents($path, sprintf("%s\n%s\n", $cleanup, $deferred));
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Deferred);

    file_put_contents($path, sprintf("%s\n", $sent), FILE_APPEND);
    rename($path, sprintf('%s.1', $path));
    file_put_contents($path, sprintf("%s\n", postfixLine('postfix/qmgr', '7D3E21F4A2: removed')));
    readPostfixLog();
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered)
        ->and(WebhookEvent::query()
            ->count())->toBe(2)
        ->and(PostfixQueueId::query()
            ->count())->toBe(0);

    array_map(unlink(...), glob(sprintf('%s/*', $directory)) ?: []);
    rmdir($directory);
});

test('a line that is still being written is read in the next run', function (): void {
    $send = handedOverSend();
    $directory = sprintf('%s/mailing-postfix-%s', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    mkdir($directory);
    $path = sprintf('%s/mailing-postfix.log', $directory);
    config()->set('mailing.postfix_log.path', $path);

    [$cleanup, $sent] = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');
    $half = intdiv(strlen($sent), 2);

    file_put_contents($path, sprintf("%s\n%s", $cleanup, substr($sent, 0, $half)));
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver);

    file_put_contents($path, sprintf("%s\n", substr($sent, $half)), FILE_APPEND);
    readPostfixLog();

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);

    array_map(unlink(...), glob(sprintf('%s/*', $directory)) ?: []);
    rmdir($directory);
});

test('a file passed with --file is read once from the start', function (): void {
    $send = handedOverSend();
    $path = sprintf('%s/mailing-postfix-%s.log', sys_get_temp_dir(), bin2hex(random_bytes(4)));
    file_put_contents($path, sprintf("%s\n", implode("\n", postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)'))));

    $this->artisan('mailing:read-postfix-log', [
        '--file' => $path,
    ])
        ->expectsOutput('Applied 1 Postfix delivery statuses.')
        ->assertSuccessful();

    unlink($path);

    expect($send->fresh()->status)->toBe(SendStatus::Delivered);
});

test('the command does nothing when the log path is not configured', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();
    config()->set('mailing.postfix_log.path', null);

    $reader->lines = postfixDelivery($send, '7D3E21F4A2', 'dsn=2.0.0, status=sent (250 2.0.0 Ok)');

    $this->artisan('mailing:read-postfix-log')
        ->expectsOutput('The Postfix log is not configured (mailing.postfix_log.path).')
        ->assertSuccessful();

    expect($send->fresh()->status)->toBe(SendStatus::HandedOver);
});

test('the reader is only scheduled when the log path is configured', function (bool $configured): void {
    config()->set('mailing.postfix_log.path', $configured ? '/var/log/mailing-postfix.log' : null);

    $commands = collect(app(Schedule::class)->events())
        ->map(fn (Event $event): string => (string) $event->command)
        ->filter(fn (string $command): bool => str_contains($command, 'mailing:read-postfix-log'));

    expect($commands)->toHaveCount($configured ? 1 : 0);
})->with([
    'configured' => true,
    'not configured' => false,
]);

test('cancelling a campaign leaves a send that Postfix is still retrying alone', function (): void {
    $send = handedOverSend();
    $reader = fakePostfixLog();

    $reader->lines = postfixDelivery($send, '7D3E21F4A2', 'dsn=4.2.0, status=deferred (450 4.2.0 Greylisted)');
    readPostfixLog();

    $send->campaign->forceFill([
        'status' => CampaignStatus::Sending,
    ])->save();

    expect(app(ChangeCampaignStatus::class)->cancel($send->campaign))->toBeTrue()
        ->and($send->fresh()->status)->toBe(SendStatus::Deferred);
});
