<?php

declare(strict_types=1);

use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Contracts\MailboxReader;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Mailbox\MailboxMessage;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Subscription;
use J87NL\Mailing\Models\Suppression;
use J87NL\Mailing\Sending\Verp;
use J87NL\Mailing\Tests\Fakes\FakeMailboxReader;

function sentCampaign(string $email = 'lid@example.org'): Send
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

function dsn(string $fixture, Send $send, bool $withVerp = true): string
{
    return str_replace(
        [
            '__VERP__',
            '__RECIPIENT__',
            '__MESSAGE_ID__',
        ],
        [
            $withVerp ? (string) Verp::address($send->id) : 'bounces@rommelmarkt.test',
            $send->email,
            (string) $send->message_id,
        ],
        (string) file_get_contents(sprintf('%s/../fixtures/dsn/%s.eml', __DIR__, $fixture)),
    );
}

function deliverToBounceMailbox(string $raw): void
{
    $reader = app(MailboxReader::class);

    if ($reader instanceof FakeMailboxReader) {
        $reader->messages[] = new MailboxMessage(sprintf('msg-%d', count($reader->messages) + 1), $raw);
    }
}

test('the VERP address only resolves with a valid signature', function (): void {
    $address = (string) Verp::address(42);

    expect($address)->toStartWith('bounces+42-')
        ->and(Verp::sendId($address))->toBe(42)
        ->and(Verp::sendId(str_replace('bounces+42-', 'bounces+43-', $address)))->toBeNull();
});

test('a hard bounce suppresses the address and marks the subscription bounced', function (): void {
    $send = sentCampaign();

    deliverToBounceMailbox(dsn('hard-bounce', $send));

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect($send->fresh()->status)->toBe(SendStatus::Bounced)
        ->and($send->fresh()->bounce_type)->toBe(BounceType::Hard)
        ->and($send->fresh()->bounce_diagnostic)->toContain('User unknown')
        ->and(Suppression::query()->sole()->reason)->toBe(SuppressionReason::HardBounce)
        ->and(Subscription::query()->sole()->status)->toBe(SubscriptionStatus::Bounced)
        ->and(app(MailboxReader::class)->unprocessed())->toBe([]);
});

test('a DSN without the VERP address is matched on its Message-ID', function (): void {
    $send = sentCampaign();

    deliverToBounceMailbox(dsn('hard-bounce', $send, false));

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect($send->fresh()->status)->toBe(SendStatus::Bounced);
});

test('three soft bounces within 30 days suppress the address, two do not', function (): void {
    foreach (range(1, 2) as $attempt) {
        deliverToBounceMailbox(dsn('soft-bounce', sentCampaign()));
    }

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect(Suppression::query()->count())->toBe(0)
        ->and(BounceEvent::query()->where('type', BounceType::Soft)->count())->toBe(2)
        ->and(Send::query()->pluck('status')->unique()->all())->toBe([SendStatus::Deferred]);

    deliverToBounceMailbox(dsn('soft-bounce', sentCampaign()));

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect(Suppression::query()->sole()->reason)->toBe(SuppressionReason::SoftBounces);
});

test('soft bounces older than 30 days do not count', function (): void {
    $this->travel(-31)->days();

    foreach (range(1, 2) as $attempt) {
        deliverToBounceMailbox(dsn('soft-bounce', sentCampaign()));
    }

    $this->artisan('mailing:process-bounces')->assertSuccessful();
    $this->travelBack();

    deliverToBounceMailbox(dsn('soft-bounce', sentCampaign()));

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect(Suppression::query()->count())->toBe(0);
});

test('a delay notification marks the send deferred without counting as a bounce or resending', function (): void {
    $send = sentCampaign();

    deliverToBounceMailbox(dsn('delay', $send));

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect($send->fresh()->status)->toBe(SendStatus::Deferred)
        ->and($send->fresh()->next_attempt_at)->toBeNull()
        ->and(BounceEvent::query()->count())->toBe(0);

    runDispatcher(now()->toImmutable()->addDay());

    expect(sentMessages())->toHaveCount(1);
});

test('mail that is not a delivery status notification is archived without effect', function (): void {
    sentCampaign();

    deliverToBounceMailbox("From: someone@example.org\r\nSubject: Hallo\r\n\r\nGewoon een mail.");

    $this->artisan('mailing:process-bounces')->assertSuccessful();

    expect(BounceEvent::query()->count())->toBe(0)
        ->and(app(MailboxReader::class)->unprocessed())->toBe([]);
});
