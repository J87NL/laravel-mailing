<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use J87NL\Mailing\Actions\StartCampaign;
use J87NL\Mailing\Drivers\MailgunDriver;
use J87NL\Mailing\Enums\BounceType;
use J87NL\Mailing\Enums\SendStatus;
use J87NL\Mailing\Enums\SubscriptionStatus;
use J87NL\Mailing\Enums\SuppressionReason;
use J87NL\Mailing\Models\BounceEvent;
use J87NL\Mailing\Models\Send;
use J87NL\Mailing\Models\Suppression;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunApiTransport;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunHttpTransport;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Email;

const MAILGUN_SIGNING_KEY = 'key-mailgun-test';

beforeEach(function (): void {
    config()->set('mailing.mailer_drivers.array', 'mailgun');
    config()->set('mailing.drivers.mailgun.webhook_signing_key', MAILGUN_SIGNING_KEY);

    $list = makeList();
    $this->subscription = makeSubscription($list);
    app(StartCampaign::class)->handle(makeCampaign($list));
    runDispatcher();

    $this->send = Send::query()
        ->sole();
});

/**
 * @param  array<string, mixed>  $changes
 */
function mailgunPayload(string $fixture, ?Send $send, array $changes = [], ?int $timestamp = null, string $key = MAILGUN_SIGNING_KEY): string
{
    $payload = json_decode((string) file_get_contents(sprintf('%s/../fixtures/mailgun/%s.json', __DIR__, $fixture)), true);

    $payload['event-data']['user-variables'] = is_null($send) ? [] : [
        MailgunDriver::SEND_VARIABLE => (string) $send->id,
    ];
    $payload['event-data'] = [
        ...$payload['event-data'],
        ...$changes,
    ];

    $timestamp = (string) ($timestamp ?? now()->getTimestamp());
    $token = bin2hex(random_bytes(25));

    $payload['signature'] = [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', sprintf('%s%s', $timestamp, $token), $key),
    ];

    return (string) json_encode($payload);
}

function postMailgun(string $body): TestResponse
{
    return test()->call('POST', route('mailing.webhooks', ['driver' => 'mailgun']), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
    ], $body);
}

/**
 * @param  array<string, mixed>  $options
 */
function mailgunRequestBody(array $options): string
{
    $body = $options['body'] ?? '';

    if (is_string($body)) {
        return $body;
    }

    $content = '';

    while (($chunk = $body(16372)) !== '') {
        $content .= $chunk;
    }

    return $content;
}

test('the Mailgun driver marks messages with the send id as user variable and the campaign tag', function (): void {
    $message = sentMessages()->sole()->getOriginalMessage();

    expect($message)->toBeInstanceOf(Email::class);

    $metadata = collect($message->getHeaders()->all())->first(fn ($header): bool => $header instanceof MetadataHeader);
    $tag = collect($message->getHeaders()->all())->first(fn ($header): bool => $header instanceof TagHeader);

    expect($metadata?->getKey())->toBe(MailgunDriver::SEND_VARIABLE)
        ->and($metadata?->getValue())->toBe((string) $this->send->id)
        ->and($tag?->getValue())->toBe(sprintf('campaign-%d', $this->send->campaign_id))
        ->and($message->getHeaders()->has('Return-Path'))->toBeFalse();
});

test('the Mailgun API and MIME transports both send the send id as user variable and the campaign tag', function (string $transport): void {
    $requests = [];
    $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): MockResponse {
        $requests[] = mailgunRequestBody($options);

        return new MockResponse((string) json_encode([
            'id' => '<20261019120000.1.ABCDEF@mg.example.org>',
            'message' => 'Queued. Thank you.',
        ]));
    });

    Mail::extend('mailgun-fake', fn (): MailgunApiTransport|MailgunHttpTransport => $transport === 'api'
        ? new MailgunApiTransport('key-test', 'mg.example.org', null, $client)
        : new MailgunHttpTransport('key-test', 'mg.example.org', null, $client));
    config()->set('mail.mailers.mailgun', [
        'transport' => 'mailgun-fake',
    ]);

    $list = makeList([
        'key' => 'klanten',
        'mailer' => 'mailgun',
    ]);
    makeSubscription($list, 'klant@example.org');
    app(StartCampaign::class)->handle(makeCampaign($list));
    runDispatcher();

    $send = Send::query()
        ->where('email', 'klant@example.org')
        ->sole();

    expect($send->status)->toBe(SendStatus::HandedOver)
        ->and($requests)->toHaveCount(1);

    if ($transport === 'api') {
        expect($requests[0])->toContain(sprintf("name=\"v:%s\"\r\n\r\n%d", MailgunDriver::SEND_VARIABLE, $send->id))
            ->and($requests[0])->toContain(sprintf("name=\"o:tag\"\r\n\r\ncampaign-%d", $send->campaign_id));

        return;
    }

    expect($requests[0])->toContain(sprintf('X-Mailgun-Variables: {"%s":"%d"}', MailgunDriver::SEND_VARIABLE, $send->id))
        ->and($requests[0])->toContain(sprintf('X-Mailgun-Tag: campaign-%d', $send->campaign_id));
})->with([
    'API transport (mailgun+api)' => ['api'],
    'MIME transport (mailgun+https, the Laravel default)' => ['https'],
]);

test('Mailgun webhooks that fail the signature check are refused', function (string $case): void {
    $body = match ($case) {
        'wrong key' => mailgunPayload('failed-permanent', $this->send, [], null, 'key-other'),
        'expired' => mailgunPayload('failed-permanent', $this->send, [], now()->subMinutes(6)->getTimestamp()),
        'from the future' => mailgunPayload('failed-permanent', $this->send, [], now()->addMinutes(6)->getTimestamp()),
        'tampered token' => str_replace('"token":"', '"token":"x', mailgunPayload('failed-permanent', $this->send)),
        'no signature' => (string) json_encode([
            'event-data' => json_decode(mailgunPayload('failed-permanent', $this->send), true)['event-data'],
        ]),
        'no signing key configured' => mailgunPayload('failed-permanent', $this->send),
    };

    if ($case === 'no signing key configured') {
        config()->set('mailing.drivers.mailgun.webhook_signing_key', null);
    }

    postMailgun($body)->assertUnauthorized();

    expect($this->send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(Suppression::query()
            ->count())->toBe(0);
})->with([
    'wrong key',
    'expired',
    'from the future',
    'tampered token',
    'no signature',
    'no signing key configured',
]);

test('a Mailgun signature within the tolerance is accepted', function (): void {
    postMailgun(mailgunPayload('delivered', $this->send, [], now()->subMinutes(4)->getTimestamp()))->assertOk()->assertJson([
        'applied' => 1,
    ]);
});

test('each Mailgun event maps to the right status', function (string $fixture, SendStatus $status, ?SuppressionReason $suppression, ?BounceType $bounce, SubscriptionStatus $subscription): void {
    postMailgun(mailgunPayload($fixture, $this->send))->assertOk()->assertJson([
        'applied' => 1,
    ]);

    expect($this->send->fresh()->status)->toBe($status)
        ->and(Suppression::query()
            ->first()?->reason)->toBe($suppression)
        ->and(BounceEvent::query()
            ->first()?->type)->toBe($bounce)
        ->and($this->subscription->fresh()->status)->toBe($subscription);
})->with([
    'delivered' => ['delivered', SendStatus::Delivered, null, null, SubscriptionStatus::Subscribed],
    'failed, temporary' => ['failed-temporary', SendStatus::Deferred, null, BounceType::Soft, SubscriptionStatus::Subscribed],
    'failed, permanent' => ['failed-permanent', SendStatus::Bounced, SuppressionReason::HardBounce, BounceType::Hard, SubscriptionStatus::Bounced],
    'failed, on the Mailgun suppression list' => ['failed-suppressed', SendStatus::Failed, null, null, SubscriptionStatus::Subscribed],
    'complained' => ['complained', SendStatus::Complained, SuppressionReason::Complaint, null, SubscriptionStatus::Complained],
    'unsubscribed' => ['unsubscribed', SendStatus::HandedOver, null, null, SubscriptionStatus::Unsubscribed],
]);

test('Mailgun events keep the SMTP code and message of the receiving server', function (): void {
    postMailgun(mailgunPayload('delivered', $this->send))->assertOk();

    expect($this->send->fresh()->last_response)->toBe('250 OK');

    postMailgun(mailgunPayload('failed-permanent', $this->send))->assertOk();

    expect($this->send->fresh()->bounce_diagnostic)->toBe('550 5.1.1 The email account that you tried to reach does not exist. (bounce)');
});

test('Mailgun events without our send id are ignored without an error', function (string $fixture): void {
    postMailgun(mailgunPayload($fixture, null))->assertOk()->assertJson([
        'applied' => 0,
    ]);

    expect($this->send->fresh()->status)->toBe(SendStatus::HandedOver)
        ->and(Suppression::query()
            ->count())->toBe(0)
        ->and(BounceEvent::query()
            ->count())->toBe(0);
})->with([
    'delivered',
    'failed-permanent',
    'complained',
    'unsubscribed',
]);

test('Mailgun events the package does not track are ignored', function (): void {
    postMailgun(mailgunPayload('delivered', $this->send, [
        'event' => 'opened',
    ]))->assertOk()->assertJson([
        'applied' => 0,
    ]);

    expect($this->send->fresh()->status)->toBe(SendStatus::HandedOver);
});

test('Mailgun retries of one temporarily failed message never suppress the address', function (): void {
    foreach (range(1, 3) as $retry) {
        postMailgun(mailgunPayload('failed-temporary', $this->send, [
            'id' => sprintf('temporary-%d', $retry),
        ]))->assertJson([
            'applied' => 1,
        ]);
    }

    expect(BounceEvent::query()
        ->count())->toBe(3)
        ->and(Suppression::query()
            ->count())->toBe(0)
        ->and($this->subscription->fresh()->status)->toBe(SubscriptionStatus::Subscribed);
});

test('a repeated Mailgun event is applied once and a late delivery never moves a bounce back', function (): void {
    $bounce = mailgunPayload('failed-permanent', $this->send);

    postMailgun($bounce)->assertJson([
        'applied' => 1,
    ]);
    postMailgun($bounce)->assertJson([
        'applied' => 0,
    ]);
    postMailgun(mailgunPayload('delivered', $this->send))->assertOk();

    expect($this->send->fresh()->status)->toBe(SendStatus::Bounced)
        ->and(BounceEvent::query()
            ->count())->toBe(1);
});
